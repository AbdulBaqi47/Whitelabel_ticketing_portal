<?php

if (!function_exists('is_head_office')) {
	function is_head_office()
	{
		if (role_name() == \App\Models\Role::HEADOFFICE) {
			return true;
		} else {
			return false;
		}
	}
}

if (!function_exists('is_head_office_emp')) {
	function is_head_office_emp()
	{
		if (role_name() == 'h-employee') {
			return true;
		} else {
			return false;
		}
	}
}

if (!function_exists('is_branch')) {
	function is_branch()
	{
		if (role_name() == 'branch') {
			return true;
		} else {
			return false;
		}
	}
}

if (!function_exists('is_branch_emp')) {
	function is_branch_emp()
	{
		if (role_name() == 'b-employee') {
			return true;
		} else {
			return false;
		}
	}
}

if (!function_exists('parent_id')) {
	function parent_id()
	{
		$parent_id = 0;
		$user = \Illuminate\Support\Facades\Auth::user();
		if (!empty($user->parent_id)) {
			$parent_id = $user->parent->id;
		} else {
			$parent_id = $user->parent_id;
		}
		return $parent_id;
	}
}

if (!function_exists('passenger_type')) {
	function passenger_type()
	{
		return [
			'adult_count' => 'ADLT',
			'child_count' => 'CHLD',
			'infant_count' => 'INFT',
		];
	}
}

if (!function_exists('arrayConversion')) {
	function arrayConversion($obj)
	{
		if (!array_key_exists("0", $obj)) {
			$obj[0] = $obj;
			foreach ($obj as $k => $kVal) {
				if ($k != 0) {
					unset($obj[$k]);
				}
			}
		}
		return $obj;
	}
}

if (!function_exists('hitit_trip_type')) {
	function hitit_trip_type()
	{
		return [
			'ONEWAY' => 'ONE_WAY',
			'RETURN' => 'ROUND_TRIP',
			'MULTICITY' => 'MULTI_DIRECTIONAL'
		];
	}
}

if (!function_exists('paxType')) {
	function paxType()
	{
		return [
			'ADLT' => 'Adult',
			'CHLD' => 'Child',
			'INFT' => 'Infant',
			'ADT' => 'Adult',
			'CHD' => 'Child',
			'INF' => 'Infant'
		];
	}
}

if (!function_exists('paxTypeAirblue')) {
	function paxTypeAirblue()
	{
		return [
			'ADT' => 'ADT',
			'CNN' => 'CHD',
			'INF' => 'INF'
		];
	}
}

if (!function_exists('d_type_airblue')) {
	function d_type_airblue()
	{
		return [
			'P' => 2,
			'I' => 5
		];
	}
}

if (!function_exists('passenger_title_airblue')) {
	function passenger_title_airblue($passenger)
	{

		$passenger_title = "";
		if ($passenger['passenger_type'] == 'CNN') {
			if ($passenger['title'] == 'MS') {
				$passenger_title = 'MISS';
			}
		} else {
			$passenger_title = $passenger['title'];
		}

		return $passenger_title;
	}
}

if (!function_exists('numberFormat')) {
	function numberFormat($value)
	{
		return number_format($value, 2, '.', ',');
	}
}

if (!function_exists('Id')) {
	function Id()
	{
		return \Illuminate\Support\Facades\Auth::user()->id;
	}
}

if (!function_exists('org_id')) {
	function org_id()
	{
		return \Illuminate\Support\Facades\Auth::user()->org_id;
	}
}

if (!function_exists('random_id')) {
	function random_id($prefix)
	{
		$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		return $prefix . substr(str_shuffle($characters), 0, 9);
	}
}

if (!function_exists('SabreCabin')) {
	function SabreCabin()
	{
		return [
			'ECONOMY' => 'Y',
			'PREMIUMECONOMY' => 'S',
			'BUSINESS' => 'C',
			'PREMIUMBUSINESS' => 'J',
			'FIRSTCLASS' => 'F',
			'PREMIUMFIRST' => 'P',
		];
	}
}

if (!function_exists('getCheapestFareOption')) {
	function getCheapestFareOption(array $fareOptions)
	{
		if (empty($fareOptions)) {
			return null;
		}

		$cheapestFare = null;
		$minPrice = PHP_FLOAT_MAX;

		foreach ($fareOptions as $fare) {
			$grossAmount = (float)str_replace(',', '', $fare['price']['gross_amount']);

			if ($grossAmount < $minPrice) {
				$minPrice = $grossAmount;
				$cheapestFare = $grossAmount;
			}
		}

		return $cheapestFare;
	}
}

if (!function_exists('removeDuplicateBaggageItems')) {
	function removeDuplicateBaggageItems(array $baggageInformation, $is_ndc = false): array
	{
		$uniqueItems = [];
		$hasCarryOn = false; // track if 'B' is present

		foreach ($baggageInformation as $item) {

			if ($item['provisionType'] === 'A') {
				$item['weight'] = in_array($item['airlineCode'], ['SV', 'KU', 'CZ'])
					? '23'
					: ($item['airlineCode'] === 'X1'
						? '20'
						: (!isset($item['weight']) ? '0' : $item['weight'])
					);
			} elseif ($item['provisionType'] === 'B') {
				$item['weight'] = '7';
				$hasCarryOn = true;
			}

			$item['unit'] = 'KG';

			$hash = md5(json_encode($item));
			$uniqueItems[$hash] = $item;
		}

		/**
		 * ---------------------------------------------------
		 * Add Carry-On baggage manually for NDC fares
		 * ---------------------------------------------------
		 */
		if ($is_ndc && !$hasCarryOn) {

			$firstItem = reset($uniqueItems);

			$carryOn = [
				"id"           => rand(1000, 9999),
				"pieceCount"   => 1,
				"weight"       => '7',
				"unit"         => "KG",
				"provisionType" => "B",
				"provision"    => "Carry-on baggage allowance",
				"airlineCode"  => $firstItem['airlineCode'] ?? '',
			];

			$hash = md5(json_encode($carryOn));
			$uniqueItems[$hash] = $carryOn;
		}

		return array_values($uniqueItems);
	}
}

