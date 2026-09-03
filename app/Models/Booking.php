<?php

namespace App\Models;

use App\Services\PassengerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Booking extends Model
{
    use \App\Traits\HasUuid;

    protected $table = "bookings";

    protected $fillable = [
        'uuid',
        'booking_id',
        'ndc_order_id',
        'status',
        'base_fare',
        'type',
        'tax',
        'gross_fare',
        'discount',
        'total_amount',
        'phone_number',
        'booking_pnr',
        'pnr_meta',
        'provider_name',
        'pnr_expiry',
        'airline_id',
        'departure_airport',
        'departure_date_time',
        'arrival_airport',
        'arrival_date_time',
        'r_departure_airport',
        'r_departure_date_time',
        'r_arrival_airport',
        'r_arrival_date_time',
        'baggage',
        'booking_brand',
        'applied_discount',
        'fare_break_down',
        'booking_class',
        'is_refundable',
        'refund_penalties',
        'exchange_penalties',
        'is_multi_city',
        'issuing_pcc',
        'reserving_pcc',
        'tour_code',
        'supplier_id',
        'booked_by',
        'issued_by',
        'request_by',
        'org_id',
        'fare_rules',
        'issued_at',
        'applied_discount_percent',
        'is_seat_selected',
        'extra_selection',
        'extra_amount'
    ];
    protected $hidden = [
        'fare_rules',
        'flown_status',
        'tour_code',
        'reserving_pcc',
        'issuing_pcc',
        'exchange_penalties'
    ];

    protected function casts(): array
    {
        return [
            'baggage'           => 'array',
            'fare_break_down'   => 'array',
            'fare_rules'        => 'array',
            'pnr_meta'          => 'array',
            'is_seat_selected'  => 'boolean',
            'extra_selection'   => 'array'
        ];
    }
    protected static function boot()
    {
        parent::boot();
        static::created(function ($booking) {
            if(request()->has('passengers')){
                (new PassengerService())->craetePassenger(request()->passengers, $booking->id);
            }
        });
    }

    public function booked_segments()
    {
        return $this->hasMany(BookedSegment::class, 'booking_id', 'id')->whereParentId(null);
    }
    public function booked_child_segements(){
        return $this->hasMany(BookedSegment::class, 'booking_id', 'id');
    }
    public function passengers()
    {
        return $this->hasMany(Passenger::class, 'booking_id', 'id');
    }

    public function airline()
    {
        return $this->belongsTo(Airline::class, 'airline_id', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public function booked_by_user()
    {
        return $this->belongsTo(User::class, 'booked_by', 'id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'org_id', 'id')->with('parent:id,name,name');
    }

    function scopeOrgFilter(\Illuminate\Database\Eloquent\Builder $builder): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $roleName = role_name();

        // if (in_array($roleName, ['agency', 'a-employee'])) {
        //     $builder->where('org_id', $user->org_id);
        // } elseif (in_array($roleName, ['branch', 'b-employee'])) {
        //     $orgIds = \App\Models\Organization::whereParentId($user->org_id)->pluck('id')->toArray();
        //     $builder->whereIn('org_id', array_merge([$user->org_id], $orgIds));
        // } elseif ($roleName === 'sub-agent') {
        //     $builder->where('booked_by', $user->id);
        // }

        if (in_array($roleName, ['agency', 'a-employee'])) {
            $builder->where('org_id', $user->org_id);
        } elseif (in_array($roleName, ['branch', 'b-employee'])) {
            if(!$user->organization->show_all_bookings){
                $orgIds = \App\Models\Organization::whereParentId($user->org_id)->pluck('id')->toArray();
                $builder->whereIn('org_id', array_merge([$user->org_id], $orgIds));
            }
        } elseif ($roleName === 'sub-agent') {
            $builder->where('booked_by', $user->id);
        }
    }

    public function refund_request(){
        return $this->hasOne(RefundRequest::class, 'booking_id', 'id');
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (?string $value, array $attributes) {
                if (!is_null($attributes['pnr_expiry']) && \Carbon\Carbon::parse($attributes['pnr_expiry'])->isPast() && $attributes['status'] != 'issued') {
                    return 'expired';
                }
                return $value;
            },
        );
    }

    public function scopeValidConfirmed(\Illuminate\Database\Eloquent\Builder $builder)
    {
        return $builder->where(function ($subQuery) {
            $subQuery->where('status', 'confirmed')->whereDate('pnr_expiry', '>=', now());
        });
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'booking_id', 'id');
    }
}
