<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
class Bank extends Model
{
    use \App\Traits\HasUuid;
    protected $table = "banks";
    const ACTIVE = 1;
    
    protected $fillable = [
        'uuid',
        'account_number',
        'account_holder_name',
        'bank_name',
        'status',
        'bank_logo',
        'bank_address',
        'contact_number',
        'iban',
        'org_id'
    ];

    protected function bankLogo(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => url($value),
        );
    }

    public function deposits(){
        return $this->hasMany(Deposit::class);
    }
    
}