if (!function_exists('get_data')) {
	function get_data($id, $prefix)
	{
		return \Illuminate\Support\Facades\Cache::get($prefix . ':' . $id);
	}
}

if (!function_exists('set_data')) {
	function set_data($id, $prefix, $expiry_time, $data)
	{
		\Illuminate\Support\Facades\Cache::put($prefix . ':' . $id, $data, $expiry_time);
	}
}

if (!function_exists('getPassengerCount')) {
	function getPassengerCount($passengers)
	{
		return collect($passengers)->reject(function ($passenger) {
			return $passenger['passenger_type'] === 'INF';
		})->count();
	}
}

if (!function_exists('cleanAmount')) {
	function cleanAmount($amount)
	{
		return (float) str_replace(',', '', $amount);
	}
}

if (!function_exists('flightReIndex')) {
	function flightReIndex($flights)
	{
		$new_flights = [];

		foreach ($flights as $value) {
			$new_flights[$value['operatingFlightNumber']] = $value;
			if($value['operatingFlightNumber'] != $value['flightNumber']){
				$new_flights[$value['flightNumber']] = $value;
			}
		}

		return $new_flights;
	}
}

if(!function_exists('sabre_ndc_expiry')){
	function sabre_ndc_expiry(){
		return \Carbon\Carbon::now()->addMinutes(30)->format('Y-m-d H:i:s');
	}
}

if (!function_exists('pnrExpiry')) {
	function pnrExpiry($booking_detail)
	{
			if (array_key_exists('specialServices', $booking_detail)) {
				$expiry_time = null;
				foreach ($booking_detail['specialServices'] as $value) {
					if (in_array($value['code'], ['ADTK', 'OTHS'])) {
						$expiry_time = extractDateTime($value['message']);

						if(is_null($expiry_time) || is_null($expiry_time['expiry_datetime'])){
							return null;
						}
						
						if(in_array($expiry_time['airport_code'],['DDU', 'AAW', 'BHW', 'BNP', 'WGB', 'BHV', 'CJL', 'CHB', 'DBA', 'DEA', 'DSK', 'LYP', 'GWD', 'GIL', 'JAG', 'JIW', 'KHI', 'HDD', 'KDD', 'KBH', 'OHT', 'LHE', 'LRG', 'XJM', 'ISB', 'SDT', 'PEW'])){
							return $expiry_time['expiry_datetime'];
						}
						else{
							$country_iso = get_country_iso($expiry_time['airport_code']);
							if(!is_null($country_iso)){
								$sourceTz = timezone_from_country($country_iso);
								$localDt = \Carbon\Carbon::parse(
									$expiry_time['expiry_datetime'],
									$sourceTz
								);
								return $localDt->setTimezone('Asia/Karachi')->format('Y-m-d H:i:s');
							}else{
								return $expiry_time['expiry_datetime'];
							}
						}
					}
				}
				return $expiry_time;
			}
		return null;
	}
}

if (!function_exists('extractDateTime')) {
    function extractDateTime($message)
    {
        if (preg_match('/BY\s+(\d{2}[A-Z]{3}\d{2})\/(\d{4})/', $message, $matches)) {

            $dateTime = \Carbon\Carbon::createFromFormat(
                'dMYyHi',
                $matches[1] . $matches[2]
            );

            return $dateTime ? [
                'expiry_datetime' => $dateTime->format('Y-m-d H:i:s'),
                'airport_code'    => null
            ] : null;
        }

        if (preg_match('/BY\s+(\d{2}[A-Z]{3}\d{2})\s+(\d{4})([A-Z]{3})/', $message, $matches)) {

             $dateTime = \Carbon\Carbon::parse(
				$matches[1] . ' ' . $matches[2]
			);

			return [
				'expiry_datetime' => $dateTime->format('Y-m-d H:i:s'),
				'airport_code'    => $matches[3]
			];
        }

        if (preg_match('/BY\s+(\d{2}[A-Z]{3})\s+(\d{4})\s+([A-Z]{3})/', $message, $matches)) {

            $year = date('Y');

            $dateTime = \Carbon\Carbon::createFromFormat(
                'dMYHi',
                $matches[1] . $year . $matches[2]
            );

            return $dateTime ? [
                'expiry_datetime' => $dateTime->format('Y-m-d H:i:s'),
                'airport_code'    => $matches[3]
            ] : null;
        }

        if (preg_match('/BY\s+(\d{2}[A-Z]{3})\s+(\d{4})/', $message, $matches)) {

            $year = date('Y');

            $dateTime = \Carbon\Carbon::createFromFormat(
                'dMYHi',
                $matches[1] . $year . $matches[2]
            );

            return $dateTime ? [
                'expiry_datetime' => $dateTime->format('Y-m-d H:i:s'),
                'airport_code'    => null
            ] : null;
        }

		if (preg_match('/BY\s+(\d{2}[A-Z]{3})\s+(\d{2})\s+(\d{2})\s+([A-Z]{3})/', $message, $matches)) {

			$year = date('Y');

			$dateString = $matches[1];
			$hour       = $matches[2];
			$minute     = $matches[3];
			$airport    = $matches[4];

			$dateString = substr($dateString, 0, 2) .
						ucfirst(strtolower(substr($dateString, 2, 3)));

			$dateTime = \Carbon\Carbon::parse(
				"$dateString $year $hour:$minute"
			);

			return [
				'expiry_datetime' => $dateTime->format('Y-m-d H:i:s'),
				'airport_code'    => $airport
			];
		}

		if (preg_match('/BY\s+([A-Z]{3})(\d{2}[A-Z]{3}\d{2})\/(\d{4})/', $message, $matches)) {

			$airport   = $matches[1];
			$datePart  = $matches[2];
			$timePart  = $matches[3];

			$dateTime = \Carbon\Carbon::parse(
				$datePart . ' ' . $timePart
			);
			
			return $dateTime ? [
				'expiry_datetime' => $dateTime->format('Y-m-d H:i:s'),
				'airport_code'    => $airport
			] : null;
		}
        return null;
    }
}

