<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Agency\ScopedToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'code', 'number_prefix', 'tagline', 'registrations', 'address', 'email', 'website', 'letterhead_emails', 'phone', 'phones', 'logo_path', 'letterhead_style', 'gstin', 'pan', 'signatory_name', 'signatory_title', 'bank_name', 'bank_account_no', 'bank_ifsc', 'bank_branch'])]
class Agency extends Model
{
    use LogsActivity, ScopedToAgency;

    /** @var array<string, string> logo data URIs already made in this request (the month ZIP prints the logo on every PDF) */
    private static array $logoCache = [];

    /**
     * Logo as a data URI for PDFs (dompdf cannot read private storage paths), or null. A large upload (e.g. a 7 MB scan)
     * is shrunk to at most 1200 px wide for printing, once per request: every PDF used to decode the full image, which made
     * the month ZIP run out of time and memory (owner, 22 Nov 2026). The uploaded file itself is never changed.
     */
    public function logoDataUri(): ?string
    {
        $disk = Storage::disk('local');
        if (! $this->logo_path || ! $disk->exists($this->logo_path)) {
            return null;
        }
        $key = $this->logo_path.'|'.$disk->lastModified($this->logo_path);
        if (isset(self::$logoCache[$key])) {
            return self::$logoCache[$key];
        }
        $bytes = $disk->get($this->logo_path);
        $ext = strtolower(pathinfo($this->logo_path, PATHINFO_EXTENSION));
        $mime = match ($ext) { 'jpg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', default => 'image/png' };
        if (strlen($bytes) > 400 * 1024 && $mime !== 'image/svg+xml') {
            [$bytes, $mime] = self::shrinkForPrint($bytes, $mime);
        }

        return self::$logoCache[$key] = 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    /** @return array{0: string, 1: string} smaller image bytes and type, or the original when GD is not available or fails */
    private static function shrinkForPrint(string $bytes, string $mime): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return [$bytes, $mime];
        }
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return [$bytes, $mime];
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > 1200) {
            $small = imagescale($img, 1200, (int) round($h * 1200 / $w));
            imagedestroy($img);
            $img = $small ?: $img;
        }
        imagesavealpha($img, true);
        ob_start();
        imagepng($img, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png !== '' && strlen($png) < strlen($bytes) ? [$png, 'image/png'] : [$bytes, $mime];
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

    /** Prefix of bill, tax invoice and employee numbers: SW/001/26-27, SW-1001. */
    public function numberPrefix(): string
    {
        return strtoupper($this->number_prefix ?: substr(preg_replace('/[^A-Za-z]/', '', (string) $this->code), 0, 2) ?: 'AG');
    }
}
