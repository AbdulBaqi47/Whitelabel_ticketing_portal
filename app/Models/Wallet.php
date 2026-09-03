<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use \App\Traits\HasUuid;
    protected $table = "wallets";
    public static $status = ['active' => 1, 'inactive' => 0];
    
    protected $fillable = [
        'uuid',
        'org_id',
        'per_credit_limit',
        'available_per_credit_limit',
        'status',
        'temp_credit_limit',
        'temp_limit_due_date'
    ];

    protected function tempLimitDueDate(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                return \Carbon\Carbon::parse($value)->format('d-m-Y');
            },
        );
    }

    public function temporay_credit_limits(){
        return $this->hasMany(TemporayCreditLimit::class, 'wallet_id', 'id')->latest();
    }   
}
