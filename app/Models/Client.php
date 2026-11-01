<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'short_code', 'letter_code', 'addressee', 'billing_address', 'contact_person', 'phone', 'email', 'district', 'state_code', 'gstin', 'payment_term_days', 'status'])]
class Client extends Model
{
    public function agencies(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(GemContract::class);
    }
}
