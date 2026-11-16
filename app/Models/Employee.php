<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Agency\ScopedToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['emp_code', 'agency_id', 'client_id', 'post_id', 'name', 'father_name', 'dob', 'gender', 'mobile', 'whatsapp', 'email', 'address',
    'qualification', 'joining_date', 'exit_date', 'status', 'rate_override', 'bank_name', 'account_holder',
    'account_number', 'ifsc', 'upi_id', 'pan', 'aadhaar', 'uan', 'esic_ip', 'pf_applicable', 'esic_applicable', 'tds_applicable'])]
#[Hidden(['account_number', 'aadhaar'])]
class Employee extends Model
{
    use LogsActivity, ScopedToAgency;

    public const STATUSES = ['active' => 'Active', 'notice' => 'On notice', 'inactive' => 'Inactive'];

    public const DOCUMENT_TYPES = [
        'aadhaar' => 'Aadhaar card', 'pan' => 'PAN card', 'photo' => 'Passport photo', 'bank_proof' => 'Bank passbook / statement',
        'police_verification' => 'Police verification', 'education' => 'Education certificate',
        'appointment_signed' => 'Signed appointment letter', 'medical' => 'Medical fitness',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date', 'joining_date' => 'date', 'exit_date' => 'date', 'bank_verified_at' => 'datetime',
            'account_number' => 'encrypted', 'aadhaar' => 'encrypted',
            'pf_applicable' => 'boolean', 'esic_applicable' => 'boolean', 'tds_applicable' => 'boolean', 'rate_override' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Employee $e) {
            if ($e->isDirty('account_number')) {
                $digits = preg_replace('/\D/', '', (string) $e->account_number);
                $e->account_last4 = $digits === '' ? null : substr($digits, -4);
                if ($e->getOriginal('account_number') !== null) {
                    $e->bank_verified_at = null;   // a changed account must be verified again
                }
            }
            if ($e->isDirty('aadhaar')) {
                $digits = preg_replace('/\D/', '', (string) $e->aadhaar);
                $e->aadhaar_last4 = $digits === '' ? null : substr($digits, -4);
            }
        });
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /** Monthly (or daily) rate used for billing. */
    public function rate(): string
    {
        return (string) ($this->rate_override ?? $this->post?->rate ?? '0');
    }

    public function maskedAccount(): ?string
    {
        return $this->account_last4 ? '••••'.$this->account_last4 : null;
    }

    /**
     * Next employee ID of an agency: SW-1001, SW-1002 … (ZP-1001 … for Z Plus), from the agency's number prefix.
     * Codes are unique across both agencies, so every employee is looked at. Without an agency: EMP-1001 … as before.
     */
    public static function nextCode(Agency|int|null $agency = null): string
    {
        $agency = is_int($agency) ? \App\Support\Agency\AgencyContext::unscoped(fn () => Agency::query()->find($agency)) : $agency;
        $prefix = ($agency?->numberPrefix() ?? 'EMP').'-';
        $codes = \App\Support\Agency\AgencyContext::unscoped(fn () => static::query()->where('emp_code', 'like', $prefix.'%')->pluck('emp_code'));
        $max = $codes->map(fn ($c) => substr($c, strlen($prefix)))->filter(fn ($n) => ctype_digit($n))->map(fn ($n) => (int) $n)->max();

        return $prefix.max(1001, ($max ?? 1000) + 1);
    }
}
