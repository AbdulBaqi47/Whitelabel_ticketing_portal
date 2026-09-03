<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    use \App\Traits\HasUuid;
    protected $table = "refund_requests";
    protected $fillable = [
        'org_id',
        'requested_by_id',
        'status',
        'booking_id',
        'refunded_amount'
    ];

    public function booking(){
        return $this->belongsTo(Booking::class);
    }

    public function requested_by(){
        return $this->belongsTo(User::class, 'requested_by_id', 'id');
    }

    public function organization(){
        return $this->belongsTo(Organization::class, 'org_id', 'id');
    }
}
