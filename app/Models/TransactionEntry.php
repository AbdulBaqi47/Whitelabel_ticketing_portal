<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionEntry extends Model
{
    protected $table = "transaction_entries";
    protected $fillable = [
        'transaction_id',
        'adjustment_date',
        'entry_detail',
        'account_id',
        'debit',
        'credit'
    ];


    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