if (!function_exists('remove_last_word')) {
	function remove_last_word($name)
	{
		return preg_replace('/\s+\S+$/', '', $name);
	}
}

if (!function_exists('booking_pdf')) {
	function booking_pdf(string $booking_id, ?string $download_type = "booking_download")
	{
		$booking = \App\Models\Booking::whereBookingId($booking_id)
			->with([
				'booked_segments.childs',
				'booked_segments.operating_airline:name,thumbnail,iata_code',
				'booked_segments.marketing_airline:name,thumbnail,iata_code',
				'booked_segments.d_airport:name,iata_code,municipality,country',
				'booked_segments.a_airport:name,iata_code,municipality,country',
				'passengers:id,title,booking_id,passenger_type,first_name,last_name,d_type,d_number,d_expiry,date_of_birth,ticket_number',
				'airline:id,name,iata_code'
			])
			->first();

		$bookingArray = $booking->toArray();

		$pdf = \Spatie\LaravelPdf\Facades\Pdf::view('pdf.booking', [
			'booking' => $bookingArray,
			'download_type' => $download_type
		])->withBrowsershot(function (\Spatie\Browsershot\Browsershot $browsershot) {
			$browsershot->setChromePath('/usr/bin/google-chrome')
				->addChromiumArguments([
					'--no-sandbox',
					'--disable-setuid-sandbox',
					'--disable-gpu',
				]);
		});

		$name = $booking->booking_pnr . '-E-' . ($download_type == 'booking_download' ? 'booking' : 'ticket') . '.pdf';
		$pdfFilePath = storage_path('app/public/booking-pdf/' . $name);

		if (MAKE_DIR($pdfFilePath)) {
			$pdf->save($pdfFilePath);
			return basename($pdfFilePath);
		}
	}
}

if (!function_exists('set_inf_gender')) {
	function set_inf_gender()
	{
		return [
			'M' => 'MI',
			'F' => 'FI'
		];
	}
}

if (!function_exists('_employee')) {
	function _employee()
	{
		if (is_head_office()) {
			return 'h-employee';
		} else if (is_branch()) {
			return 'b-employee';
		} else if (is_agency()) {
			return 'a-employee';
		}
	}
}

if (!function_exists('is_agency')) {
	function is_agency()
	{
		if (role_name() == 'agency') {
			return true;
		} else {
			return false;
		}
	}
}

if (!function_exists('format_name_from_raw')) {
	function format_name_from_raw($input)
	{
		$formatted = preg_replace('/[^A-Za-z ]+/', '', $input);
		return trim(preg_replace('/\s+/', ' ', $formatted));
	}
}

if (!function_exists('pnr_contact')) {
	function pnr_contact()
	{
		$auth_user = \Illuminate\Support\Facades\Auth::user();
		$crated_by = strtoupper(format_name_from_raw($auth_user->name));
		if (is_head_office() || is_branch() || is_agency()) {
			return $crated_by;
		} else {
			return strtoupper(format_name_from_raw($auth_user?->organization->name)) . ' REF ' . $crated_by;
		}
	}
}

if (!function_exists('booking_recieved_from')) {
	function booking_recieved_from()
	{
		$role = role_name();
		$auth_user = \Illuminate\Support\Facades\Auth::user();
		$crated_by = strtoupper(format_name_from_raw($auth_user->name));
		if (in_array($role, \App\Models\Role::MANAGMENT_ROLES)) {
			if (request()->has('created_for') && request()->filled('created_for')) {
				return request()->created_for;
			} else {
				$auth_user = \Illuminate\Support\Facades\Auth::user();
				$crated_by = strtoupper(format_name_from_raw($auth_user->name));
				return $crated_by;
			}
		} else {
			if (is_agency()) {
				return $crated_by;
			} else {
				return strtoupper(format_name_from_raw($auth_user?->organization->name)) . ' - ' . $crated_by;
			}
		}
	}
}

if (!function_exists('passenger_title')) {
	function passenger_title()
	{
		return ['Mr' => 'MR', 'Mrs' => 'MRS', 'Ms' => 'MS', 'Miss' => 'MISS', 'MSTR' => 'MSTR', 'INF' => 'INF'];
	}
}

