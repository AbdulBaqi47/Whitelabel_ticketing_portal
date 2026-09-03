<?php

namespace App\Enums;

enum BookingStatusEnum: string
{
    case ISSUED = 'issued';
    case CONFIRMED = 'confirmed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case VOIDED = 'voided';
    case REFUNDED = 'refunded';
}
