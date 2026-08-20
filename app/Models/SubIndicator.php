<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubIndicator extends Model
{
    protected $fillable = [
        'indicator_id',
        'name',
        'alias_name',
        'sector',
        'survey',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    // Helper: Admin alias name prioritises over raw name
    public function getDisplayNameAttribute(): string
    {
        return $this->alias_name ?: $this->name;
    }
}