<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'data_source_ids',
        'indicator_ids',
    ];

    protected $casts = [
        'data_source_ids' => 'array',
        'indicator_ids'   => 'array',
    ];

    // Accessor: Selected Data Sources fetch karne ke liye ($source->data_sources)
    public function getDataSourcesAttribute()
    {
        if (empty($this->data_source_ids)) {
            return collect();
        }
        return DataSource::whereIn('id', (array)$this->data_source_ids)->get();
    }

    // Accessor: Selected Indicators fetch karne ke liye ($source->indicators)
    public function getIndicatorsAttribute()
    {
        if (empty($this->indicator_ids)) {
            return collect();
        }
        return Indicator::whereIn('id', (array)$this->indicator_ids)->get();
    }
}