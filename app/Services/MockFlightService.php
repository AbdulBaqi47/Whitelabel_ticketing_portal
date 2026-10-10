<?php

namespace App\Services;

/**
 * MockFlightService
 *
 * Returns realistic dummy flight data when no real connector is configured or available.
 * Only used as a fallback for demo/UAT. Once a real connector is added this is never called.
 *
 * Output shape matches the SABRE provider format so HittitAndSaberListing renders it correctly.
 */
class MockFlightService
{
    public function searchFlights(array $request): array
    {
        $origin      = strtoupper($request['origin'] ?? 'ISB');
        $destination = strtoupper($request['destination'] ?? 'DXB');
        $depDate     = $request['departure_date'] ?? now()->addDays(7)->format('Y-m-d');
        $adults      = (int) ($request['traveler_count']['adult_count'] ?? 1);
        $children    = (int) ($request['traveler_count']['child_count'] ?? 0);
        $infants     = (int) ($request['traveler_count']['infant_count'] ?? 0);
        $cabin       = $request['cabin_class'] ?? 'ECONOMY';
        $routeType   = $request['route_type'] ?? 'ONEWAY';
        $pax         = max(1, $adults + $children);

        $flights = $this->buildFlights($origin, $destination, $depDate, $pax, $cabin);

        if ($routeType === 'RETURN' && !empty($request['return_date'])) {
            $returnFlights = $this->buildFlights($destination, $origin, $request['return_date'], $pax, $cabin);
            // For return, combine pairs as Sabre would
            $combined = [];
            foreach ($flights as $out) {
                foreach ($returnFlights as $ret) {
                    $combined[] = array_merge($out, [
                        'legs' => array_merge($out['legs'], $ret['legs']),
                    ]);
                }
            }
            return $combined;
        }

        return $flights;
    }

