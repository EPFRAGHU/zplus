<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'code', 'tagline', 'registrations', 'address', 'email', 'website', 'letterhead_emails', 'phone', 'phones', 'logo_path', 'letterhead_style', 'gstin', 'pan', 'signatory_name', 'signatory_title', 'bank_name', 'bank_account_no', 'bank_ifsc', 'bank_branch'])]
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

    /** "Label: value" registration lines for the banner letterhead, with the GSTIN placed after the PAN line. */
    public function registrationPairs(): array
    {
        $pairs = [];
        foreach (preg_split('/\R/', (string) $this->registrations, -1, PREG_SPLIT_NO_EMPTY) as $line) {
            [$label, $value] = array_pad(array_map('trim', explode(':', $line, 2)), 2, '');
            $pairs[] = [$label, $value];
        }
        if ($this->gstin && ! collect($pairs)->contains(fn ($p) => stripos($p[0], 'GST') !== false)) {
            $at = collect($pairs)->search(fn ($p) => stripos($p[0], 'PAN') !== false);
            array_splice($pairs, $at === false ? count($pairs) : $at + 1, 0, [['GST No', $this->gstin]]);
        }

        return $pairs;
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
