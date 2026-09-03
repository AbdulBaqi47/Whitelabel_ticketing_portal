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
            Status: Confirmed <br>
            Airline (PNR): {{ $segment['flight_pnr'] ?? '-' }}
             {!! pdf_baggage($booking['baggage'], $booking['provider_name'], $seg_key) !!}
        </div>

    </div>
</div>