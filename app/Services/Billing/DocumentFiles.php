<?php

namespace App\Services\Billing;

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TaxInvoiceController;
use App\Models\Invoice;
use App\Models\SignedDocument;
use App\Models\TaxInvoice;
use App\Support\Codes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * The PDF files of a bill, for e-mails and the GeM bill pack: the DSC-signed copy when one was uploaded after the
 * bill's last change, otherwise generated now (marked unsigned). Each file: ['name' => ..., 'data' => ..., 'signed' => bool].
 */
final class DocumentFiles
{
    public function subBill(Invoice $invoice): array
    {
        return $this->signedOr(SignedDocument::latestFor('sub_bill', invoiceId: $invoice->id), $invoice->updated_at, Codes::fileSafe($invoice->invoice_no).'.pdf',
            fn () => Pdf::loadView('pdf.invoice', InvoiceController::pdfData($invoice))->setPaper('a4')->output());
    }

    public function coveringLetter(Invoice $invoice): array
    {
        return ['name' => 'covering-letter-'.Codes::fileSafe($invoice->invoice_no).'.pdf', 'signed' => false,
            'data' => Pdf::loadView('pdf.covering-letter', InvoiceController::pdfData($invoice))->setPaper('a4')->output()];
    }

    public function taxInvoice(TaxInvoice $tax): array
    {
        return $this->signedOr(SignedDocument::latestFor('tax_invoice', taxInvoiceId: $tax->id), $tax->updated_at, 'tax-invoice-'.Codes::fileSafe($tax->number).'.pdf',
            fn () => Pdf::loadView('pdf.tax-invoice', TaxInvoiceController::pdfData($tax))->setPaper('a4')->output());
    }

    public function isSigned(?SignedDocument $signed, $changedAt): bool
    {
        return $signed !== null && ! ($changedAt && $changedAt->gt($signed->created_at)) && Storage::disk('local')->exists($signed->path);
    }

    private function signedOr(?SignedDocument $signed, $changedAt, string $name, callable $generate): array
    {
        return $this->isSigned($signed, $changedAt)
            ? ['name' => $name, 'data' => Storage::disk('local')->get($signed->path), 'signed' => true]
            : ['name' => $name, 'data' => $generate(), 'signed' => false];
    }
}
