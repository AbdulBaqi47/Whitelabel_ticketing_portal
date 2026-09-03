<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
class Airline extends Model
{
    use \App\Traits\HasUuid;

    protected $table = "airlines";
    const ACTIVE = 1;
    
    protected $fillable = [
        'uuid',
        'name',
        'thumbnail',
        'iata_desi',
        'icao_code',
        'iata_code',
        'status',
        'issuing_pcc',
        'reserving_pcc',
        'tour_code',
        'country_id',
        'preferred_connector'
    ];

    protected function casts(): array
    {
        return [
            'preferred_connector' => 'array',
            'status'=> 'boolean'
        ];
    }
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => url($value),
        );
    }
}
