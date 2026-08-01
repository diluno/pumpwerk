<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Machine extends Model
{
    protected $guarded = [];

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }
}
