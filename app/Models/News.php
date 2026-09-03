<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    use \App\Traits\HasUuid;
    protected  $table = "news";
    protected $fillable = [
        'uuid',
        'title',
        'description',
        'image',
        'news_url',
        'is_feature'
    ];

    protected function casts(): array
    {
        return [
            'is_feature'=> 'boolean'
        ];
    }

}
