<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportRoute extends Model
{
    use \App\Traits\HasUuid;
    protected $table = "transport_routes";

    protected $fillable = ['uuid', 'name', 'amount', 'status'];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }
}
