<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Airport extends Model
{
    use \App\Traits\HasUuid;
    protected $table = "airports";
    protected $fillable = [
        'uuid',
        'name',
        'municipality',
        'iata_code',
        'iso_country',
        'country'
    ];
}
