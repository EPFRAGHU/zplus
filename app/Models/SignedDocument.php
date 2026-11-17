<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Agency\ScopedToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A DSC-signed PDF uploaded back into the app: of a sub-bill, a tax invoice, or an agency's bank letter for a month. */
#[Fillable(['agency_id', 'kind', 'invoice_id', 'tax_invoice_id', 'month', 'path', 'original_name', 'uploaded_by'])]
class SignedDocument extends Model
{
    use LogsActivity, ScopedToAgency;

    public const KINDS = ['sub_bill' => 'Sub-bill', 'tax_invoice' => 'Tax invoice', 'bank_letter' => 'Bank letter'];

    /** Stored as "Y-m-d" (first of the month), like the other month columns. */
    protected function month(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($v) => $v === null ? null : \Carbon\CarbonImmutable::parse($v)->startOfDay(),
            set: fn ($v) => $v === null ? null : \Carbon\CarbonImmutable::parse($v)->startOfMonth()->toDateString(),
        );
    }

    protected static function booted(): void
    {
        static::deleting(fn (SignedDocument $d) => Storage::disk('local')->delete($d->path));
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Latest signed copy of a sub-bill / tax invoice / bank letter, or null. */
    public static function latestFor(string $kind, ?int $invoiceId = null, ?int $taxInvoiceId = null, ?int $agencyId = null, ?string $month = null): ?self
    {
        return static::query()->where('kind', $kind)
            ->when($invoiceId, fn ($q) => $q->where('invoice_id', $invoiceId))
            ->when($taxInvoiceId, fn ($q) => $q->where('tax_invoice_id', $taxInvoiceId))
            ->when($kind === 'bank_letter', fn ($q) => $q->where('agency_id', $agencyId)->where('month', \Carbon\CarbonImmutable::parse($month)->startOfMonth()->toDateString()))
            ->latest('id')->first();
    }
}
