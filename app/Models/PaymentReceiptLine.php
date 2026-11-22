<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Agency\ScopedToAgency;
use App\Support\Exact;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One sub-bill paid by a receipt: the client's deductions on it and the net received. */
#[Fillable(['agency_id', 'payment_receipt_id', 'invoice_id', 'bill_amount', 'taxable', 'tds_it', 'tds_cgst', 'tds_sgst', 'tds_igst',
    'other_deduction', 'other_reason', 'received', 'form16a_seen', 'gstr7a_accepted'])]
class PaymentReceiptLine extends Model
{
    use LogsActivity, ScopedToAgency;

    public const DEDUCTIONS = ['tds_it' => 'TDS (income tax)', 'tds_cgst' => 'CGST TDS', 'tds_sgst' => 'SGST TDS', 'tds_igst' => 'IGST TDS', 'other_deduction' => 'Other deduction'];

    protected function casts(): array
    {
        return array_fill_keys(['bill_amount', 'taxable', 'tds_it', 'tds_cgst', 'tds_sgst', 'tds_igst', 'other_deduction', 'received'], 'decimal:2')
            + ['form16a_seen' => 'boolean', 'gstr7a_accepted' => 'boolean'];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class, 'payment_receipt_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** All deductions on this line. */
    public function deducted(): string
    {
        return Exact::money(array_reduce(array_keys(self::DEDUCTIONS), fn ($c, $k) => $c->plus(Exact::of((string) $this->{$k})), Exact::zero()));
    }

    /** What this line settles of the sub-bill: received + deductions. */
    public function settled(): string
    {
        return Exact::money(Exact::of((string) $this->received)->plus(Exact::of($this->deducted())));
    }
}
