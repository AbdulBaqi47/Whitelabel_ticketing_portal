<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Organization extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes, \App\Traits\HasUuid;
    protected $table = "organizations";

    protected $fillable = [
        'uuid',
        'parent_id',
        'name',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'phone_number',
        'status',
        'org_id',
        'theme_color',
        'logo',
        'financial_profile',
        'show_all_bookings'
    ];

    protected $appends = [
        'last_login'
    ];

    public function main_user(){
        return $this->hasOne(\App\Models\User::class , 'org_id', 'id');
    }

    protected function casts(): array
    {
        return [
            'status'            => 'boolean',
            'financial_profile' => 'boolean',
            'show_all_bookings' => 'boolean'
        ];
    }

    public function org_wallet():HasOne{
        return $this->hasOne(Wallet::class, 'org_id', 'id');
    }

    public function parent(){
        return $this->belongsTo($this, 'parent_id', 'id')->with('parent');
    }
    public function org_setting(){
        return $this->hasOne(UserSetting::class, 'org_id', 'id');
    }

    function scopeOrgFilter(\Illuminate\Database\Eloquent\Builder $builder): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $roleName = role_name();

        if($roleName == 'branch' || $roleName == 'b-employee'){
            $builder->where('parent_id', $user->org_id);
        }
        // if (in_array($roleName, agency_roles)) {
        //     $builder->where('org_id', $user->org_id);
        // } elseif (in_array($roleName, ['branch', 'b-employee'])) {
        //     $orgIds = \App\Models\Organization::whereParentId($user->org_id)->pluck('id')->toArray();
        //     $builder->whereIn('org_id', array_merge([$user->org_id], $orgIds));
        // }
    }

    public function getLastLoginAttribute()
    {
        return $this->main_user ? \Carbon\Carbon::parse($this->main_user->last_login)->format('Y-m-d H:s:i') : null;
    }

    protected function logo(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value != null ? url($value) : null, 
        );
    }

    public function logoPath(): ?string
    {
        return $this->getRawOriginal('logo');
    }

}


