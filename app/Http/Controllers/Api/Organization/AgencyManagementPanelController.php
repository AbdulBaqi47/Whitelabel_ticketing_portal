<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Response, DB};

class AgencyManagementPanelController extends Controller
{
    public function profileOverview($uuid)
    {
        try {

            $orgId = orgById($uuid);
            $transactions = \App\Models\Transaction::with('transaction_entries')
                ->where(['transaction_show' => true, 'org_id' => $orgId])->get();

            $balance = balance($orgId);
            $valid_temp_limit = valid_temp_limit($orgId);
            $totalWalletAmount = $balance;
            $organization = \App\Models\Organization::find($orgId);
            $permanent_credit_limit = \App\Models\Wallet::where('org_id', $orgId)->first()->per_credit_limit;

            $walletBalanceHistory = $this->getWalletBalanceHistory($orgId, 10);

            return Response::successResponse(200, 'Financial Profile', [
                'stats' => [
                    'financial_profile' => $organization->financial_profile,
                    'wallet_balance'    => round($totalWalletAmount, 2),
                    'receivable_amount' => round(payable_amount($orgId), 2),
                    'temp_credit'       => $valid_temp_limit,
                    'permanent_credit'  => $permanent_credit_limit,
                    'not_invoiced'      => not_invoiced_booking_total($orgId),
                ],
                'wallet_balance_history'=> $walletBalanceHistory,
                'spend_vs_deposits'     => $this->getSpendVsDeposits($orgId, 12),
                'recent_transactions'   => \App\Http\Resources\AgencyTransactionResource::collection($transactions),
            ]);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function depositsHistory($uuid)
    {
        try {

            $orgId = orgById($uuid);

            $deposits = \App\Models\Deposit::with('bank')
                ->where('org_id', $orgId)
                ->latest()
                ->get();

            $dailyTrend = \App\Models\Deposit::select(
                DB::raw("DATE(created_at) as date"),
                DB::raw("SUM(deposit_amount) as amount")
            )
                ->where(['org_id' => $orgId, 'status' => 'accepted'])
                ->groupBy(DB::raw("DATE(created_at)"))
                ->orderBy('date')
                ->get();

            $accepted = $deposits->where('status', 'accepted');
            $pending  = $deposits->where('status', 'pending');

            $depositHistory = $deposits->map(fn($deposit) => [
                'id'        => $deposit->deposit_id,
                'method'    => $deposit->bank?->bank_name ?? 'Bank Transfer',
                'bank'      => $deposit->bank?->bank_name ?? '-',
                'amount'    => (float) $deposit->deposit_amount,
                'status'    => $deposit->status === 'accepted' ? 'approved' : $deposit->status,
                'date'      => $deposit->created_at->format('M d, Y'),
                'reference' => $deposit->deposit_id,
                'note'      => $deposit->rejection_reason ?? '-',
            ]);

            return Response::successResponse(200, 'Deposits Data', [
                'stats' => [
                    'total_deposits'  => $deposits->count(),
                    'approved_amount' => (float) $accepted->sum('deposit_amount'),
                    'pending_amount'  => (float) $pending->sum('deposit_amount'),
                    'this_month'      => (float) $accepted
                        ->filter(fn($d) => $d->created_at->isCurrentMonth())
                        ->sum('deposit_amount'),
                ],
                'daily_trend'    => $dailyTrend,
                'deposits'       => $depositHistory,
            ]);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function loginLogs($uuid)
    {
        try {
            $user_logins = \App\Models\UserLoginLog::with('organization')
                ->where('org_id', orgById($uuid))
                ->latest()
                ->get();

            return Response::successResponse(200, 'User Logins List', $user_logins);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function employeeSubAgent($uuid)
    {
        try {

            $orgId = orgById($uuid);
            $employeeSubAgents = \App\Models\User::select(['name', 'id', 'email', 'status', 'uuid', 'last_login', 'created_at'])->with('roles:name')->withCount('bookings as booking_count')->where(function ($query) {
                $query->whereHas('roles', function ($subQuery) {
                    $subQuery->whereIn('name', ['a-employee', 'sub-agent']);
                });
            })->where('org_id', $orgId)->get();

            return Response::successResponse(200, 'Employee Sub Agents List', $employeeSubAgents);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    private function getWalletBalanceHistory(int $orgId, int $days = 10): array
    {
        $today = \Carbon\Carbon::today();
        $startDate = $today->copy()->subDays($days - 1);

        $query = \App\Models\TransactionEntry::whereHas('transaction', fn($q) => $q->where('org_id', $orgId))
            ->where('created_at', '<', $startDate);
        $runningBalance = $query->sum('credit') - $query->sum('debit');

        $dailyMovements = \App\Models\TransactionEntry::whereHas('transaction', fn($q) => $q->where('org_id', $orgId))
            ->whereBetween('created_at', [$startDate, $today])
            ->selectRaw('DATE(created_at) as date, SUM(credit) as total_credit, SUM(debit) as total_debit')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $history = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateKey = $date->toDateString();

            if (isset($dailyMovements[$dateKey])) {
                $movement = $dailyMovements[$dateKey];
                $runningBalance += ($movement->total_credit - $movement->total_debit);
            }

            $history[] = [
                'date'    => $date->format('M j'),
                'balance' => round($runningBalance, 2),
            ];
        }

        return $history;
    }

    private function getSpendVsDeposits(int $orgId, int $months = 12): array
    {
        $startDate = \Carbon\Carbon::today()->startOfMonth()->subMonths($months - 1);

        $deposits = \App\Models\Deposit::where('org_id', $orgId)
            ->where('status', 'accepted')
            ->where('created_at', '>=', $startDate)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month_key, DATE_FORMAT(created_at, '%b') as month_label, SUM(deposit_amount) as total_deposits")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b')")
            ->orderByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->get()
            ->keyBy('month_key');
        
        $spend = \App\Models\TransactionEntry::whereHas('transaction', function ($q) use ($orgId) {
                $q->where('org_id', $orgId);
            })
            ->where('created_at', '>=', $startDate)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month_key, DATE_FORMAT(created_at, '%b') as month_label, SUM(debit) as total_spend")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b')")
            ->orderByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->get()
            ->keyBy('month_key');
        
        $result = [];
        for ($i = 0; $i < $months; $i++) {
            $date     = $startDate->copy()->addMonths($i);
            $monthKey = $date->format('Y-m');
            $label    = $date->format('M');

            $result[] = [
                'month'    => $label,
                'deposits' => round($deposits[$monthKey]->total_deposits ?? 0, 2),
                'spend'    => round($spend[$monthKey]->total_spend ?? 0, 2),
            ];
        }

        return $result;
    }
}
