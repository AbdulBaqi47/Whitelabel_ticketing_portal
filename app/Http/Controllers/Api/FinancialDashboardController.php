<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Response};

class FinancialDashboardController extends Controller
{
     public function topAirlinesStats(Request $request)
    {
        try {

            $from = $request->from !== 'null' ? \Carbon\Carbon::parse($request->from)->startOfDay() : null;
            $to   = $request->to   !== 'null' ? \Carbon\Carbon::parse($request->to)->endOfDay()     : null;

            $firstSegments = DB::table('booked_segments')
                ->selectRaw('
                    booking_id,
                    COALESCE(NULLIF(m_airline, ""), o_airline) as airline_code,
                    ROW_NUMBER() OVER (PARTITION BY booking_id ORDER BY seg_departure_datetime ASC) as rn
                ');

            $airlineStats = \App\Models\Booking::joinSub($firstSegments, 'segments', function ($join) {
                $join->on('bookings.id', '=', 'segments.booking_id')
                    ->where('segments.rn', 1);
                })
                ->leftJoin('airlines', 'segments.airline_code', '=', 'airlines.iata_code')
                ->selectRaw('
                    COALESCE(airlines.name, segments.airline_code) as airline,
                    SUM(CASE WHEN bookings.status = "issued" THEN 1 ELSE 0 END) as issued_tickets,
                    SUM(CASE WHEN bookings.status = "confirmed" THEN 1 ELSE 0 END) as confirmed_bookings,
                    SUM(CASE WHEN bookings.status = "expired" THEN 1 ELSE 0 END) as expired_bookings,
                    SUM(CASE WHEN bookings.status = "voided" THEN 1 ELSE 0 END) as voided_tickets,
                    SUM(CASE WHEN bookings.status = "issued" THEN (COALESCE(bookings.total_amount, 0) + COALESCE(bookings.extra_amount, 0) + COALESCE(bookings.return_bundle_amount, 0)) ELSE 0 END) as total_sales
                ')
                ->orgFilter()
                ->when($from && $to, fn($q) => $q->whereBetween('bookings.created_at', [$from, $to]))
                ->groupBy('airline')
                ->orderByDesc('issued_tickets')
                ->limit(10)
                ->get();

            $airlines = [];
            $issued = [];
            $confirmed = [];
            $expired = [];
            $voided = [];
            $totalSales = [];

            foreach ($airlineStats as $row) {

                $airlines[] = $row->airline ?? 'Unknown';
                $issued[] = (int) $row->issued_tickets;
                $confirmed[] = (int) $row->confirmed_bookings;
                $expired[] = (int) $row->expired_bookings;
                $voided[] = (int) $row->voided_tickets;
                $totalSales[] = (float) $row->total_sales;
            }

            $response = [
                'airlines' => $airlines,
                'series' => [
                    [
                        'name' => 'Issued Tickets',
                        'data' => $issued
                    ],
                    [
                        'name' => 'Confirmed Bookings',
                        'data' => $confirmed
                    ],
                    [
                        'name' => 'Expired Bookings',
                        'data' => $expired
                    ],
                    [
                        'name' => 'Voided Tickets',
                        'data' => $voided
                    ],
                    [
                        'name' => 'Total Sales',
                        'data' => $totalSales
                    ]
                ]
            ];

            return Response::successResponse(200, 'Top Airline Statictis', $response);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function providerPerformanceStats()
    {
        try {

            $providerStats = \App\Models\Booking::selectRaw('
                bookings.provider_name,
                SUM(CASE WHEN status = "issued" THEN 1 ELSE 0 END) as issued,
                SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = "expired" THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN status = "voided" THEN 1 ELSE 0 END) as voided,
                SUM(CASE WHEN status = "refunded" THEN 1 ELSE 0 END) as refunded
            ')
            ->orgFilter()
            ->groupBy('bookings.provider_name')
            ->get();


            $data = [];


            foreach ($providerStats as $row) {

                $total =
                    $row->issued +
                    $row->confirmed +
                    $row->expired +
                    $row->voided +
                    $row->refunded;

                if ($total == 0) {
                    continue;
                }

                $data[] = [
                    'name' => $row->provider_name,
                    'total' => $total,
                    'statuses' => [
                        'issued' => (int) $row->issued,
                        'confirmed' => (int) $row->confirmed,
                        'expired' => (int) $row->expired,
                        'voided' => (int) $row->voided,
                        'refunded' => (int) $row->refunded,
                    ]
                ];
            }

            return  Response::successResponse(200, 'Provider Booking Statistics', $data);
        } catch (\Exception $e) {

            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function agencyLoginStats()
    {

        if (!in_array(role_name(), ['head-office', 'h-employee', 'branch', 'b-employee'])) {
            return Response::errorResponse(403, "You don't have permission to access this module.");
        }

        try {

            $dailyLogins = \App\Models\UserLoginLog::query()
                ->selectRaw('DATE(login_at) as date, COUNT(*) as total')
                ->where('login_at', '>=', now()->subDays(7))
                ->where(function($subQuery){
                    if(in_array(role_name(), ['branch', 'b-employee'])){
                        $orgIds = \App\Models\Organization::where('parent_id',org_id())->pluck('id')->toArray();
                        $subQuery->whereIn('org_id', $orgIds);
                    }
                })->groupByRaw('DATE(login_at)')
                ->orderBy('date')
                ->get();

            $trend = [
                'days' => $dailyLogins->pluck('date')->map(fn($date) => \Carbon\Carbon::parse($date)->format('D')),
                'totals' => $dailyLogins->pluck('total'),
            ];

            $agencies = \App\Models\UserLoginLog::query()
                ->join('users', 'user_login_logs.user_id', '=', 'users.id')
                ->selectRaw('users.name as name, COUNT(*) as logins, MAX(login_at) as last_login')
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('logins')
                ->limit(10)
                ->get()
                ->map(function ($row) {
                    return [
                        'name' => $row->name,
                        'logins' => $row->logins,
                        'lastLogin' => \Carbon\Carbon::parse($row->last_login)->diffForHumans()
                    ];
                });

            $data = [
                'trend' => $trend,
                'agencies' => $agencies
            ];

            return Response::successResponse(200, 'Top Logins List', $data);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function receivables()
    {
        if (!in_array(role_name(), ['head-office', 'h-employee', 'branch', 'b-employee'])) {
            return Response::errorResponse(403, "You don't have permission to access this module.");
        }

        try {

            $tempCreditSub = DB::table('temporay_credit_limits')
                ->select(['wallet_id', DB::raw('SUM(limit_amount) as temp_credit')])
                ->where('instant_stop_limit', false)
                ->where('end_date', '>=', today())
                ->whereColumn('used_amount', '<', 'limit_amount')
                ->groupBy('wallet_id');

            $agencies = DB::table('organizations as o')
                ->select([
                    'o.uuid',
                    'o.name',
                    'branch.name as branch_name',
                    'u.email',
                    'u.phone_number',
                    DB::raw('COALESCE(SUM(te.credit), 0) - COALESCE(SUM(te.debit), 0) as raw_balance'),
                    DB::raw('COALESCE(tcl.temp_credit, 0) as temp_credit'),
                    DB::raw('COUNT(DISTINCT t.id) as transaction_count'),
                ])
                ->join('users as u', 'u.org_id', '=', 'o.id')
                ->join('model_has_roles as mhr', fn($j) => $j->on('mhr.model_uuid', '=', 'u.id')->where('mhr.model_type', 'App\Models\User'))
                ->join('roles as r', fn($j) => $j->on('r.uuid', '=', 'mhr.role_id')->where('r.name', 'agency'))
                ->leftJoin('organizations as branch', 'branch.id', '=', 'o.parent_id')
                ->leftJoin('transactions as t', fn($j) => $j->on('t.org_id', '=', 'o.id')->where('t.transaction_show', true))
                ->leftJoin('transaction_entries as te', 'te.transaction_id', '=', 't.id')
                ->leftJoin('wallets as w', 'w.org_id', '=', 'o.id')
                ->leftJoinSub($tempCreditSub, 'tcl', 'tcl.wallet_id', '=', 'w.id')
                ->whereNull('o.deleted_at')
                ->when(
                    in_array(role_name(), ['branch', 'b-employee']),
                    fn($q) => $q->where('o.parent_id', org_id()),
                    fn($q) => $q->where('branch.parent_id', org_id())
                )
                ->groupBy('o.id', 'o.uuid', 'o.name', 'branch.name', 'u.email', 'u.phone_number', 'tcl.temp_credit')
                ->havingRaw('raw_balance < 0')
                ->orderByRaw('raw_balance ASC')
                ->get();

            $list = $agencies->map(fn($row) => [
                'uuid'              => $row->uuid,
                'name'              => $row->name,
                'branch_name'       => $row->branch_name,
                'email'             => $row->email,
                'contact'           => $row->phone_number,
                'wallet_balance'    => $row->raw_balance < 0 ? 0 : round($row->raw_balance, 2),
                'temp_credit'       => round($row->temp_credit, 2),
                'receivable'        => round(abs($row->raw_balance), 2),
                'transaction_count' => (int) $row->transaction_count,
            ]);

            return Response::successResponse(200, 'Receivables', [
                'total_receivable'  => round($list->sum('receivable'), 2),
                'agency_count'      => $list->count(),
                'agencies'          => $list,
            ]);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }
}
