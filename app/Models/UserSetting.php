<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $table = "user_settings";
    const ACTIVE = 1;

    protected $fillable = [
        'org_id',
        'issue_ticket',
        'flight_search',
        'void_charges',
        'soto_void_charges',
        'can_issue_ticket',
        'extra_commision'
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
