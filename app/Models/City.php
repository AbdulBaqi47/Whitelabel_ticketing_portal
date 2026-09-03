<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    use \App\Traits\HasUuid;
    protected $table = "cities";

    protected $fillable = ['uuid', 'name', 'city_code', 'status', 'country_id'];

    public function country():BelongsTo{
        return $this->belongsTo(Country::class);
    }
}
