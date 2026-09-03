<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Passenger extends Model
{
    protected $table = "passengers";

    public static $title = ['Mr', 'Mrs', 'Ms', 'Miss'];
    public static $passenger_type = ['ADT', 'CNN', 'INF'];
    public static $gender = ['F', 'M', 'O'];

    protected $fillable = [
        'title',
        'first_name',
        'last_name',
        'passenger_type',
        'booking_id',
        'd_type',
        'd_number',
        'd_expiry',
        'date_of_birth',
        'ticket_number'
    ];

    
    protected function title(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => passenger_title()[$value],
        );
    }
}
