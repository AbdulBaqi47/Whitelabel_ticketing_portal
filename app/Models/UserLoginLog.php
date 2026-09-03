<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLoginLog extends Model
{
    protected $table = "user_login_logs";

    protected $fillable = [
        'user_id',
        'login_at',
        'ip_address',
        'user_agent',
        'client_data',
        'org_id',
        'status',
        'device_browser',
    ];

    protected $casts = [
        'client_data' => 'array',
        'status'      => 'boolean'
    ];

    public function organization(){
        return $this->belongsTo(Organization::class, 'org_id', 'id')->select('id', 'name');
    }
    
    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id')->select('id', 'name');
    }
    
}
