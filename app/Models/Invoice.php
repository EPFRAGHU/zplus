<?php

namespace App\Models;

use App\Support\AmountInWords;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['agency_id', 'invoice_no', 'gem_contract_id', 'project_id', 'attendance_sheet_id', 'month', 'invoice_date', 'billing_type',
    'service_charge_percent', 'gst_percent', 'wages', 'epf', 'esic', 'subtotal', 'service_charge', 'taxable',
    'cgst', 'sgst', 'igst', 'total', 'rounded_total', 'status', 'created_by'])]
class Invoice extends Model
{
    /**
     * Always store the month as "Y-m-d" (first of the month). A plain date cast saves
     * "Y-m-d 00:00:00", which on SQLite no longer matches lookups by "Y-m-d".
     */
    protected function month(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : CarbonImmutable::parse($value)->startOfDay(),
            set: fn ($value) => $value === null ? null : CarbonImmutable::parse($value)->startOfMonth()->toDateString(),
        );
    }

    public const STATUSES = ['draft' => 'Draft', 'approved' => 'Approved', 'submitted' => 'Submitted', 'paid' => 'Paid'];

    protected function casts(): array
    {
        $money = array_fill_keys(['wages', 'epf', 'esic', 'subtotal', 'service_charge', 'taxable', 'cgst', 'sgst', 'igst', 'total', 'rounded_total'], 'decimal:2');

        return $money + ['invoice_date' => 'date', 'approved_at' => 'datetime', 'paid_on' => 'date'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(GemContract::class, 'gem_contract_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(AttendanceSheet::class, 'attendance_sheet_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function amountInWords(): string
    {
        return AmountInWords::rupees((string) $this->rounded_total);
    }
}
