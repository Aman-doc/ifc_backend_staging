<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    protected $fillable = ['name', 'code','status'];

    public function aliases(): HasMany
    {
        return $this->hasMany(StateAlias::class);
    }
}