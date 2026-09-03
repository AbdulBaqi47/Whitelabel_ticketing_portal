<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    use \App\Traits\HasUuid;
    protected $table = 'accounts';
    protected $fillable = [
        'uuid',
        'name',
        'code',
        'description',
        'user_id',
        'supplier_id'
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
