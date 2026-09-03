<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">

    <style>
        body {
            margin: 0;
            /* background: #f2f3f5; */
            font-family: Arial;
            color: #222
        }

        .page {
            width: 240mm;
            /* min-height: 297mm; */
            margin: auto;
            background: #fff;
            padding: 25px 35px 40px;
            box-sizing: border-box
        }

        .top {
            display: flex;
            justify-content: space-between
        }

        .line1 {
            font-size: 20px;
            font-weight: 700
        }

        .line1 strong {
            color: #d71920
        }

        .passenger {
            color: #566de4;
            font-size: 14px;
            margin-top: 4px
        }

        .divider {
            height: 1px;
            background: #d71920;
            /* margin: 8px 0 15px */
        }

        .row-head {
            display: flex;
            justify-content: space-between;
            background: #f8f8f8;
            padding: 8px;
            font-weight: bold;
            font-size: 13px;
            margin-top: 12px;
            margin-bottom:  5px;
        }

        .card {
            border: 2px solid #566de4;
            padding: 12px;
            margin-bottom: 14px;
            /* page-break-inside: avoid */
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            color: #566de4;
            font-size: 12px;
            font-weight: bold
        }

        .route {
            display: grid;
            grid-template-columns: 1fr 120px 1fr 170px;
            align-items: center;
            gap: 10px
        }

        .city-name {
            font-size: 18px;
            font-weight: bold
        }

        .airport {
            font-size: 11px;
            color: #777
        }

        .time {
            font-size: 18px;
            font-weight: bold;
            color: #566de4;
            margin-top: 5px
        }

        .duration {
            text-align: center;
            color: #566de4
        }

        .dur-text {
            font-size: 11px;
            color: #555
        }

        .details {
            border-left: 1px solid #ddd;
            padding-left: 10px;
            font-size: 11px;
            line-height: 1.6
        }

        .section-title {
            background: #f8f8f8;
            padding: 8px;
            font-weight: bold;
            font-size: 13px;
            margin-top: 10px;
        }

        .passenger-table{
            /* padding: ; */
            margin-left: 2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px
        }

        th {
            border-bottom: 1px solid #ddd;
            text-align: left;
            padding: 4px
        }

        td {
            padding: 5px 2px
        }

        .transit {
            border: 2px solid #a80808;
            background: #f8f8f8;
            padding: 10px;
            margin: 10px 0;
            font-weight: bold;
            display: flex;
            justify-content: space-between
        }

        .company-info {
         text-align: right;
         font-size: 14px;
         line-height: 1.4;
         color: #444;
      }
    </style>
</head>

