<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportType extends Model
{
    use \App\Traits\HasUuid;
    protected $table = 'transport_types';

    protected $fillable = [
        'uuid',
        'name',
        'status'
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }
}
