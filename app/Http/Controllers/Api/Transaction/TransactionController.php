<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgencyTransactionResource;
use App\Http\Resources\ManagementTransactionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function agencyTransactionlist(Request $request, $agencyId = null)
    {

        try {

            $orgId = org_id();
            $agencyProfile = null;

            if (!is_null($agencyId)) {

                $organization = \App\Models\Organization::where('uuid', $agencyId)->with('main_user')->first();
                $orgId = $organization->id;

                $agencyProfile = [
                    'name'          => $organization->main_user?->name,
                    'email'         => $organization->main_user?->email,
                    'phone_number'  => $organization->main_user?->phone_number,
                ];
            }

            $transactions = \App\Models\Transaction::with('transaction_entries')->where(['transaction_show' => true, 'org_id' => $orgId])
                ->when(
                    (($request->filled('from') && $request->filled('to')) && ($request->from != 'null' && $request->to != 'null')),
                    function ($subQuery) use ($request) {

                        $from = \Carbon\Carbon::parse($request->from)->startOfDay();
                        $to   = \Carbon\Carbon::parse($request->to)->endOfDay();

                        return $subQuery->whereBetween('created_at', [$from, $to]);
                    }
                )->when(role_name() == 'sub-agent', function ($subQuery) {
                    return $subQuery->where('user_id', Id());
                })->get();

            return Response::successResponse(200, 'Transaction List', [
                'agency'       => $agencyProfile,
                'transactions' => AgencyTransactionResource::collection($transactions),
            ]);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function bookingTransactionById($bookingId)
    {
        try {

            $booking = $this->BookingId($bookingId);

            if (!$booking) {
                return Response::errorResponse(404, 'Booking Not Found!');
            }

            $transactions = \App\Models\Transaction::where('booking_id', $booking->id)
                ->leftJoin('users', 'users.id', '=', 'transactions.request_by')
                ->leftJoin('users as usrs', 'usrs.id', '=', 'transactions.user_id')
                ->select(
                    'transactions.number',
                    'transactions.uuid',
                    'transactions.transaction_amount',
                    'transactions.entry_type',
                    'transactions.transaction_naration',
                    'transactions.created_at',
                    'users.name as request_by',
                    'usrs.name as payment_by',
                    DB::raw('transactions.created_at as created_at'),
                )->orderBy('transactions.id', 'DESC')->get();
            return Response::successResponse(200, 'Transaction List!', $transactions);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    private function BookingId($bookingId)
    {
        $booking = \App\Models\Booking::whereBookingId($bookingId)->first();
        return $booking;
    }

    public function financialDetails($bookingId, $agencyId = null)
    {
        try {

            $booking = $this->BookingId($bookingId);
            if (!$booking) {
                return Response::errorResponse(404, 'Booking Not Found!');
            }

            $orgId = null;
            if (in_array(role_name(), \App\Models\Role::MANAGMENT_ROLES) && !is_null($agencyId)) {

                if (!\App\Models\Organization::whereId($agencyId)->exists()) {
                    return Response::errorResponse(404, 'Agency Not Found!');
                }

                $orgId = $agencyId;
            }


            if (in_array(role_name(), \App\Models\Role::AGENCY_ROLES)) {
                $orgId = $booking->org_id;
            }

            if (is_null($orgId)) {
                return Response::errorResponse(404, 'Organization Must Be Valid!');
            }

            return Response::successResponse(200, 'Financial Status', wallet_detail($booking, $orgId));
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function makePayment($bookingId)
    {
        DB::beginTransaction();
        try {

            $booking = $this->BookingId($bookingId);

            if (!$booking) {
                return Response::errorResponse(404, 'Booking Not Found!');
            }

            if (\App\Models\Transaction::where(['entry_type' => 'SALE', 'booking_id' => $booking->id, 'transaction_show' => true])->exists()) {
                return Response::errorResponse(400, 'A payment has already been successfully processed for this booking.');
            }

            $wallet_detail = wallet_detail($booking, $booking->org_id);

            if (!$wallet_detail['eligible_for_payment']) {
                return Response::errorResponse(400, 'Insufficient balance to complete payment. Please top up your wallet or use an alternative payment method.');
            }

            process_booking_payment($booking, $booking->org_id);
            DB::commit();
            return Response::successResponse(200, 'Payment has been successfully processed. You may now proceed with ticket issuance.');
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function managementMakePayment(Request $request, $bookingId)
    {
        DB::beginTransaction();
        try {

            $validateData = $request->validate([
                'issueFor'   => ['required', 'string', 'in:self,others'],
                'agencyId'   => [Rule::requiredIf($request->issueFor === 'others' && empty($request->employeeId))],
                'employeeId' => [Rule::requiredIf($request->issueFor === 'others' && empty($request->agencyId))],
            ]);

            $booking = $this->BookingId($bookingId);

            if (!$booking) {
                return Response::errorResponse(404, 'Booking Not Found!');
            }

            if (\App\Models\Transaction::where(['entry_type' => 'SALE', 'booking_id' => $booking->id, 'transaction_show' => true])->exists()) {
                return Response::errorResponse(400, 'A payment has already been successfully processed for this booking.');
            }

            $orgId     = $validateData['issueFor'] === 'self'
                ? org_id()
                : (!empty($validateData['employeeId']) ? branch_employee_org_id($validateData['employeeId']) : $request->agencyId);

            $requestBy = $validateData['issueFor'] !== 'self'
                ? (!empty($validateData['employeeId'])
                    ? $validateData['employeeId']
                    : \App\Models\User::where('org_id', $request->agencyId)
                    ->whereHas('roles', fn($q) => $q->where('name', 'agency'))
                    ->value('id'))
                : null;

            process_booking_payment($booking, $orgId, $requestBy);
            DB::commit();
            return Response::successResponse(200, 'Payment has been successfully processed. You may now proceed with ticket issuance.');
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function reverseTransaction($bookingId)
    {
        DB::beginTransaction();
        try {

            $booking = $this->BookingId($bookingId);

            if (!$booking) {
                return Response::errorResponse(404, 'Booking Not Found!');
            }

            if ($booking->payment_status === 'issued') {
                return Response::errorResponse(400, 'This booking has already been issued.');
            }

            $transaction = \App\Models\Transaction::where(['booking_id' => $booking->id, 'entry_type' => 'SALE', 'transaction_show' => true])->first();

            if (!$transaction) {
                return Response::errorResponse(404, 'No payment transaction found for this booking.');
            }

            reverse_booking_payment($booking, $transaction);
            $booking->payment_status = false;
            $booking->save();
            DB::commit();
            return Response::successResponse(200, 'Payment has been successfully reversed. You may now proceed with ticket issuance.');
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }
}
