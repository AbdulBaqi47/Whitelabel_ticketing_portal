<?php

use Illuminate\Support\Facades\DB;

if (!function_exists('balance')) {
    function balance($orgId)
    {
        $balance = \App\Models\TransactionEntry::select(
            \Illuminate\Support\Facades\DB::raw("COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS balance")
        )
            ->whereHas('transaction', fn($q) => $q->where(['org_id' => $orgId, 'transaction_show' => true]))
            ->value('balance') ?? 0;

        return $balance < 0 ? 0 : $balance;
    }
}

if (!function_exists('valid_temp_limit')) {
    function valid_temp_limit($orgId) {
        $today = \Carbon\Carbon::today();

        $wallet = \App\Models\Wallet::where('org_id', $orgId)->first();

        if (!$wallet) {
            return 0;
        }

         return \App\Models\TemporayCreditLimit::select(
            \Illuminate\Support\Facades\DB::raw("COALESCE(SUM(limit_amount),0) - COALESCE(SUM(used_amount),0) AS remaining_balance")
        )->where(['wallet_id' => $wallet->id, 'instant_stop_limit' => false])
            ->where('end_date', '>=', $today)
            ->whereColumn('used_amount', '<', 'limit_amount')
            ->value('remaining_balance');
    }
}

if (!function_exists('active_temp_limits')) {
    function active_temp_limits($orgId) {
        $today = \Carbon\Carbon::today();

        $wallet = \App\Models\Wallet::where('org_id', $orgId)->first();

        if (!$wallet) {
            return collect();
        }

        return \App\Models\TemporayCreditLimit::select(['limit_amount', 'used_amount', 'start_date', 'end_date'])->where('wallet_id', $wallet->id)
            ->where('instant_stop_limit', false)
            ->where('end_date', '>=', $today)
            // ->whereColumn('used_amount', '<', 'limit_amount')
            ->get();
    }
}

if (!function_exists('not_invoiced_booking_total')) {
    function not_invoiced_booking_total($orgId)
    {
        return round(\App\Models\Booking::select(DB::raw("SUM(COALESCE(extra_amount, 0) + return_bundle_amount + total_amount) as not_invoiced_balance"))
            ->where('org_id', $orgId)
            ->validConfirmed()
            ->whereDoesntHave('transactions', function ($q) {
                $q->where(['entry_type' => 'SALE', 'transaction_show' => true]);
            })
            ->value('not_invoiced_balance'), 2) ?? 0;
    }
}

if (!function_exists("payable_amount")) {
    function payable_amount($org_id)
    {
        $payable_amount = \App\Models\TransactionEntry::select(
            \Illuminate\Support\Facades\DB::raw("
                COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS payable
            ")
        )->whereHas('transaction', function ($subQuery) use ($org_id) {
            $subQuery->where(['org_id' => $org_id, 'transaction_show' => true]);
        })->value('payable') ?? 0;

		return $payable_amount > 0 ? $payable_amount : 0;
    }
}

if (!function_exists('consume_temp_credit')) {
    function consume_temp_credit($orgId, $amount)
    {
        if ($amount <= 0) return;

        $wallet = \App\Models\Wallet::where('org_id', $orgId)->first();

        if (!$wallet) return;

        $limits = \App\Models\TemporayCreditLimit::where('wallet_id', $wallet->id)
            ->where('instant_stop_limit', false)
            ->where('end_date', '>=', \Carbon\Carbon::today())
            ->whereColumn('used_amount', '<', 'limit_amount')
            ->orderBy('end_date')
            ->get();

        $remaining = $amount;

        foreach ($limits as $limit) {
            if ($remaining <= 0) break;

            $available = $limit->limit_amount - $limit->used_amount;
            $consume   = min($available, $remaining);

            $limit->increment('used_amount', $consume);
            $remaining -= $consume;
        }
    }
}

if (!function_exists('release_temp_credit')) {
    function release_temp_credit($orgId, $amount)
    {
        if ($amount <= 0) return;

        $wallet = \App\Models\Wallet::where('org_id', $orgId)->first();

        if (!$wallet) return;

        $limits = \App\Models\TemporayCreditLimit::where('wallet_id', $wallet->id)
            ->where('used_amount', '>', 0)
            ->orderByDesc('end_date')
            ->get();

        $remaining = $amount;

        foreach ($limits as $limit) {
            if ($remaining <= 0) break;

            $release   = min($limit->used_amount, $remaining);
            $limit->decrement('used_amount', $release);
            $remaining -= $release;
        }
    }
}