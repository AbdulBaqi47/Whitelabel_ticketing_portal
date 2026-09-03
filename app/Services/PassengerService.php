<?php

namespace App\Services;
class PassengerService
{
    public function craetePassenger($passengers, $booking_id)
    {
        foreach ($passengers as $value) {
            \App\Models\Passenger::create([
                'title' => strtoupper($value['title']),
                'first_name' => strtoupper($value['first_name']),
                'last_name' => strtoupper($value['last_name']),
                'passenger_type' => $value['passenger_type'],
                'booking_id' => $booking_id,
                'd_type' => $value['d_type'],
                'd_number' => $value['d_number'],
                'd_expiry' => $value['d_expiry'],
                'date_of_birth'=>$value['date_of_birth']
            ]);
        }
    }
}
