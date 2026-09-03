<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use \App\Traits\HasUuid;

class AirTicket extends Model
{
    use HasUuid;
    protected $table = 'air_tickets';
    protected $fillable = [
        'uuid',
        'flight_booking_passenger_id',
        'reservation',
        'ticket_number',
        'ticket_info'
    ];
}
