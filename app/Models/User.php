<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory,
        Notifiable,
        HasRoles,
        \App\Traits\HasUuid,
        \Laravel\Sanctum\HasApiTokens,
        HasPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
        'phone_number',
        'address',
        'city',
        'status',
        'branch_id',
        'login_otp',
        'issuance_otp',
        'permission_otp',
        'org_id',
        'last_login',
        'google_auth_verified'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'login_otp',
        'issuance_otp',
        'permission_otp',
        'email_verified_at',
        'google2fa_secret',
        'password_set',
        'google_auth_verified',
        'otp_pref',
        'deleted_at'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'password'              => 'hashed',
            'google_auth_verified'  => 'boolean',
            'can_issue_ticket'      => 'boolean'
        ];
    }
    /**
     * User Status
     */

    public static $status = ['inactive' => 0, 'active' => 1];

    public function parent(){
        return $this->belongsTo(\App\Models\User::class, 'parent_id', 'id');
    }

    public function branch(){
        return $this->hasOne(Branch::class, 'id', 'branch_id')->select('id', 'name');
    }

    public function organization(){
        return $this->belongsTo(Organization::class, 'org_id', 'id');
    }

    protected function lastLogin(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? \Carbon\Carbon::parse($value)->format('D, F j, Y h:i A') : null,
        );
    }

    protected function profileImage(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => is_null($value) ?null: url($value)
        );
    }

     public function bookings(){
        return $this->hasMany(Booking::class, 'booked_by', 'id');
    }
}