if (!function_exists('extra_commision')) {
	function extra_commision()
	{
		$auth_user = \Illuminate\Support\Facades\Auth::user();
		$exta_commision = 0;
		if (in_array(role_name(), \App\Models\Role::AGENCY_ROLES)) {
			$org_setting = \App\Models\UserSetting::where('org_id', $auth_user->org_id)->first();
			if (!is_null($org_setting) && !is_null($org_setting?->extra_commision)) {
				$exta_commision = $org_setting?->extra_commision;
			}
		}

		return $exta_commision;
	}
}

if (!function_exists('is_tkt_voidable')) {
	function is_tkt_voidable($issued_at)
	{
		$issue_date = \Carbon\Carbon::parse($issued_at);
		$end_issue_date = $issue_date->copy()->endOfDay();
		$now = \Carbon\Carbon::now();

		$canBeVoided = $now->lte($end_issue_date);

		return $canBeVoided;
	}
}

if (!function_exists('is_tkt_voidable_flydubai')) {
	function is_tkt_voidable_flydubai($issued_at)
	{
		$issue_date = \Carbon\Carbon::parse($issued_at);
		$now = \Carbon\Carbon::now();

		$within30Minutes = $now->diffInMinutes($issue_date) <= 30;

		return $within30Minutes;
	}
}

if (!function_exists('role_name')) {
	function role_name()
	{
		$current_user = \Illuminate\Support\Facades\Auth::user();
		return $current_user->roles->first()->name;
	}
}

if (!function_exists('is_domastic')) {
	function is_domastic($origin, $destination)
	{
		return \App\Models\Airport::query()
			->selectRaw('COUNT(DISTINCT country) as country_count')
			->whereIn('iata_code', [$origin, $destination])
			->value('country_count') == 1 ? true : false;
	}
}

if (!function_exists('fetch_airport')) {
	function fetch_airport($iata_code)
	{
		if (!$iata_code) {
			return [];
		}

		$airport = \App\Models\Airport::where('iata_code', $iata_code)
			->select(['name', 'municipality', 'iata_code', 'country'])
			->first();

		return $airport ? $airport->toArray() : [];
	}
}

if (!function_exists('fetch_airline')) {
	function fetch_airline($iata_code)
	{
		if (!$iata_code) {
			return [];
		}

		$airline = \App\Models\Airline::where('iata_code', $iata_code)
			->select(['iata_code', 'thumbnail', 'name'])
			->first();

		return $airline ? $airline->toArray() : [];
	}
}

if (!function_exists('amount_cal')) {
	function amount_cal($fare, $promo_type, $promo_value, $carrier)
	{
		$amount = 0;
		switch ($promo_type) {
			case 'amount':
				$amount = 0;
				if (request()->all()['route_type'] == 'RETURN' && in_array($carrier, ['FZ', 'PA', '9P'])) {
					$amount = $promo_value / 2;
				} else {
					$amount = $promo_value;
				}
				break;
			case 'percentage':
				$amount = get_commision_per($promo_value, $fare);
				break;
		}
		return $amount;
	}
}

if (!function_exists('get_commision_per')) {
	function get_commision_per($percentage, $fare)
	{
		return ($percentage * $fare) / 100;
	}
}

if (!function_exists('airline_discount')) {
	function airline_discount($base_fare, $gross_amount, $commission, ?string $carrier = null)
	{
		if (count($commission) > 0) {

			$margin_type = $commission[0]['margin_type'];
			$margin_value = $commission[0]['margin'];
			$is_apply_on_gross = $commission[0]['is_apply_on_gross'];

			$fare_to_use = $is_apply_on_gross ? $gross_amount : $base_fare;
			$discount = amount_cal($fare_to_use, $margin_type, $margin_value, $carrier);

			return $discount;
		}
		return 0;
	}
}

if (!function_exists('flights_combination')) {
	function flights_combination(array $flights, array $journeys): array
	{
		$result = [];
		$flightIndex = 0;

		foreach ($journeys as $journey) {
			$segments = [];

			for ($i = 0; $i < $journey['numberOfFlights']; $i++) {
				if (isset($flights[$flightIndex])) {
					$segments[] = $flights[$flightIndex];
					$flightIndex++;
				}
			}

			$result[] = [
				'sector' => [$journey['firstAirportCode'], $journey['lastAirportCode']],
				'segments' => $segments,
			];
		}

		return $result;
	}
}

if (!function_exists('pax_type_import')) {
	function pax_type_import()
	{
		return [
			'ADT' => 'ADULT',
			'CNN' => 'CHILD',
			'INF' => 'INFANT'
		];
	}
}

