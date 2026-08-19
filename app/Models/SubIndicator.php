<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubIndicator extends Model
{
    protected $fillable = ['indicator_id', 'name'];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }
}