<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Transaction extends Model
{
    use \App\Traits\HasUuid;
    protected $table = "transactions";
    
     protected $fillable = [
        'uuid',
        'number',
        'transaction_amount',
        'entry_type',
        'transaction_naration',
        'transaction_show',
        'user_id',
        'org_id',
        'booking_id',
        'request_by'
    ];

    protected function casts(): array
    {
        return [
            'transaction_show' => 'boolean',
        ];
    }

    public function transaction_entries(){
        
        return $this->hasMany(TransactionEntry::class, 'transaction_id', 'id')->selectRaw("
            *,
            SUM(credit - debit) OVER (
                ORDER BY id
                ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
            ) as balance
        ");
    }

    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function organization(){
        return $this->belongsTo(Organization::class, 'org_id', 'id');
    }

    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: function($value){
               return \Carbon\Carbon::parse($value)->format('d/m/Y');
            }
        );
    }

    public function scopeOrgFilter(\Illuminate\Database\Eloquent\Builder $builder): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        if (in_array(role_name(), ['branch', 'b-employee'])) {
            $orgIds = \App\Models\Organization::whereParentId($user->org_id)->pluck('id')->toArray();
            $builder->whereIn('org_id', array_merge([$user->org_id], $orgIds));
        }
    }
}
