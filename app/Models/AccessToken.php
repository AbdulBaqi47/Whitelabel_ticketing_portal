<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessToken extends Model
{
    use \App\Traits\HasUuid;
    
    protected $table = "access_tokens";

    protected $fillable = [
        'uuid',
        'type',
        'token',
        'expiry_date',
        'status'
    ];
}
