<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{request()->email_type == 'send_booking_email' ? 'Booking Information': 'Ticket Detail'}} - PNR: {{$booking['booking_pnr']}}</title>
    <style>
        body {
            background-color: #f3f4f6;
            padding: 24px;
        }
        .container {
            max-width: 72rem;
            margin: 0 auto;
            background-color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-radius: 0.5rem;
            padding: 24px;
        }
        h2 {
            font-size: 1.25rem;
            font-weight: bold;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 8px;
            margin-bottom: 16px;
        }
        .booking-info {
            display: flex;
            gap: 4px;
            background-color: #bfdbfe;
            padding: 16px;
            border-radius: 0.5rem;
            justify-content: space-around;
        }
        h3 {
            font-size: 1.125rem;
            font-weight: bold;
            margin-top: 24px;
            margin-bottom: 8px;
        }
        table {
            width: 100%;
            text-align: left;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #e5e7eb;
        }
        thead {
            background-color: #bfdbfe;
            text-transform: uppercase;
            font-size: 0.75rem;
            color: #374151;
        }
        tbody tr {
            background-color: white;
            border-bottom: 2px solid #e5e7eb;
        }
        tbody tr:hover {
            background-color: #f9fafb;
        }
        .text-black {
            color: #000;
        }
        .border-r {
            border-right: 2px solid #e5e7eb;
        }
    </style>
</head>
<body>

    @php
    $booking_status= [
		'issued' => 'Ticket Issued',
		'confirmed'=> 'Booking Confirmed',
	];
    @endphp
    <div class="container">
        <h2>Booking Information</h2>
        <div class="booking-info">
            <div style="width: 40%;">
            @php $by = booking_by($booking['booked_by']); @endphp
            <div><strong>Agency:</strong> {{ strtoupper($by['label']) }}</div>
            <div><strong>PNR Number:</strong> {{$booking['booking_pnr']}}</div>
            <div><strong>Airline:</strong> {{$booking['airline']['name']}}</div>
            <div><strong>Booking Class:</strong> {{$booking['booking_brand']}}</div>
        </div>
        <div style="width: 35%;">
            <div><strong>Booking Date:</strong> {{\Carbon\Carbon::parse($booking['created_at'])->isoFormat('dddd, MMM DD, YYYY HH:mm')}}</div>
            <div><strong>Flight Date:</strong> {{\Carbon\Carbon::parse($booking['departure_date_time'])->isoFormat('dddd, MMM DD, YYYY HH:mm')}}</div>
            <div><strong>Booking Status:</strong> {{$booking_status[$booking['status']]}}</div>
        </div>
        </div>
        <h3>Flight Details</h3>
        <table>
            <thead>
                <tr>
                    <th>Detail</th>
                    <th>Flight Number</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Time</th>
                    <th>Meal</th>
                    <th>Luggage</th>
                </tr>
            </thead>
            <tbody>

                @foreach($booking['booked_segments'] as $key => $segment)
                    @foreach($segment['childs'] as $sub_key => $sub_value)
                        @if($sub_key == 0)
                            <tr>
                                <td class="text-black border-r" rowspan="{{count($segment['childs']) + 1}}">Flight {{$key+1}}</td>
                                <td>{{$segment['o_airline']}}-{{$segment['m_flight_number']}}</td>
                                <td>{{$segment['d_airport']['municipality']}} ({{$segment['seg_origin']}}) - {{\Carbon\Carbon::parse($segment['seg_departure_datetime'])->isoFormat('dddd, MMM DD, YYYY HH:mm')}}</td>
                                <td>{{$segment['a_airport']['municipality']}} ({{$segment['seg_destination']}}) - {{\Carbon\Carbon::parse($segment['seg_arrival_datetime'])->isoFormat('dddd, MMM DD, YYYY HH:mm')}}</td>
                                <td>{{$segment['cabin_fullname']}} | Duration: {{\Carbon\Carbon::parse($segment['seg_departure_datetime'])->diff(\Carbon\Carbon::parse($segment['seg_arrival_datetime']))->format('%h hours %i minutes')}}</td>
                                <td>M-Meal</td>
                                <!-- <td>Luggage 20 KG (1 Piece)</td> -->
                            </tr>
                        @endif

                        <tr>
                            <td>{{$sub_value['o_airline']}}-{{$sub_value['m_flight_number']}}</td>
                            <td>{{$sub_value['d_airport']['municipality']}} ({{$sub_value['seg_origin']}}) - {{\Carbon\Carbon::parse($sub_value['seg_departure_datetime'])->isoFormat('dddd, MMM DD, YYYY HH:mm')}}</td>
                            <td>{{$sub_value['a_airport']['municipality']}} ({{$sub_value['seg_destination']}}) - {{\Carbon\Carbon::parse($sub_value['seg_arrival_datetime'])->isoFormat('dddd, MMM DD, YYYY HH:mm')}}</td>
                            <td>{{$sub_value['cabin_fullname']}} | Duration: {{\Carbon\Carbon::parse($sub_value['seg_departure_datetime'])->diff(\Carbon\Carbon::parse($sub_value['seg_arrival_datetime']))->format('%h hours %i minutes')}}</td>
                            <td>M-Meal</td>
                            <!-- <td>Luggage 20 KG (1 Piece)</td> -->
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
        <h3>Passenger Details</h3>
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Date Of Birth</th>
                    <th>Passport</th>
                    <th>Passport Expiry</th>
                    @if(request()->email_type == 'send_ticket_email')
                        <th>Ticket Number</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($booking['passengers'] as $value)
                <tr>
                    <th scope="row">{{$value['title']}}</th>
                    <td>{{$value['first_name']}}</td>
                    <td>{{$value['last_name']}}</td>
                    <td>{{$value['date_of_birth']}}</td>
                    <td>{{$value['d_number']}}</td>
                    <td>{{$value['d_expiry']}}</td>
                    @if(request()->email_type == 'send_ticket_email')
                        <td>{{$value['ticket_number']}}</td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>