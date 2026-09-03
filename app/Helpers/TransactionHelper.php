<?php

if (!function_exists('deposit_approved')) {
    function deposit_approved($deposit)
    {
        $deposit_narration = deposit_narration($deposit->bank);
        $transaction = \App\Models\Transaction::create([
            'transaction_amount' => $deposit->deposit_amount,
            'entry_type' => 'DEPOSIT',
            'transaction_naration' => $deposit_narration,
            'number' => generate_transaction_number($deposit->org_id),
            'user_id' => $deposit->request_id,
            'org_id' => $deposit->org_id
        ]);
        \App\Models\TransactionEntry::create([
            'transaction_id' => $transaction->id,
            'entry_detail' => $deposit_narration,
            'account_id' => agent_account($deposit->org_id),
            'debit' => 0,
            'credit' => $deposit->deposit_amount,
        ]);
    }
}

if (!function_exists('deposit_narration')) {

    function deposit_narration($bank)
    {
        return sprintf(
            'TO: %s %s ONLINE',
            optional($bank)->bank_name ?? 'BANK',
            optional($bank)->account_holder_name ?? ''
        );
    }
}

if (!function_exists('agent_account')) {
    function agent_account($org_id)
    {
        if(is_null($org_id)){
            $accountType = 'HEAD_OFFICE_SALE';
        }elseif(
            \App\Models\Organization::find($org_id)->main_user->roles->first()->name == 'branch'
        ){
            $accountType = 'BRANCH_SALE_ACCOUNT';
        }else{
            $accountType = 'AGENCY_WALLET';
        }

        $account = \App\Models\Account::where(['org_id' => $org_id, 'name' => $accountType])->first();

        if(is_null($account)){
            $account = \App\Models\Account::create([
                'name' => 'AGENCY_WALLET',
                'code' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10)),
                'description' => 'AGENCY WALLET ACCOUNT',
                'org_id'    => $org_id
            ]);
        }

        return $account->id;
    }
}

if (!function_exists('generate_transaction_number')) {
    function generate_transaction_number($org_id)
    {
        $count = \App\Models\Transaction::where('org_id', $org_id)->count() + 1;
        return sprintf('ORG%03d-%03d', $org_id, $count);
    }
}

if (!function_exists('make_booking_payment')) {
    function make_booking_payment($total_amount, $booking, $orgId, $requestBy)
    {
        $transaction_naration = payment_naration($booking);

        $transaction = \App\Models\Transaction::create([
            'transaction_amount'    => $total_amount,
            'entry_type'            => 'SALE',
            'transaction_naration'  => $transaction_naration,
            'number'                => generate_transaction_number($booking->org_id),
            'user_id'               => Id(),
            'org_id'                => $orgId,
            'booking_id'            => $booking->id,
            'request_by'            => $requestBy
        ]);

        \App\Models\TransactionEntry::create([
            'transaction_id'    => $transaction->id,
            'entry_detail'      => $transaction_naration,
            'account_id'        => agent_account($orgId),
            'debit'             => $total_amount,
            'credit'            => 0,
        ]);

        if (in_array(role_name(), ['agency', 'a-employee', 'sub-agent'])) {
            $rawBalanceBefore = balance($orgId);
            $fromTempCredit = max(0, $total_amount - max(0, $rawBalanceBefore));
            consume_temp_credit($orgId, $fromTempCredit);
        }
    }
}

if (!function_exists('payment_naration')) {
    function payment_naration($booking, $type = 'SALE')
    {
        $sector = booking_sectors($booking->booked_child_segements);
        $pnr = $booking->booking_pnr;
        return $type . ": PNR " . $pnr . ' ' . $sector;
    }
}

if (!function_exists('wallet_detail')) {
    function wallet_detail($booking, $orgId)
    {
        $booking_total_amount = booking_total_amount($booking);
        $balance = balance($orgId);

        $wallet = \App\Models\Wallet::where('org_id', $orgId)->first();

        $per_credit_limit = $wallet?->per_credit_limit - payable_amount($orgId);
        $totalWalletAmount = $balance + valid_temp_limit($orgId) + $per_credit_limit;

        $eligibleForPaymentCheck = $totalWalletAmount >= $booking_total_amount;

        $walletData = [
            'booking_total_amount' => $booking_total_amount,
            'balance'              => round($totalWalletAmount, 2),
            'eligible_for_payment' => $eligibleForPaymentCheck,
            'currency'             => 'PKR',
        ];

        if (in_array(role_name(), \App\Models\Role::MANAGMENT_ROLES)) {
            $walletData['organization'] = \App\Models\Organization::find($orgId)?->name ?? '-';
            $walletData['payable_amount'] = round(payable_amount($orgId), 2);
        }

        return $walletData;
    }
}

if (!function_exists('reverse_booking_payment')) {
    function reverse_booking_payment($booking, $transaction){

        $booking_total_amount = booking_total_amount($booking);
        $orgId = $transaction->org_id;
        $transaction_naration = payment_naration($booking, 'RETURN');
        $transaction->transaction_show = false;
        $transaction->save();
        
        $new_transaction = \App\Models\Transaction::create([
            'transaction_amount'    => $booking_total_amount,
            'entry_type'            => 'RETURN',
            'transaction_show'      => false,
            'transaction_naration'  => $transaction_naration,
            'number'                => generate_transaction_number($orgId),
            'user_id'               => Id(),
            'org_id'                => $orgId,
            'booking_id'            => $booking->id,
        ]);

        \App\Models\TransactionEntry::create([
            'transaction_id'    => $new_transaction->id,
            'entry_detail'      => $transaction_naration,
            'account_id'        => agent_account($orgId),
            'debit'             => 0,
            'credit'            => $booking_total_amount,
        ]);

        release_temp_credit($orgId, $booking_total_amount);

        return true;
    }
}

if (!function_exists('process_booking_payment')) {
    function process_booking_payment($booking, $orgId, $requestBy = null)
    {
        $amount = booking_total_amount($booking);

        make_booking_payment($amount, $booking, $orgId, $requestBy);

        $booking->payment_status = true;
        $booking->payment_by     = Id();
        $booking->request_by     = $requestBy;
        $booking->save();
    }
}

if (!function_exists('booking_total_amount')) {

    function booking_total_amount($booking)
    {

        return round(
            (
                $booking->total_amount +
                (!is_null($booking->extra_amount) ? $booking->extra_amount : 0) +
                (!is_null($booking->return_bundle_amount) ? $booking->return_bundle_amount : 0)
            ),
            2
        );
    }
}