if (!function_exists('import_pnr_price')) {
	function import_pnr_price(array $fares, $travelers): array
	{
		$fareBreakdown = [];
		$totalBaseFare = 0;
		$totalTaxes = 0;
		$totalAmount = 0;


		$passengerCounts = [
			'ADULT' => 0,
			'CHILD' => 0,
			'INFANT' => 0,
		];

		foreach ($travelers as $traveler) {
			$type = strtoupper($traveler['type']);
			if (isset($passengerCounts[$type])) {
				$passengerCounts[$type]++;
			}
		}

		foreach ($fares as $fare) {

			$travelers = array_key_exists('travelerIndices', $fare) ? count($fare['travelerIndices']) : 1;
			$type = $fare['pricedTravelerType'];
			$base = (float) $fare['totals']['subtotal'] * $travelers;
			$tax = (float) $fare['totals']['taxes'] * $travelers;
			$total = (float) $fare['totals']['total'] * $travelers;
			$currency = $fare['totals']['currencyCode'];

			$totalBaseFare += $base;
			$totalTaxes += $tax;
			$totalAmount += $total;

			if (!isset($fareBreakdown[$type])) {
				$fareBreakdown[$type] = [
					'quantity' => $passengerCounts[pax_type_import()[$type]],
					'prices' => [
						'base_fare' => number_format($base, 2, '.', ','),
						'tax' => number_format($tax, 2, '.', ','),
						'gross_amount' => number_format($total, 2, '.', ','),
						'currency' => $currency,
						'discount_psf' => "0.00",
						'total_amount' => number_format($total, 2, '.', ','),
					]
				];
			} else {
				$fareBreakdown[$type]['quantity'] += 1;
				$fareBreakdown[$type]['prices']['base_fare'] = number_format(
					(float) str_replace(',', '', $fareBreakdown[$type]['prices']['base_fare']) + $base,
					2,
					'.',
					','
				);
				$fareBreakdown[$type]['prices']['tax'] = number_format(
					(float) str_replace(',', '', $fareBreakdown[$type]['prices']['tax']) + $tax,
					2,
					'.',
					','
				);
				$fareBreakdown[$type]['prices']['gross_amount'] = number_format(
					(float) str_replace(',', '', $fareBreakdown[$type]['prices']['gross_amount']) + $total,
					2,
					'.',
					','
				);
				$fareBreakdown[$type]['prices']['total_amount'] = $fareBreakdown[$type]['prices']['gross_amount'];
			}
		}

		return [
			'base_fare' => number_format($totalBaseFare, 2, '.', ','),
			'tax' => number_format($totalTaxes, 2, '.', ','),
			'gross_amount' => number_format($totalAmount, 2, '.', ','),
			'currency' => 'PKR',
			'discount_psf' => "0.00",
			'total_amount' => number_format($totalAmount, 2, '.', ','),
			'fare_break_down' => $fareBreakdown
		];
	}
}

if (!function_exists('format_baggage_data')) {
	function format_baggage_data(array $baggage): array
	{
		$result = [
			'ADT' => [],
		];
		if (!empty($baggage['checkedBaggageAllowance']['baggagePieces'])) {
			foreach ($baggage['checkedBaggageAllowance']['baggagePieces'] as $index => $item) {
				$result['ADT'][] = [
					'id' => 30 + $index,
					'provision' => 'Checked baggage allowance',
					'pieceCount' => $item['numberOfPieces'] ?? 0,
					'airlineCode' => 'SV',
					'provisionType' => 'A',
				];
			}
		}

		if (!empty($baggage['cabinBaggageAllowance']['baggagePieces'])) {
			foreach ($baggage['cabinBaggageAllowance']['baggagePieces'] as $index => $item) {
				$result['ADT'][] = [
					'id' => 10 + $index,
					'provision' => 'Carry-on baggage allowance',
					'pieceCount' => $item['numberOfPieces'] ?? 0,
					'airlineCode' => 'SV',
					'provisionType' => 'B',
				];
			}
		}

		return $result;
	}
}

if (!function_exists('manage_request')) {
	function manage_request($request, $required)
	{

		if (!is_array($request)) {
			$request = $request->all();
		}
		if ($request['route_type'] != 'MULTICITY') {
			return $request[$required];
		}
		return $request['legs'][0][$required];
	}
}

if (!function_exists('make_chunks')) {
	function make_chunks($segments_data)
	{
		$chunks = [];
		$childrenByParentId = [];
		$segmentsById = [];

		foreach ($segments_data as $segment) {
			$segmentsById[$segment['id']] = $segment;
			if (!is_null($segment['parent_id'])) {
				$childrenByParentId[$segment['parent_id']][] = $segment;
			}
		}

		foreach ($segments_data as $segment) {
			if (is_null($segment['parent_id'])) {
				$chunk = [$segment];

				if (isset($childrenByParentId[$segment['id']])) {
					foreach ($childrenByParentId[$segment['id']] as $childSegment) {
						$chunk[] = $childSegment;
					}
				}
				$chunks[] = $chunk;
			}
		}

		return $chunks;
	}
}

if (!function_exists('identity_document_key_pair')) {
	function identity_document_key_pair($air_travelers)
	{
		$keyPair = [];
		foreach ($air_travelers as $traveler) {
			if (array_key_exists('identityDocuments', $traveler)) {
				foreach (arrayConversion($traveler['identityDocuments']) as $identity_document) {
					$keyPair[$identity_document['givenName']] = $identity_document;
				}
			}
		}
		return $keyPair;
	}
}

