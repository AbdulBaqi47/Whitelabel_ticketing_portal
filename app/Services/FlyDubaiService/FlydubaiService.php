<?php

namespace App\Services\FlyDubaiService;
class FlydubaiService
{
	use \App\Services\FlyDubaiService\Network, \App\Traits\FlyDubaiBooking;
	protected $baseURL = 'https://api.flydubai.com';

	protected $auth;

    public function __construct()
    {
        $connector = \App\Models\Connector::where('type', 'FLYDUBAI_API')->first();
        if(!empty($connector)){
            $this->auth = $connector->getConnectorCreds();
            if (!empty($connector->connector_domain) && filter_var($connector->connector_domain, FILTER_VALIDATE_URL)) {
                $this->baseURL = rtrim($connector->connector_domain, '/');
            } else {
                $this->baseURL = 'https://api.flydubai.com';
            }
        }else{
            $this->auth = null;
        }
    }
    public function getAccessToken(): bool
    {
        if (empty($this->auth) || empty($this->auth['api_key'])) {
            return false;
        }

        try {
            $payload = [
                "client_id" => $this->auth['api_key'],
                "client_secret" => $this->auth['api_secret'],
                "grant_type" => 'password',
                "password" => $this->auth['password'] ?? '',
                "scope" => 'res',
                "username" => $this->auth['username'] ?? '',
            ];
            $response = \Illuminate\Support\Facades\Http::timeout(10)->asForm()->post($this->baseURL.'/res/v3/authenticate', $payload);
            if (!$response->successful()) {
                \Illuminate\Support\Facades\Log::warning('FlyDubai authentication failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $data = $response->json();
            if (empty($data['access_token'])) {
                \Illuminate\Support\Facades\Log::warning('FlyDubai response missing access_token', ['data' => $data]);
                return false;
            }

            \App\Models\AccessToken::updateOrCreate(['type' => 'jwt'], [
                'type' => 'jwt',
                'token' => $data['access_token'],
                'expiry_date' => date('Y-m-d H:i:s', time() + ($data['expires_in'] ?? 3600))
            ]);
            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('FlyDubai getAccessToken exception: ' . $e->getMessage());
            return false;
        }
    }

	public function searchFlights($request)
	{
		if (!$this->getAccessToken()) {
            return false;
        }

        try {
            $payload = (new \App\Services\FlyDubaiService\MakeRequest())->makeSearchFlightRequest($request, $this->auth);
            $url = $this->baseURL . '/res/v3/pricing/flightswithfares';
            $rawResponse = $this->fetch($url, $payload, 'POST');

            if (empty($rawResponse) || !is_array($rawResponse)) {
                return false;
            }

            if(array_key_exists('statusCode', $rawResponse) && $rawResponse['statusCode'] == 403){
                return false;
            }

            if(array_key_exists('statusCode', $rawResponse) && $rawResponse['statusCode'] == 400){
                return false;
            }

            if (!isset($rawResponse['RetrieveFareQuoteDateRangeResponse']['RetrieveFareQuoteDateRangeResult'])) {
                return false;
            }

            $rawResponse = $rawResponse['RetrieveFareQuoteDateRangeResponse']['RetrieveFareQuoteDateRangeResult'];

            if(array_key_exists('SegmentDetails',$rawResponse)){
                if(empty($rawResponse['SegmentDetails']['SegmentDetail'])){
                    return false;
                }
            }

            if(!array_key_exists('ServiceDetails', $rawResponse)){
                return false;
            }
            
            return (new \App\Services\FlyDubaiService\Parser)->searchFlightParser($rawResponse, $request);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('FlyDubai searchFlights error: ' . $e->getMessage());
            return false;
        }
	}

	function createBooking($data, $request) 
	{
		$payload = (new \App\Services\FlyDubaiService\MakeRequest())->makeAddToCartPayload($this->auth,$data);
		\Illuminate\Support\Facades\Log::info(['makeAddToCartPayload',$payload]);
		$add_to_cart_response = $this->fetch($this->baseURL . '/res/v3/order/cart', $payload, 'POST');
		
		\Illuminate\Support\Facades\Log::info(['add_to_cart_response',$add_to_cart_response]);
	
		$makesummary_payload = (new MakeRequest)->makeSummaryPnrPayload($request, $add_to_cart_response, $this->auth, array_key_exists(0,$data));
		\Illuminate\Support\Facades\Log::info(['makesummary_payload',$makesummary_payload]);
		$summaryPNR = $this->fetch($this->baseURL . '/res/v3/cp/summaryPNR?accural=true', $makesummary_payload, 'POST');
		\Illuminate\Support\Facades\Log::info(['summaryPNR',$summaryPNR]);


		$commit_pnr_payload = (new MakeRequest)->makeCommitPnrPayload($this->auth);
		\Illuminate\Support\Facades\Log::info(['commit_pnr_payload',$commit_pnr_payload]);
		$summarypnr_response = $this->fetch($this->baseURL . '/res/v3/cp/commitPNR?accrual=true', $commit_pnr_payload, 'POST');
		\Illuminate\Support\Facades\Log::info(['summarypnr_response',$summarypnr_response]);
		return ['status'=>true, 'data' =>$this->flydubaiBooking($summarypnr_response, $request, $data)];

	}

	function retreivePnr($pnrConfirmationNo) {

		$this->getAccessToken();
		$payload = (new \App\Services\FlyDubaiService\MakeRequest())->makeRetreivePnrPayload($pnrConfirmationNo, $this->auth);
		\Illuminate\Support\Facades\Log::info(['RetrievePNR',$payload]);
		$response = $this->fetch($this->baseURL . '/res/v3/cp/RetrievePNR', $payload, 'POST');
		\Illuminate\Support\Facades\Log::info(['RetrievePNRRes',$payload]);
		return $response;
	}

	function isssueTkt($pnrConfirmationNo)
	{
		
		$rawResponse = $this->retreivePnr($pnrConfirmationNo);

		$makeGetBFeePayload = (new \App\Services\FlyDubaiService\MakeRequest())->makeGetBFeePayload($pnrConfirmationNo, $rawResponse['ReservationBalance']);
		\Illuminate\Support\Facades\Log::info(['makeGetBFeePayload',$makeGetBFeePayload]);
		$makeGetBFeeResponse = $this->fetch($this->baseURL . '/res/v3/payment/fees', $makeGetBFeePayload, 'POST');
		\Illuminate\Support\Facades\Log::info(['makeGetBFeeResponse',$makeGetBFeeResponse]);

		$payment_charges_and_discount = (new \App\Services\FlyDubaiService\MakeRequest())->makeGetPaymentChargesAndDiscountDetailPayload($pnrConfirmationNo, $rawResponse['ReservationBalance'],$this->auth);
		\Illuminate\Support\Facades\Log::info(['payment_charges_and_discount',$payment_charges_and_discount]);
		$payment_charges_and_discount_response = $this->fetch($this->baseURL . '/res/v3/order/payment/GetPaymentChargesAndDiscountDetail', $payment_charges_and_discount, 'POST');
		\Illuminate\Support\Facades\Log::info(['payment_charges_and_discount_response',$payment_charges_and_discount_response]);

		$reservationBalanceWithPaymentFee = $payment_charges_and_discount_response['BinBasedDiscountAndPaymentFeeResponse'][0]['ReservationBalance'];
	
		$process_fop_request = (new \App\Services\FlyDubaiService\MakeRequest())->makeProcessFOPPayload($pnrConfirmationNo, $reservationBalanceWithPaymentFee, $this->auth);
		\Illuminate\Support\Facades\Log::info(['process_fop_request',$process_fop_request]);

		$processFOP = $this->fetch($this->baseURL . '/res/v3/order/payment/processFOP', $process_fop_request, 'POST');
		\Illuminate\Support\Facades\Log::info(['processFOPRes',$processFOP]);

		return ['status'=> true, 'flydubai_bsp_fee'=> $makeGetBFeeResponse['feeDetails']['feeAmount'],'reservation_balance' =>  $makeGetBFeeResponse['amount'],'issued_amount_tkt_time' => $reservationBalanceWithPaymentFee];
	}

	function cancelPNR($pnrConfirmationNo, $type = 'cancel') {

		$this->getAccessToken();
		$payload = (new MakeRequest)->makeCancelPnrPayload($pnrConfirmationNo, $type);
		\Illuminate\Support\Facades\Log::info(['cancelPNR',$payload]);
		$cancel_response = $this->fetch($this->baseURL . '/res/v3/order/cancelPNR', $payload, 'POST');
		\Illuminate\Support\Facades\Log::info(['cancel_response',$cancel_response]);
		$makeCommitPnrSaveResPayload = (new MakeRequest)->makeCommitPnrSaveResPayload($pnrConfirmationNo,$this->auth);
		\Illuminate\Support\Facades\Log::info(['cancel_response',$cancel_response]);
		$commit_pnr_response = $this->fetch($this->baseURL . '/res/v3/cp/commitPNR?accrual=true', $makeCommitPnrSaveResPayload, 'POST');
		\Illuminate\Support\Facades\Log::info(['commit_pnr_response',$commit_pnr_response]);
		// if ($type == 'cancel' && $retreivePnrRes['ReservationFulfillmentRequiredByGMT'] == null) { // refund
		// 	if (request()->has('refundConfimation')) {
		// 		$refundAmount = $rawResponse['ReservationBalance'];
		// 		$this->setData($refundAmount);
		// 		return $this->response();
		// 	}
		// }		
		return $commit_pnr_response;
	}
}
