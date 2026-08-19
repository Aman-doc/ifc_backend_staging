<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chart extends Model
{
    protected $fillable = [
        'indicator_id',
        'chart_type_id',
        'chart_name',
         'source',  
        'display_order',
        'field_config',
    ];

    protected $casts = [
        'field_config' => 'array', // Automatic JSON to Array conversion
        'display_order' => 'integer',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    public function chartType(): BelongsTo
    {
        return $this->belongsTo(ChartType::class);
    }

    public function getColorsForField(string $fieldKey): array
    {
        return $this->field_config[$fieldKey]['colors'] ?? [];
    }

    /**
     * Get selected values for a specific field key from field_config
     */
    public function getValuesForField(string $fieldKey): array
    {
        return $this->field_config[$fieldKey]['values'] ?? [];
}
}