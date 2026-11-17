<?php

namespace App\Models;

use App\Support\Agency\ScopedToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One "E-mail to client": a sub-bill or tax invoice sent from the agency's Gmail (or the failed attempt). */
#[Fillable(['agency_id', 'kind', 'invoice_id', 'tax_invoice_id', 'document_no', 'from_address', 'to_addresses', 'cc_addresses',
    'subject', 'attachments', 'status', 'error', 'sent_by'])]
class SentEmail extends Model
{
    use ScopedToAgency;

    protected function casts(): array
    {
        return ['attachments' => 'array'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** Latest e-mails of one sub-bill or tax invoice, newest first. */
    public static function historyFor(?int $invoiceId = null, ?int $taxInvoiceId = null)
    {
        return static::query()->with('sender')
            ->when($invoiceId, fn ($q) => $q->where('invoice_id', $invoiceId))
            ->when($taxInvoiceId, fn ($q) => $q->where('tax_invoice_id', $taxInvoiceId))
            ->latest('id')->limit(10)->get();
    }
}
