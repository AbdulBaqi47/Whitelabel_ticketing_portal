<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountHead extends Model
{
    use \App\Traits\HasUuid;
    protected $table = 'account_heads';

    protected $fillable = ['uuid', 'name', 'status'];

}
