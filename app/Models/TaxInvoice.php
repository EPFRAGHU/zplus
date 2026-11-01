<?php

namespace App\Models;

use App\Support\AmountInWords;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One client's tax invoice for a month, combining that month's sub-bills (one per project). */
#[Fillable(['agency_id', 'client_id', 'number', 'month', 'invoice_date', 'place_of_supply', 'taxable', 'cgst', 'sgst', 'igst',
    'total', 'rounded_total', 'status', 'created_by'])]
class TaxInvoice extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'submitted' => 'Submitted', 'paid' => 'Paid'];

    protected function casts(): array
    {
        $money = array_fill_keys(['taxable', 'cgst', 'sgst', 'igst', 'total', 'rounded_total'], 'decimal:2');

        return $money + ['invoice_date' => 'date', 'paid_on' => 'date'];
    }

    /** Stored as "Y-m-d" (first of the month), like sub-bills. */
    protected function month(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v === null ? null : CarbonImmutable::parse($v)->startOfDay(),
            set: fn ($v) => $v === null ? null : CarbonImmutable::parse($v)->startOfMonth()->toDateString(),
        );
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** The sub-bills combined in this tax invoice. */
    public function subBills(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function amountInWords(): string
    {
        return AmountInWords::rupees((string) $this->rounded_total);
    }
}
