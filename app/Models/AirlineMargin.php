<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class AirlineMargin extends Model
{
    use \App\Traits\HasUuid;
    protected $table = "airline_margins";
    
    protected $fillable = [
        'uuid',
        'sales_channel',
        'airline_id',
        'region',
        'margin',
        'margin_type',
        'sale_start_continue',
        'sale_end_continue',
        'travel_start_continue',
        'travel_end_continue',
        'rbds',
        'is_apply_on_gross',
        'status',
        'remarks',
        'apply_to_all',
        'airline_id',
        'apply_all_value'
    ];

    protected $hidden = [
        'deleted_at',
        'created_at',
        'updated_at'
    ];

    public function airline():BelongsTo{
        return $this->belongsTo(Airline::class);
    }

    protected function casts(): array
    {
        return [
            'region' => 'array',
            'is_apply_on_gross' => 'boolean',
            'apply_to_all'=> 'boolean',
            'status' => 'boolean',
        ];
    }
    
    protected function saleStartContinue(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => \Carbon\Carbon::parse($value)->format('Y-m-d'),
        );
    }

    protected function saleEndContinue(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => \Carbon\Carbon::parse($value)->format('Y-m-d'),
        );
    }

    protected function travelStartContinue(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => \Carbon\Carbon::parse($value)->format('Y-m-d'),
        );
    }

    protected function travelEndContinue(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => \Carbon\Carbon::parse($value)->format('Y-m-d'),
        );
    }

    protected function region(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => implode(', ', json_decode($value, true)),
        );
    }


    public function margin_originations(){
        return $this->hasMany(MarignOrigination::class, 'airline_margin_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();
        static::deleted(function ($airline_margin) {
            foreach($airline_margin->margin_originations as $margin_origination){
                $margin_origination->delete();
            }
        });
    }

}
