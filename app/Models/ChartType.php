<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'fields_definition', 'is_active'];

    protected $casts = [
        'fields_definition' => 'array', // Array/JSON cast
        'is_active' => 'boolean',
    ];
}