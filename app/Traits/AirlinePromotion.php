<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait AirlinePromotion
{

    protected static bool $codeShare = false;
    /**
     * Get the commission for the given request.
     *
     * @param array $request
     * @param string $llcIataCode
     * @param bool $code_share
     * @return array
     */
    public static function getCommision($request, $llcIataCode = "", $code_share = false)
    {
        $promotions = [];
        static::$codeShare = $code_share;
        if (!is_head_office() && !is_head_office_emp()) {
            $margins = static::getSectorCommission($request, $llcIataCode);

            if (count($margins) > 0) {
                $promotions = $margins;
            }
        }
        return $promotions;
    }

    static function getSectorCommission($request, $llcIataCode)
    {
        $margin_region = '';

        if (static::checkDosmatic($request)) {
            $margin_region = 'DOMESTIC';
        } elseif (static::checkSoto($request)) {
            $margin_region = 'SOTO';
        } elseif (!static::checkDosmatic($request)) { /// INTERNATIONAL FLIGHTS
            $margin_region = 'EX-PAKISTAN';
        }

        if (static::$codeShare) {
            $margin_region = 'CODE-SHARE';
        }

        $travel_date = \Carbon\Carbon::parse($request['departure_date'])->toDateString();
        $user = Auth::user();
        $orgId = $user->org_id;
        $organization = $user->organization;

        $today = \Carbon\Carbon::now()->format('Y-m-d');
        $baseQuery = \App\Models\AirlineMargin::query()
            ->with(['airline:id,iata_code', 'margin_originations' => function ($query) use ($orgId, $organization) {
                // if (is_branch() || is_branch_emp()) {
                //     $query->where('org_id', $orgId);
                // } else {
                //     $query->whereIn('org_id', [$organization->id, $organization->parent_id])
                //         ->orderByRaw("CASE 
                //     WHEN org_id = ? THEN 0
                //     WHEN org_id = ? THEN 1
                //     ELSE 2 END", [
                //             $organization->id,
                //             $organization->parent_id
                //         ]);
                // }
                $query->where('org_id', $orgId);
            }])
            // ->where(function ($query) use ($travel_date, $today) {
            //     $query->where('sale_start_continue', '<=', $today)
            //         ->orWhere('sale_end_continue', '>=', $today)
            //         ->orWhere('travel_start_continue', '>=', $travel_date)
            //         ->orWhere('travel_end_continue', '=<', $travel_date)
            //         ->orWhereNull(['sale_start_continue', 'sale_end_continue', 'travel_start_continue', 'travel_end_continue']);
            // })

            ->where(function ($query) use ($travel_date, $today) {

                // Case 1: Both sale & travel dates exist → BOTH must match
                $query->where(function ($q) use ($today, $travel_date) {
                    $q->whereNotNull('sale_start_continue')
                      ->whereNotNull('sale_end_continue')
                      ->whereNotNull('travel_start_continue')
                      ->whereNotNull('travel_end_continue')
                      ->whereDate('sale_start_continue', '<=', $today)
                      ->whereDate('sale_end_continue', '>=', $today)
                      ->whereDate('travel_start_continue', '<=', $travel_date)
                      ->whereDate('travel_end_continue', '>=', $travel_date);
                })
            
                // Case 2: Only sale dates exist
                ->orWhere(function ($q) use ($today) {
                    $q->whereNotNull('sale_start_continue')
                      ->whereNotNull('sale_end_continue')
                      ->whereNull('travel_start_continue')
                      ->whereNull('travel_end_continue')
                      ->whereDate('sale_start_continue', '<=', $today)
                      ->whereDate('sale_end_continue', '>=', $today);
                })
            
                // Case 3: Only travel dates exist
                ->orWhere(function ($q) use ($travel_date) {
                    $q->whereNull('sale_start_continue')
                      ->whereNull('sale_end_continue')
                      ->whereNotNull('travel_start_continue')
                      ->whereNotNull('travel_end_continue')
                      ->whereDate('travel_start_continue', '<=', $travel_date)
                      ->whereDate('travel_end_continue', '>=', $travel_date);
                })
            
                // Case 4: No date restrictions
                ->orWhere(function ($q) {
                    $q->whereNull('sale_start_continue')
                      ->whereNull('sale_end_continue')
                      ->whereNull('travel_start_continue')
                      ->whereNull('travel_end_continue');
                });
            })
            
            ->whereHas('airline', function ($query) use ($llcIataCode) {
                $query->where('iata_code', $llcIataCode);
            })
            ->where('status', true);
        $allSectors = (clone $baseQuery)
            ->whereJsonContains('region', 'ALL-SECTORS')
            ->get();

        if ($allSectors->isNotEmpty()) {
            $airlineCommissions = $allSectors;
        } else {
            $airlineCommissions = (clone $baseQuery)
                ->whereJsonContains('region', $margin_region)
                ->get();
        }

        $airlineCommisions = $airlineCommissions
            ->map(function ($item) {
                return [
                    'margin_type' => $item['margin_originations'][0]['margin_type'] ?? "",
                    'margin' => $item['margin_originations'][0]['margin'] ?? 0,
                    'branch_margin_type' => $item['margin_originations'][1]['margin_type'] ?? "",
                    'branch_margin' => $item['margin_originations'][1]['margin'] ?? 0,
                    'is_apply_on_gross' => $item['is_apply_on_gross'],
                ];
            });
        return $airlineCommisions;
    }

    static function checkDosmatic($request)
    {
        return \App\Models\Airport::query()
            ->selectRaw('COUNT(DISTINCT country) as country_count')
            ->whereIn('iata_code', [manage_request($request, 'origin'), manage_request($request, 'destination')])
            ->value('country_count') == 1 ? true : false;
    }

    static function checkSoto($request)
    {
        return \App\Models\Airport::query()->where(['iata_code' => manage_request($request, 'origin')])->where('iso_country', '!=', 'PK')->exists();
    }
}
