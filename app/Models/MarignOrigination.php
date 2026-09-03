<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class MarignOrigination extends Model
{
    protected $table = 'marign_originations';

    protected $fillable = [
        'org_id',
        'parent_org_id',
        'airline_margin_id',
        'margin',
        'margin_type',
        'apply_all',
        'apply_value'
    ];
    
    
    public function airline_margin(){
        return $this->belongsTo(AirlineMargin::class, 'airline_margin_id', 'id');
    }

    protected function casts(): array
    {
        return [
            'apply_all' => 'boolean',
        ];
    }

    
}