if (!function_exists('sabre_import_passengers')) {

	function sabre_import_passengers($air_travelers, $air_ticketing)
	{
		$identity_document = identity_document_key_pair($air_travelers);
		$passengers = [];
		foreach (arrayConversion($air_travelers) as $key => $air_traveler) {
			$document = $identity_document[$air_traveler['givenName']];

			$ticket_number = null;
			if ($air_ticketing != null && $air_ticketing[$key]['ticketStatusName'] == 'Issued') {
				$ticket_number = $air_ticketing[$key]['number'];
			}

			$fullName = $air_traveler['givenName'];
			$nameParts = explode(' ', $fullName);
			$title = array_pop($nameParts);
			$firstName = implode(' ', $nameParts);

			$passengers[] = [
				'title'          => $title,
				'passenger_type' => $air_traveler['passengerCode'],
				'first_name'     => $firstName,
				'last_name'      => $air_traveler['surname'],
				'd_type'         => $document['documentType'] == 'PASSPORT' ? 'P' : 'I',
				'd_number'       => $document['documentNumber'],
				'd_expiry'       => $document['expiryDate'],
				'date_of_birth'  => $document['birthDate'],
				'ticket_number'  => $ticket_number,
			];
		}

		return $passengers;
	}
}

if (!function_exists('sabre_baggage')) {
	function sabre_baggage(array $travelers, array $fare_offer): array
	{
		$baggageData = [];
		foreach ($travelers as $traveler) {
			$type = $traveler['passenger_type'];
			$baggage = baggage_separation($fare_offer, $type);

			if (!isset($baggageData[$type])) {
				$baggageData[$type] = [
					[
						'unit'         => 'kg',
						'weight'       => $baggage['checked_weight'],
						'pieceCount'   => $baggage['checked_pieces'],
						'provision'    => 'Checked baggage allowance',
						'provisionType' => 'A',
					],
					[
						'unit'         => 'kg',
						'weight'       => $baggage['carry_weight'],
						'pieceCount'   => $baggage['carry_pieces'],
						'provision'    => 'Carry-on baggage allowance',
						'provisionType' => 'B',
					]
				];
			}
		}

		return $baggageData;
	}
}

if (!function_exists('baggage_separation')) {
	function baggage_separation(array $fare_offer, string $traveler_type): array
	{
		$baggage = $fare_offer[0] ?? $fare_offer;
		if ($traveler_type === 'INF') {
			$baggage = $fare_offer[1] ?? $fare_offer[0] ?? $fare_offer;
		}

		$cabin  = $baggage['cabinBaggageAllowance']  ?? [];
		$checked = $baggage['checkedBaggageAllowance'] ?? [];

		return [
			'carry_pieces'   => $cabin['maximumPieces']
				?? ($cabin['baggagePieces'][0]['numberOfPieces'] ?? 1),
			'carry_weight'   => $cabin['totalWeightInKilograms']
				?? ($cabin['baggagePieces'][0]['maximumWeightInKilograms'] ?? 7),
			'checked_pieces' => $checked['baggagePieces'][0]['numberOfPieces']
				?? ($checked['maximumPieces'] ?? 1),
			'checked_weight' => $checked['totalWeightInKilograms']
				?? ($checked['baggagePieces'][0]['maximumWeightInKilograms'] ?? 0),
		];
	}
}

if (!function_exists('time_calculation')) {

	function time_calculation($dep, $arr)
	{

		$departure = \Carbon\Carbon::parse($dep);
		$arrival = \Carbon\Carbon::parse($arr);

		return abs($arrival->diffInMinutes($departure));
	}
}

if (!function_exists('ndc_booking_data_collector')) {
	function ndc_booking_data_collector($pricingInformation)
	{
		$offerItemId = [];
		if (array_key_exists('offerItemId', $pricingInformation['fare'])) {
			$offerItemId[] = $pricingInformation['fare']['offerItemId'];
		} else {
			foreach (arrayConversion($pricingInformation['fare']['passengerInfoList']) as $key => $passengerInfo) {
				$offerItemId[] = $passengerInfo['passengerInfo']['offerItemId'];
			}
		}
		return $offerItemId;
	}
}

if (!function_exists('preferences')) {
	function preferences($user)
	{

		$role 		= $user->roles->first()->name;
		$brandName 	= "";
		$logo 	 	= "";

		if (in_array($role, \App\Models\Role::HEAD_ROLES)) {
			$brandName 	= 'WhiteLabel';
			$logo	 	= null;
		} else {
			$brandName 	= $user->organization->name;
			$logo	 	= $user->organization->logo;
		}

		$output = [
			'brandName' => $brandName,
			'logo' 		=> $logo
		];

		return $output;
	}
}

if (!function_exists('offer_item')) {
	function offer_item($offer)
	{

		$flightOffer = [
			'offerId' => $offer['id'],
			'selectedOfferItems' => [],
		];

		$selectedOfferItems = [];
		foreach ($offer['offerItems'] as $offerItem) {
			$selectedOfferItems[] = $offerItem['id'];
		}
		$flightOffer['selectedOfferItems'] = $selectedOfferItems;
		return $flightOffer;
	}
}

if (!function_exists('booking_passenger_title')) {
	function booking_passenger_title($passenger,)
	{
		$passenger_title = "";
		if ($passenger['passenger_type'] == 'CNN') {
			if ($passenger['title'] == 'MSTR') {
				$passenger_title = 'MSTR';
			} else {
				$passenger_title = 'MISS';
			}
		} else if ($passenger['passenger_type'] == 'INF') {
			$passenger_title = 'INF';
		} else {
			$passenger_title = $passenger['title'];
		}

		return $passenger_title;
	}
}

if (!function_exists('sabre_terminal')) {
	function sabre_terminal($terminal = null, $ndcBookingId = null)
	{
		if (!is_null($ndcBookingId) && !is_null($terminal)) {
			return 'Terminal ' . $terminal;
		}
		return $terminal;
	}
}

