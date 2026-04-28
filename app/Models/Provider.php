<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Provider extends Model
{
    protected $fillable = ['name', 'slug'];

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }
}
