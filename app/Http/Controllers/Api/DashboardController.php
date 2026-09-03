<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use \Carbon\Carbon;
use Illuminate\Support\Facades\Response;

class DashboardController extends Controller
{
   public function stats(Request $request)
    {
        try {

            [$from, $to, $previousFrom, $previousTo] = calculate($request);
            $currentStats = stats_data($from, $to);
            $previousStats = stats_data($previousFrom, $previousTo);

            $currentSales = (float) ($currentStats->total_sales ?? 0);
            $previousSales = (float) ($previousStats->total_sales ?? 0);

            $currentBookingCount = (int) ($currentStats->total_bookings ?? 0);
            $previousBookingCount = (int) ($previousStats->total_bookings ?? 0);

            $currentIssuedTickets = (int) ($currentStats->total_issued_tickets ?? 0);
            $previousIssuedTickets = (int) ($previousStats->total_issued_tickets ?? 0);

            $currentVoidTickets = (int) ($currentStats->total_void_tickets ?? 0);
            $previousVoidTickets = (int) ($previousStats->total_void_tickets ?? 0);


            $stats = [

                'sales' => [
                    'current_total' => $currentSales,
                    'previous_total' => $previousSales,
                    'current_percentage' => round(percentage_change($currentSales, $previousSales), 1),
                ],
            
                'bookings' => [
                    'current_total' => $currentBookingCount,
                    'previous_total' => $previousBookingCount,
                    'current_percentage' => round(percentage_change($currentBookingCount, $previousBookingCount), 1),
                ],
            
                'issued_tickets' => [
                    'current_total' => $currentIssuedTickets,
                    'previous_total' => $previousIssuedTickets,
                    'current_percentage' => round(percentage_change($currentIssuedTickets, $previousIssuedTickets), 1),
                ],
            
                'void_tickets' => [
                    'current_total' => $currentVoidTickets,
                    'previous_total' => $previousVoidTickets,
                    'current_percentage' => round(percentage_change($currentVoidTickets, $previousVoidTickets), 1),
                ]
            
            ];

            return Response::successResponse(200, 'Dashboard Stats', $stats);

        } catch (\Exception $e) {

            return Response::errorResponse(500, $e->getMessage());

        }
    }

    public function salesGraphStatistics() {

        $data = [
            'day' => [],
            'month' => [],
            'year' => []
        ];

        for ($i = 9; $i >= 0; $i--) {
            $from = Carbon::now()->subDays($i)->startOfDay();
            $to = Carbon::now()->subDays($i)->endOfDay();
            $data['day'][] = $this->fetchStatistics($from, $to);
        }

        $now = Carbon::now();

        for ($i = 9; $i >= 0; $i--) {
            $date = $now->copy()->subMonthsNoOverflow($i);

            $from = $date->copy()->startOfMonth();
            $to = $date->copy()->endOfMonth();

            $data['month'][] = $this->fetchStatistics($from, $to);
        }

        for ($i = 9; $i >= 0; $i--) {
            $from = Carbon::now()->subYears($i)->startOfYear();
            $to = Carbon::now()->subYears($i)->endOfYear();
            $data['year'][] = $this->fetchStatistics($from, $to);
        }

        return Response::successResponse(200, 'Sales Statistics', $data);
    }

    private function fetchStatistics($from, $to)
    {
        $stats = \App\Models\Booking::whereBetween('created_at', [$from, $to])
            ->selectRaw('SUM(CASE WHEN status = "issued" THEN total_amount ELSE 0 END) as total_sales, COUNT(*) as total_bookings, SUM(CASE WHEN status = "issued" THEN 1 ELSE 0 END) as total_issued_tickets')
            ->orgFilter()->first();

        return [
            'label' => $from->format('Y-m-d'),
            'total_sales' => number_format($stats->total_sales, 2) ?? 0,
            'total_bookings' => $stats->total_bookings ?? 0,
            'total_issued_tickets' => $stats->total_issued_tickets ?? 0
        ];
    }

    public function travelCalender(Request $request){
        try{
            
            $bookings = \App\Models\Booking::with(['organization', 'passengers'])
                ->where('status', 'issued')
                ->where(function ($subQuery) use ($request) {
                    if(($request->branch_id !== 'null' && $request->filled('branch_id')) && (!is_null($request->agency_id) && $request->filled('agency_id'))){
                        return $subQuery->whereIn('org_id',[$request->branch_id, $request->agency_id]);
                    }

                    if($request->branch_id !== 'null' && $request->filled('branch_id')){
                        $orgIds = \App\Models\Organization::whereParentId($request->branch_id)->pluck('id')->toArray();
                        return $subQuery->whereIn('org_id',[$request->branch_id, ...$orgIds]);
                    }

                    if($request->agency_id !== 'null' && $request->filled('agency_id')){
                        return $subQuery->where('org_id', $request->agency_id);
                    }
                })
                ->orgFilter()
                ->get()
                ->map(function ($booking) {
                    return [
                        'booking_id' => $booking->booking_id,
                        'departure_date_time' => $booking->departure_date_time,
                        'sector' => booking_sectors($booking->booked_child_segements),
                        'provider_name' => $booking->provider_name,
                        'orgName' => $booking->organization?->name ?? $booking->booked_by_user?->name,
                        'passengers' => $booking->passengers->map(function ($passenger) {
                            return [
                                'title'      => $passenger->title,
                                'first_name' => $passenger->first_name,
                                'last_name' => $passenger->last_name,
                                'passenger_type' => $passenger->passenger_type,
                            ];
                        })->toArray(),
                    ];
                });

            return Response::successResponse(200, 'Travel Calender Data', $bookings);
        }   catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }
}
