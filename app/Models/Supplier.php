<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use \App\Traits\HasUuid;
    protected $table = "suppliers";
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'status'
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }
}
