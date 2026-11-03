<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['emp_code', 'agency_id', 'client_id', 'post_id', 'name', 'father_name', 'dob', 'gender', 'mobile', 'whatsapp', 'email', 'address',
    'qualification', 'joining_date', 'exit_date', 'status', 'rate_override', 'bank_name', 'account_holder',
    'account_number', 'ifsc', 'upi_id', 'pan', 'aadhaar', 'uan', 'esic_ip', 'pf_applicable', 'esic_applicable'])]
#[Hidden(['account_number', 'aadhaar'])]
class Employee extends Model
{
    use LogsActivity;

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
            'pf_applicable' => 'boolean', 'esic_applicable' => 'boolean', 'rate_override' => 'decimal:2',
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

    public static function nextCode(): string
    {
        $last = static::query()->where('emp_code', 'like', 'EMP-%')->orderByDesc('id')->value('emp_code');
        $n = $last ? ((int) substr($last, 4)) + 1 : 1001;

        return 'EMP-'.$n;
    }
}
