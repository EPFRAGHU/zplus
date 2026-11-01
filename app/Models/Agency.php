<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'address', 'email', 'phone', 'gstin', 'pan', 'signatory_name', 'signatory_title'])]
class Agency extends Model
{
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(GemContract::class);
    }
}
