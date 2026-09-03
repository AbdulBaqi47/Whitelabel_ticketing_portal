<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookedSegment extends Model
{
    protected $table = "booked_segments";

    protected $fillable = [
        'o_flight_number',
        'o_airline',
        'm_flight_number',
        'm_airline',
        'seg_origin',
        'seg_destination',
        'seg_departure_datetime',
        'seg_arrival_datetime',
        'departure_terminal',
        'arrival_terminal',
        'cabin_fullname',
        'flight_status_code',
        'flight_type',
        'booking_id',
        'flight_duration',
        'parent_id',
        'meal',
        'flight_pnr'
    ];

    protected $appends = [
        'flight_duration'
    ];

    protected function casts(): array
    {
        return [
            'meal' => 'array',
        ];
    }
    public function childs(){
        return $this->hasMany($this, 'parent_id', 'id')->with(['marketing_airline:name,thumbnail,iata_code', 'operating_airline:name,thumbnail,iata_code', 'd_airport:name,iata_code,municipality,country', 'a_airport:name,iata_code,municipality,country']);
    }

    public function marketing_airline(){
        return $this->BelongsTo(Airline::class, 'm_airline', 'iata_code');
    }

    public function operating_airline(){
        return $this->BelongsTo(Airline::class, 'o_airline', 'iata_code');       
    }
    public function d_airport(){
        return $this->belongsTo(Airport::class, 'seg_origin', 'iata_code');
    }
    public function a_airport(){
        return $this->belongsTo(Airport::class, 'seg_destination', 'iata_code');
    }

    public function getFlightDurationAttribute()
    {
        return flight_duration($this['seg_departure_datetime'], $this['seg_arrival_datetime'], $this['seg_origin'], $this['seg_destination']);
    }

}