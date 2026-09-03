<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingHistory extends Model
{
    use \App\Traits\HasUuid;

    protected $table = "booking_histories";

    protected $fillable = [
        'uuid',
        'booking_id',
        'user_id',
        'modified_data',
        'old_data',
        'remarks'
    ];

    protected function casts(): array
    {
        return [
            'modified_data' => 'array',
            'old_data' => 'array',
        ];
    }
}