if (!function_exists('booked_segment_modify')) {
	function booked_segment_modify(array $segments): array
	{
		$parents = array_filter($segments, fn($s) => empty($s['parent_id']));
		$children = array_filter($segments, fn($s) => !empty($s['parent_id']));

		$parents = array_column(
			array_map(fn($p) => $p + ['childs' => []], $parents),
			null,
			'id'
		);

		foreach ($children as $child) {
			if (isset($parents[$child['parent_id']])) {
				$parents[$child['parent_id']]['childs'][] = $child;
			}
		}

		return array_values($parents);
	}
}
if (!function_exists('meal_helper')) {
	function meal_helper(): array
	{
		return [
			[
				'code' => 'M',
				'description' => 'Meal',
			],
		];
	}
}

if (!function_exists('flight_duration')) {
	function flight_duration(
		string $departureDatetime,
		string $arrivalDatetime,
		string $departureCity,
		string $arrivalCity
	): string {

		$departureTimezone = timezone_from_country(get_country_iso($departureCity));
		$arrivalTimezone   = timezone_from_country(get_country_iso($arrivalCity));

		$departureDatetime = \Carbon\Carbon::parse($departureDatetime)->format('Y-m-d H:i:s');
		$arrivalDatetime   = \Carbon\Carbon::parse($arrivalDatetime)->format('Y-m-d H:i:s');

		$departure = \Carbon\Carbon::parse($departureDatetime, $departureTimezone)->utc();
		$arrival   = \Carbon\Carbon::parse($arrivalDatetime, $arrivalTimezone)->utc();
		$minutes = $departure->diffInMinutes($arrival);

		return $minutes;
	}
}

if (!function_exists('get_country_iso')) {
	function get_country_iso($iata_code)
	{
		return \App\Models\Airport::where('iata_code', $iata_code)
			->value('iso_country');
	}
}

if(!function_exists('timezone_from_country')){
	function timezone_from_country(string $countryCode): string
	{
		$zones = DateTimeZone::listIdentifiers(
			DateTimeZone::PER_COUNTRY,
			strtoupper($countryCode)
		);

		if (!$zones) {
			throw new InvalidArgumentException("No timezone for country {$countryCode}");
		}

		return $zones[0];
	}
}

if (!function_exists('normalize_given_name')) {
	function normalize_given_name($givenName)
	{
		$titles = ['MR', 'MRS', 'MS', 'MISS', 'MSTR', 'INF'];

		$givenName = trim($givenName);
		$lastWord = strtoupper(substr($givenName, strrpos($givenName, ' ') + 1));

		if (in_array($lastWord, $titles)) {
			return trim(substr($givenName, 0, strrpos($givenName, ' ')));
		}
		return $givenName;
	}
}

