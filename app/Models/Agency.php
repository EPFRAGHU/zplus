<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'code', 'tagline', 'registrations', 'address', 'email', 'website', 'letterhead_emails', 'phone', 'phones', 'logo_path', 'gstin', 'pan', 'signatory_name', 'signatory_title'])]
class Agency extends Model
{
    /** Logo as a data URI for PDFs (dompdf cannot read private storage paths), or null. */
    public function logoDataUri(): ?string
    {
        $disk = Storage::disk('local');
        if (! $this->logo_path || ! $disk->exists($this->logo_path)) {
            return null;
        }

        return 'data:'.($disk->mimeType($this->logo_path) ?: 'image/png').';base64,'.base64_encode($disk->get($this->logo_path));
    }

    /** Whether there is enough to print a letterhead instead of a plain name. */
    public function hasLetterhead(): bool
    {
        return filled($this->tagline) || filled($this->registrations) || filled($this->logo_path);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
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
