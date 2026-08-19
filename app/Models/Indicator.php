<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indicator extends Model
{
    use HasFactory;

    protected $table = 'indicators';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'parent_id',       // Added for virtual/duplicate sections
        'data_source_id',
        'theme_id',
        'indicator_code',
        'name',
        'alias',           // Added for custom display name
        'is_synced',
        'last_synced_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'attributes'     => 'array',
        'source' => 'array',
        'is_synced'      => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Relationship: Self-referencing Parent (Main Indicator)
     * Agar ye record khud ek duplicate section hai, toh ye apne main indicator ko belong karega.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'parent_id', 'id');
    }

    /**
     * Relationship: Self-referencing Children (Virtual/Duplicate Sections)
     * Ek main indicator ke multiple duplicate sections ho sakte hain.
     */
    public function virtualSections(): HasMany
    {
        return $this->hasMany(Indicator::class, 'parent_id', 'id');
    }

    /**
     * Relationship: Indicator belongs to DataSource
     */
    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id', 'id');
    }

    /**
     * Relationship: Indicator belongs to Theme
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'theme_id');
    }

    /**
     * Relationship: Indicator has many SubIndicators (Existing Table Relation)
     */
    public function subIndicators(): HasMany
    {
        return $this->hasMany(SubIndicator::class, 'indicator_id');
    }

    /**
     * Relationship: Charts configured under this specific main or virtual section
     */
    public function charts(): HasMany
    {
        return $this->hasMany(Chart::class, 'indicator_id')->orderBy('display_order');
    }
}