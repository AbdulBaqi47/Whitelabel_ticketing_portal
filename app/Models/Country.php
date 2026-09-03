<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use \App\Traits\HasUuid;
    protected $table = 'countries';
    protected $fillable = [
        'uuid',
        'name',
        'nice_name',
        'iso',
        'iso3',
        'status',
        'country'
    ];
}
