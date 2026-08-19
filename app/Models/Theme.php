<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    protected $fillable = [
        'name',
        'description',
        'data_source_ids',
        'indicator_ids',
        'created_by'
    ];

    protected $casts = [
        'data_source_ids' => 'array',
        'indicator_ids'   => 'array',
    ];
}