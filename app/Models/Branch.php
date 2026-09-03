<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{

    use \App\Traits\HasUuid;
    protected $table = "branches";
    protected $fillable = ['uuid', 'name', 'address', 'city', 'state', 'country', 'postal_code', 'phone_number', 'user_id'];

    public function branchManager():BelongsTo{
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function branch_agencies(){
        return $this->hasMany(User::class, 'branch_id', 'id')->whereHas('roles', function($subQuery){
            $subQuery->where('name', 'agency');
        });
    }

    public function branch_employees(){
        return $this->hasMany(User::class, 'branch_id', 'id')->whereHas('roles', function($subQuery){
            $subQuery->where('name', 'b-employee');
        });
    }
}