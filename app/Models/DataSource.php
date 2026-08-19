<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSource extends Model
{
    protected $fillable = [
        'dataset_id',
        'parent_datasource_id',
        'title',
        'description',
        'is_synced',
    ];

    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class, 'data_source_id', 'id');
    }

    protected $casts = [
        'metadata'  => 'array',    // Automatically JSON string ko Array me parse kar dega
        'is_synced' => 'boolean',  // Automatically Boolean (true/false) return karega
    ];
}