if (!function_exists('chk_codeshare_not_operating')) {

	function chk_codeshare_not_operating($flight_segments)
	{
		foreach ($flight_segments as $flight_segment) {
			$flightSegment = $flight_segment['flightSegment'];
			if ($flightSegment['airline']['code'] !== 'PK' && !array_key_exists('operatingAirline', $flight_segment)) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('deposit_status')) {
	function deposit_status()
	{
		return [
			'processing' => 'pending',
			'approved'	=> 'accepted',
			'rejected'	=> 'rejected'
		];
	}
}

if (!function_exists('store_notification')) {
	function store_notification($model, $description, $type)
	{
		$model->notifications()->create([
			'id'		=> (string) \Illuminate\Support\Str::uuid(),
			'user_id'	=> Id(),
			'type' 		=> $type,
			'data' 		=> $description
		]);
	}
}

if (!function_exists('booking_by')) {
	function booking_by($booked_by)
	{
		$user = \App\Models\User::find($booked_by);
		$role = $user->roles->first()->name;
		$booking_by_name = "";
		$phone_number 	 = "";
		$logo 			 = "";

		if (in_array($role, [\App\Models\Role::HEADOFFICE, 'h-employee', 'sub-agent'])) {
			$booking_by_name = $user->name;
			$phone_number	 = $user->phone_number;
			$logo			 = asset('storage/logos/software-logo.svg');
		} else {
			$booking_by_name = $user->organization->name;
			$phone_number	 = $user->phone_number;
			$logo			 = $user->organization->logo;
		}

		if ($role == 'sub-agent') {
			$logo = $user->organization?->logo;
		}

		$output = [
			'phone_number' => $phone_number,
			'label' 	   => $booking_by_name,
			'email'		   => $user->email,
			'logo'		   => $logo
		];

		return $output;
	}
}
if (!function_exists('format_phone_number')) {

	function format_phone_number($number)
	{
		// Remove anything that is not a digit
		$number = preg_replace('/\D/', '', $number);

		// Ensure it starts with country code 92
		if (strlen($number) !== 12 || substr($number, 0, 2) !== '92') {
			return $number; // fallback if unexpected format
		}

		$countryCode = substr($number, 0, 2);
		$networkCode = substr($number, 2, 3);
		$subscriber  = substr($number, 5);

		return "+{$countryCode} {$networkCode} {$subscriber}";
	}
}

if (!function_exists('terminalLabel')) {
	function terminalLabel($terminal, $showTerminal)
	{
		if ($showTerminal) {
			return 'Terminal: ' . ($terminal ?: 'N/A');
		}
		return $terminal ?: 'Terminal: N/A';
	}
}

if (!function_exists('pdf_baggage')) {

	function pdf_baggage($baggage, $provider, $sectorIndex = 0)
	{
		if (empty($baggage)) {
			return '<p>No baggage information available</p>';
		}

		$html = '';
		$paxLabels = paxType();

		if (in_array($provider, ['SABRE', 'SABRE_NDC'])) {

			foreach ($paxLabels as $code => $label) {

				if (!isset($baggage[$code])) {
					continue;
				}

				$paxBag = $baggage[$code];

				$checked = collect($paxBag)->firstWhere('provisionType', 'A');
				$cabin   = collect($paxBag)->firstWhere('provisionType', 'B');

				if ($cabin) {
					$piece  = $cabin['pieceCount'] ?? 1;
					$weight = $cabin['weight'] ?? '';
					$unit   = $cabin['unit'] ?? '';

					$html .= "<br>Hand Carry: {$label}, {$piece}x{$weight}{$unit} <br>";
				}

				if ($checked) {
					$html .= "Checked Luggage: {$label}, {$checked['weight']}{$checked['unit']} <br>";
				}
			}

			return $html;
		}

		if (isset($baggage[$sectorIndex])) {
			$sectorBag = $baggage[$sectorIndex];
		} else {
			$sectorBag = $baggage;
		}

		foreach ($paxLabels as $code => $label) {

			if (!isset($sectorBag[$code])) {
				continue;
			}

			$paxBag = $sectorBag[$code];

			$checked = collect($paxBag)->firstWhere('provisionType', 'A');
			$cabin   = collect($paxBag)->firstWhere('provisionType', 'B');

			if ($cabin) {
				$piece  = $cabin['pieceCount'] ?? 1;
				$weight = $cabin['weight'] ?? '';
				$unit   = $cabin['unit'] ?? '';

				$html .= "<br>Hand Carry: {$label}, {$piece}x{$weight}{$unit} <br>";
			}

			if ($checked) {
				$html .= "Checked Luggage: {$label}, {$checked['weight']}{$checked['unit']}";
			}
		}

		return $html;
	}
}

if (!function_exists('percentage_change')) {
	function percentage_change($current, $previous)
	{
		if ($previous == 0) {
			return $current == 0 ? 0 : 100;
		}
		return (($current - $previous) / abs($previous)) * 100;
	}
}

if (!function_exists('stats_data')) {
	function stats_data($from, $to)
	{
		return \App\Models\Booking::whereBetween('created_at', [$from, $to])
			->selectRaw('
				SUM(CASE WHEN status = "issued" THEN total_amount ELSE 0 END) as total_sales,
				COUNT(*) as total_bookings,
				SUM(CASE WHEN status = "issued" THEN 1 ELSE 0 END) as total_issued_tickets,
				SUM(CASE WHEN status = "voided" THEN 1 ELSE 0 END) as total_void_tickets
			')
			->orgFilter()
			->first();
	}
}

if (!function_exists('booking_sectors')) {
	function booking_sectors($booked_segments)
	{
		$segment_chunks = make_chunks($booked_segments);
		$sector = [];
		$parts = [];
		foreach ($segment_chunks as $chunk) {
			if (empty($chunk)) continue;

			$first = reset($chunk);
			$last = end($chunk);

			$sector[] = $first['seg_origin'] . '-' . $last['seg_destination'];
		}
		foreach ($sector as $route) {
			[$from, $to] = explode('-', $route);

			if (empty($parts)) {
				$parts[] = $from;
				$parts[] = $to;
			} else {
				$last = end($parts);

				if ($last === $from) {
					$parts[] = $to;
				} else {
					$parts[] = $from;
					$parts[] = $to;
				}
			}
		}
		return implode('→', $parts);
	}
}

if (!function_exists('booked_by_agency')) {
	function booked_by_agency($booked_by)
	{
		$role = \App\Models\User::find($booked_by)->roles->first()->name;
		if (in_array($role, \App\Models\Role::AGENCY_ROLES)) {
			return true;
		} else {
			return false;
		}
	}
}

if (!function_exists('branch_employee_org_id')) {
	function branch_employee_org_id($employeeId)
	{
		return \App\Models\User::find($employeeId)?->org_id;
	}
}

if (!function_exists('calculate')) {

	function calculate(\Illuminate\Http\Request $request): array
	{
		$from = \Carbon\Carbon::today()->startOfDay();
		$to = \Carbon\Carbon::today()->endOfDay();

		$previousFrom = \Carbon\Carbon::yesterday()->startOfDay();
		$previousTo = \Carbon\Carbon::yesterday()->endOfDay();

		if ($request->filled('from') || $request->filled('to')) {

			$from = \Carbon\Carbon::parse($request->from)->startOfDay();
			$to = \Carbon\Carbon::parse($request->to)->endOfDay();

			$days = (int) round($from->diffInDays($to));

			$previousFrom = $from->copy()->subDays($days)->startOfDay();
			$previousTo = $from->copy()->subDay()->endOfDay();
		}

		return [$from, $to, $previousFrom, $previousTo];
	}
}

if (!function_exists('orgById')) {
	function orgById($orgUuid)
	{
		return \App\Models\Organization::where('uuid', $orgUuid)->first()->id;
	}
}

if (!function_exists('get_device_info')) {
	function get_device_info()
	{
		$agent = new \Jenssegers\Agent\Agent();
		$agent->setUserAgent(request()->header('User-Agent'));

		$browser = $agent->browser();
		$platform = $agent->platform();

		return $browser . ' / ' . $platform;
	}
}
