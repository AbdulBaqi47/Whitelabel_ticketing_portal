<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegisterationRequest extends Model
{
    use \App\Traits\HasUuid;
    protected $table = "registeration_requests";

    protected $fillable = [
        'agency_name',
        'name',
        'email',
        'city',
        'phone_number',
        'address',
        'mark_read'
    ];

    protected function casts(): array
    {
        return [
            'mark_read' => 'boolean',
        ];
    }
}
