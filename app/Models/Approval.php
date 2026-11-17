<?php

namespace App\Models;

use App\Support\Agency\ScopedToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One "Send for approval" of attendance, a sub-bill, a tax invoice or a month's salary payout (maker–checker). */
#[Fillable(['agency_id', 'type', 'attendance_sheet_id', 'invoice_id', 'tax_invoice_id', 'month', 'label', 'fingerprint', 'details',
    'status', 'submitted_by', 'submitted_at', 'decided_by', 'decided_at', 'reason'])]
class Approval extends Model
{
    use ScopedToAgency;

    public const TYPES = ['attendance' => 'Attendance', 'sub_bill' => 'Sub-bill', 'tax_invoice' => 'Tax invoice', 'payout' => 'Salary payout & bank letter'];

    public const STATUSES = ['pending' => 'Waiting for approval', 'approved' => 'Approved', 'returned' => 'Returned', 'superseded' => 'Sent again'];

    protected function casts(): array
    {
        return ['details' => 'array', 'submitted_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    protected function month(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($v) => $v === null ? null : \Carbon\CarbonImmutable::parse($v)->startOfDay(),
            set: fn ($v) => $v === null ? null : \Carbon\CarbonImmutable::parse($v)->startOfMonth()->toDateString(),
        );
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(AttendanceSheet::class, 'attendance_sheet_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function taxInvoice(): BelongsTo
    {
        return $this->belongsTo(TaxInvoice::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Where to look at the thing being approved. */
    public function url(): ?string
    {
        return match ($this->type) {
            'attendance' => $this->attendance_sheet_id ? route('attendance.show', $this->attendance_sheet_id) : null,
            'sub_bill' => $this->invoice_id ? route('invoices.show', $this->invoice_id) : null,
            'tax_invoice' => $this->tax_invoice_id ? route('tax-invoices.show', $this->tax_invoice_id) : null,
            'payout' => route('bank-letters.index', ['agency' => $this->agency_id, 'month' => $this->month->format('Y-m')]),
            default => null,
        };
    }
}