    private function buildFlights(string $origin, string $destination, string $date, int $pax, string $cabin): array
    {
        $airlines = [
            ['code' => 'PK', 'name' => 'Pakistan International Airlines', 'thumbnail' => '/media/icons/pia.svg'],
            ['code' => 'PA', 'name' => 'AirBlue',                         'thumbnail' => '/media/icons/air-blue.svg'],
            ['code' => 'EK', 'name' => 'Emirates',                        'thumbnail' => null],
            ['code' => 'FZ', 'name' => 'FlyDubai',                        'thumbnail' => null],
            ['code' => '9P', 'name' => 'FlyJinnah',                       'thumbnail' => null],
        ];

        $slots = [
            ['dep' => '06:00', 'arr' => '08:30', 'dur' => 150],
            ['dep' => '09:15', 'arr' => '11:45', 'dur' => 150],
            ['dep' => '13:00', 'arr' => '15:35', 'dur' => 155],
            ['dep' => '16:30', 'arr' => '19:05', 'dur' => 155],
            ['dep' => '20:45', 'arr' => '23:15', 'dur' => 150],
        ];

        $basePrices = [
            'ECONOMY'         => [18000, 22000, 26000, 31000, 38000],
            'BUSINESS'        => [55000, 65000, 75000, 85000, 95000],
            'FIRST_CLASS'     => [120000, 140000, 160000, 180000, 200000],
            'PREMUIM_ECONOMY' => [32000, 38000, 44000, 50000, 58000],
        ];

        $prices  = $basePrices[$cabin] ?? $basePrices['ECONOMY'];
        $flights = [];

        // Fetch airport info for display
        $originAirport      = $this->airport($origin);
        $destinationAirport = $this->airport($destination);

        foreach ($airlines as $i => $airline) {
            $slot      = $slots[$i];
            $depDt     = $date . 'T' . $slot['dep'] . ':00';
            $arrDt     = $date . 'T' . $slot['arr'] . ':00';
            $flightNo  = $airline['code'] . str_pad((string)(100 + $i * 23), 3, '0', STR_PAD_LEFT);
            $duration  = $slot['dur'];

            $perPaxBase = $prices[$i];
            $baseFare   = $perPaxBase * $pax;
            $tax        = (int)($baseFare * 0.18);
            $gross      = $baseFare + $tax;

            // Segment — matches what HittitAndSaberListing reads
            $segment = [
                'departure_datetime'  => $depDt,
                'arrival_datetime'    => $arrDt,
                'flight_number'       => $flightNo,
                'operating_airline'   => $airline['code'],
                'marketing_airline'   => $airline['code'],
                'aircraft'            => 'Boeing 737-800',
                'cabin_class'         => $cabin,
                'res_book_desig_code' => 'Y',
                'stop_quantity'       => 0,
                'duration'            => $duration,
                'origin'              => $originAirport,
                'destination'         => $destinationAirport,
                'departure_terminal'  => 'T1',
                'arrival_terminal'    => 'T2',
            ];

            // Leg — matches what HittitAndSaberListing reads (data.legs[0])
            $leg = [
                'flight_number'   => [$flightNo],
                'segments'        => [$segment],
                'journey_duration'=> $duration,
                'stops'           => 0,
                'sector'          => [$origin, $destination],
            ];

            // Fare options — matches fare_option array in HittitAndSaberListing
            $fareOptions = [
                [
                    'booking_id'    => 'MOCK_' . strtoupper($airline['code']) . '_' . $i . '_' . time(),
                    'fare_key'      => 'MOCK_' . $i,
                    'fare_type'     => 'Published',
                    'cabin_class'   => $cabin,
                    'rbd'           => 'Y' . $i . 'PKR',
                    'fare_basis'    => 'Y' . $i . 'PKR',
                    'is_refundable' => ($i % 2 === 0),
                    'has_meal'      => ($i % 3 === 0),
                    'bagage_info'   => 'Adult(23 KG)',
                    'price' => [
                        'base_fare'    => number_format($baseFare, 2, '.', ','),
                        'tax'          => number_format($tax, 2, '.', ','),
                        'gross_amount' => number_format($gross, 2, '.', ','),
                        'discount_psf' => '0.00',
                        'currency'     => 'PKR',
                        'total_amount' => number_format($gross, 2, '.', ','),
                    ],
                    'fare_break_down' => [
                        'Adult' => [
                            'quantity'     => $pax,
                            'base_fare'    => number_format($perPaxBase, 2, '.', ','),
                            'tax'          => number_format($perPaxBase * 0.18, 2, '.', ','),
                            'gross_amount' => number_format($perPaxBase * 1.18, 2, '.', ','),
                            'gross_fare'   => number_format($perPaxBase * 1.18, 2, '.', ','),
                            'currency'     => 'PKR',
                            'total_amount' => number_format($perPaxBase * 1.18, 2, '.', ','),
                        ],
                    ],
                    'baggage' => [
                        'Adult' => [[
                            ['weight' => 23, 'unit' => 'KG', 'provisionType' => 'A', 'provision' => 'Checked baggage allowance', 'pieceCount' => 1, 'airlineCode' => $airline['code']],
                            ['weight' => 7,  'unit' => 'KG', 'provisionType' => 'B', 'provision' => 'Carry-on baggage allowance', 'pieceCount' => 1, 'airlineCode' => $airline['code']],
                        ]],
                    ],
                ],
            ];

            $flights[] = [
                // provider = SABRE so HittitAndSaberListing renders it
                'provider'    => 'SABRE',
                'API'         => 'MOCK',
                'is_mock'     => true,
                'airline'     => $airline,
                'legs'        => [$leg],
                'fare_option' => $fareOptions,
                'price' => [
                    'base_fare'    => number_format($baseFare, 2, '.', ','),
                    'tax'          => number_format($tax, 2, '.', ','),
                    'gross_amount' => number_format($gross, 2, '.', ','),
                    'currency'     => 'PKR',
                    'total_amount' => number_format($gross, 2, '.', ','),
                ],
                'stops'    => 0,
                'duration' => $duration,
            ];
        }

        return $flights;
    }

