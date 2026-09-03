<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemporayCreditLimit extends Model
{
    protected $table = 'temporay_credit_limits';
    protected $appends = ['status'];

    protected $fillable = [
        'wallet_id',
        'limit_amount',
        'start_date',
        'end_date',
        'created_by',
        'used_amount',
        'instant_stop_limit'
    ];

    protected $casts = [
        'instant_stop_limit' => 'boolean'
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class, 'wallet_id', 'id');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function getStatusAttribute()
    {
        $today = \Carbon\Carbon::today();

        if ($this->limit_amount == $this->used_amount) {
            return 'consumed';
        }

        if ($this->end_date && \Carbon\Carbon::parse($this->end_date)->lt($today)) {
            return 'expired';
        }

        if ($this->end_date && \Carbon\Carbon::parse($this->end_date)->gte($today)) {
            return 'active';
        }
    }
}