<body>
    <div class="page">

        {{-- HEADER --}}
        <div class="top">
            <div>
                <div class="line1">
                    Booking Reference Number:
                    <strong>{{ $booking['booking_pnr'] }}</strong>
                    <span style="font-size:14px;color:#666;margin-left:10px">
                </div>
            </div>

            <div style="font-size:32px;font-weight:bold;color:#566de4">
                @php $by = booking_by($booking['booked_by']); @endphp
                <img src="{{ $by['logo'] != '' ? $by['logo'] : asset('storage/logos/software-logo.svg') }}" height="80" alt="{{$by['label']}} Logo">
            </div>

            <div class="company-info">
                <div>{{$by['label']}}</div>
                <div>{{format_phone_number($by['phone_number'])}}</div>
            </div>
        </div>

        <div class="divider"></div>

        {{-- SEGMENTS LOOP --}}

        @php
            $showTerminal = !in_array($booking['provider_name'], [
                'SABRE',
                'SABRE_NDC',
                'FLYDUBAI_API'
            ]);
        @endphp

        @foreach($booking['booked_segments'] as $key => $segment)


            {{-- STAY / LAYOVER --}}
            @if($key > 0)
                @php
                    $previousSegment = $booking['booked_segments'][$key - 1];

                    $arrival = \Carbon\Carbon::parse($previousSegment['seg_arrival_datetime']);
                    $departure = \Carbon\Carbon::parse($segment['seg_departure_datetime']);

                    // if departure is next day
                    if ($departure->lessThan($arrival)) {
                        $departure->addDay();
                    }

                    $layoverMinutes = $arrival->diffInMinutes($departure);

                    $days = floor($layoverMinutes / (24 * 60));
                    $hours = floor(($layoverMinutes % (24 * 60)) / 60);
                    $minutes = $layoverMinutes % 60;
                @endphp

                <div class="transit">
                    <div>
                        STAY IN {{ strtoupper($previousSegment['a_airport']['municipality']) }}
                        ({{ $previousSegment['a_airport']['iata_code'] }})
                    </div>

                    <div>
                        DURATION:

                        @if($days > 0)
                            {{ $days }}D
                        @endif

                        {{ $hours }}h {{ $minutes }}m
                    </div>
                </div>
            @endif
            {{-- DATE BAR --}}
            <div class="row-head">
                <div>DEPARTURE: {{ \Carbon\Carbon::parse($segment['seg_departure_datetime'])->translatedFormat('l, d M, Y') }}</div>
                <div>ARRIVAL: {{ \Carbon\Carbon::parse($segment['seg_arrival_datetime'])->translatedFormat('l, d M, Y') }}</div>
            </div>

            {{-- CARD --}}
            <div class="card">

                <div class="card-top">
                    <div>{{ $segment['operating_airline']['name'] }}</div>
                    <div>{{ $segment['o_airline'] }}-{{ $segment['o_flight_number'] }}</div>
                </div>

                <div class="route">

                    {{-- FROM --}}
                    <div>
                        <div class="city-name">
                            {{ $segment['d_airport']['municipality'] }} ({{ $segment['d_airport']['iata_code'] }})
                        </div>
                       <div class="airport">
                            {{ $segment['d_airport']['name'] ?? '' }}<br>
                            {{ terminalLabel($segment['departure_terminal'], $showTerminal) }}
                        </div>
                        <div class="time">
                            {{ \Carbon\Carbon::parse($segment['seg_departure_datetime'])->format('H:i A') }}
                        </div>
                    </div>

                    {{-- DURATION --}}
                    <div class="duration">
                        →<div class="dur-text">
                            {{ gmdate('H\h i\m', $segment['flight_duration'] * 60) }}
                        </div>
                    </div>

                    {{-- TO --}}
                    <div>
                        <div class="city-name">
                            {{ $segment['a_airport']['municipality'] }} ({{ $segment['a_airport']['iata_code'] }})
                        </div>
                        <div class="airport">
                            {{ $segment['a_airport']['name'] ?? '' }}<br>
                            {{ terminalLabel($segment['arrival_terminal'], $showTerminal) }}
                        </div>
                        <div class="time">
                            {{ \Carbon\Carbon::parse($segment['seg_arrival_datetime'])->format('H:i A') }}
                        </div>
                    </div>

                    {{-- DETAILS --}}
                    <div class="details">
                        <b>Flight Details</b><br>
                        Duration: <span>{{ gmdate('H\h i\m', $segment['flight_duration'] * 60) }}</span><br>
                        Cabin: <b>{{ $segment['cabin_fullname'] }}</b><br>
                        Status: Confirmed</b><br>
                        Airline (PNR): {{ $segment['flight_pnr'] ?? '-' }}

                        {!! pdf_baggage($booking['baggage'], $booking['provider_name'], $key) !!}
                    </div>

                </div>
            </div>


            {{-- TRANSIT --}}
            @foreach($segment['childs'] as $child)
                <div class="transit">
                    <div>LAYOVER IN {{ strtoupper($child['d_airport']['municipality']) }} ({{ $child['d_airport']['iata_code'] }})</div>
                    <div>
                        DURATION:
                        @php
                            $arrival = \Carbon\Carbon::parse($segment['seg_arrival_datetime']);
                            $departure = \Carbon\Carbon::parse($child['seg_departure_datetime']);
                            $layover = $arrival->diffInMinutes($departure);
                        @endphp

                        {{ gmdate('H\h i\m', $layover * 60) }}
                    </div>
                </div>

                @include('pdf.partials.segment-leg', ['segment' => $child, 'showTerminal' => $showTerminal, 'seg_key' => $key])

            @endforeach
        @endforeach

        

        {{-- PASSENGERS --}}
        <div class="section-title">PASSENGER INFORMATION</div>

        <table class="passenger-table">
            <tr>
                <th>Passenger</th>
                <th>E-Ticket</th>
                <th>Seat</th>
            </tr>

            @foreach($booking['passengers'] as $pax)
                <tr>
                    <td>{{ $pax['title'] }} {{ $pax['first_name'] }} {{ $pax['last_name'] }}</td>
                    <td>{{ $pax['ticket_number'] ?? '-' }}</td>
                    <td>Check-In Required</td>
                </tr>
            @endforeach

        </table>


        <div style="margin-top:30px;font-size:11px">
            <b>Important Information:</b><br>
            Please check-in at least 4 hours before departure for international flights
        </div>

    </div>
</body>

</html>