    private function airport(string $iata): array
    {
        $airport = \App\Models\Airport::where('iata_code', $iata)
            ->select(['name', 'iata_code', 'municipality', 'country'])
            ->first();

        if ($airport) {
            return [
                'iata_code'    => $airport->iata_code,
                'name'         => $airport->name,
                'municipality' => $airport->municipality,
                'country'      => $airport->country,
                'terminal'     => '',
            ];
        }

        return [
            'iata_code'    => $iata,
            'name'         => $iata . ' Airport',
            'municipality' => $iata,
            'country'      => '',
            'terminal'     => '',
        ];
    }

    public function createPassengerNameRecord(array $data, array $request): array
    {
        $pnr = 'MC' . strtoupper(substr(uniqid(), -4));
        $booking = new \App\Models\Booking();
        $booking->booking_id = random_id('MC');
        $booking->phone_number = $request['contact_number'] ?? '03001234567';
        $booking->status = 'confirmed';

        $price = $data['price'] ?? [];
        $baseFare = floatval(str_replace(',', '', $price['base_fare'] ?? '0'));
        $tax = floatval(str_replace(',', '', $price['tax'] ?? '0'));
        $grossFare = floatval(str_replace(',', '', $price['gross_amount'] ?? '0'));
        $totalAmount = floatval(str_replace(',', '', $price['total_amount'] ?? ($grossFare ?: ($baseFare + $tax))));

        $booking->base_fare = $baseFare;
        $booking->tax = $tax;
        $booking->gross_fare = $grossFare;
        $booking->discount = 0;
        $booking->total_amount = $totalAmount;
        $booking->booking_pnr = $pnr;
        $booking->provider_name = 'MOCK';
        $booking->pnr_expiry = now()->addHours(24)->format('Y-m-d H:i:s');

        $airlineIata = $data['airline']['iata_code'] ?? 'PK';
        $airline = \App\Models\Airline::where('iata_code', $airlineIata)->first();
        $booking->airline_id = $airline?->id ?? 1;

        $mockConnector = \App\Models\Connector::where('type', 'MOCK')->first();
        $booking->supplier_id = $mockConnector?->supplier_id ?? 1;

        $firstLeg = $data['segment_data'][0] ?? $data['legs'][0] ?? [];
        $booking->departure_airport = $firstLeg['departure']['airport']['name'] ?? ($firstLeg['origin'] ?? 'Origin');
        $booking->departure_date_time = $firstLeg['departure']['departure_datetime'] ?? now()->addDays(3)->format('Y-m-d H:i:s');
        $booking->arrival_airport = $firstLeg['arrival']['airport']['name'] ?? ($firstLeg['destination'] ?? 'Destination');
        $booking->arrival_date_time = $firstLeg['arrival']['arrival_datetime'] ?? now()->addDays(3)->addHours(2)->format('Y-m-d H:i:s');

        $booking->baggage = $data['baggage'] ?? null;
        $booking->fare_break_down = $data['fare_break_down'] ?? null;
        $booking->booking_class = 'Y';
        $booking->is_refundable = 1;
        $booking->applied_discount = 0;

        $user = \Illuminate\Support\Facades\Auth::user();
        $booking->booked_by = $user?->id ?? 1;
        $booking->org_id = $user?->org_id ?? null;
        $booking->save();

        if (!empty($request['passengers']) && is_array($request['passengers'])) {
            foreach ($request['passengers'] as $pax) {
                \App\Models\Passenger::create([
                    'booking_id'           => $booking->id,
                    'title'                => $pax['title'] ?? 'MR',
                    'first_name'           => $pax['first_name'] ?? 'Demo',
                    'last_name'            => $pax['last_name'] ?? 'Passenger',
                    'date_of_birth'        => $pax['date_of_birth'] ?? '1990-01-01',
                    'gender'               => $pax['gender'] ?? 'MALE',
                    'type'                 => $pax['type'] ?? 'ADT',
                    'passport_no'          => $pax['passport_no'] ?? 'A12345678',
                    'passport_expiry_date' => $pax['passport_expiry_date'] ?? now()->addYears(5)->format('Y-m-d'),
                    'nationality'          => $pax['nationality'] ?? 'PK',
                ]);
            }
        }

        return [
            'status'  => true,
            'data'    => $booking,
            'message' => 'Demo Booking created successfully',
        ];
    }
}
