<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class BookingPricingModification extends Model
{
   
    use \App\Traits\HasUuid;

    protected $table = "booking_pricing_modifications";

    protected $fillable = [
        'uuid',
        'booking_id',
        'user_id',
        'modified_pricing',
        'old_pricing',
        'remarks'
    ];

    protected function casts(): array
    {
        return [
            'modified_pricing' => 'array',
            'old_pricing' => 'array',
        ];
    }


    public function user(){
        return $this->belongsTo(User::class)->select('id', 'name');
    }

    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? \Carbon\Carbon::parse($value)->format('D, F j, Y h:i A') : null,
        );
    }
}
