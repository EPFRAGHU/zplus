<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['agency_id', 'name', 'short_code', 'letter_code', 'addressee', 'billing_address', 'contact_person', 'phone', 'email', 'district', 'state_code', 'gstin', 'payment_term_days', 'status'])]
class Client extends Model
{
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** "ICMR-NIHR (RMRCBB), Bhubaneswar · SWH" */
    public function label(): string
    {
        return $this->name.($this->agency ? ' · '.$this->agency->code : '');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(GemContract::class);
    }
}
