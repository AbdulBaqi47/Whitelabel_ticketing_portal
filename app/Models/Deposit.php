<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Deposit extends Model
{
    use \App\Traits\HasUuid;
    protected $table = 'deposits';

    protected $fillable = [
        'bank_id',
        'request_id',
        'org_id',
        'approved_by',
        'status',
        'reciept',
        'deposit_amount',
        'deposit_id'
    ];

    public function bank(){
        return $this->belongsTo(Bank::class, 'bank_id', 'id');
    }

    public function organization(){
        return $this->belongsTo(User::class, 'org_id', 'id');
    }

    public function approved_by(){
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    public function request_by(){
        return $this->belongsTo(User::class, 'request_id', 'id');
    }

    protected function reciept(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => url($value),
        );
    }
    public function notifications(){
        return $this->morphMany(Notification::class, 'notifiable');
    }
}
