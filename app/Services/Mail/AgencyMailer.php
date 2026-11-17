<?php

namespace App\Services\Mail;

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TaxInvoiceController;
use App\Mail\AgencyDocuments;
use App\Models\Agency;
use App\Models\Invoice;
use App\Models\SentEmail;
use App\Models\SignedDocument;
use App\Models\TaxInvoice;
use App\Support\Codes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * "E-mail to client" (owner, 17 Nov 2026): sends a sub-bill or tax invoice from the agency's own Gmail (smtp.gmail.com,
 * Gmail app password from the server's environment). The DSC-signed copy is attached when one was uploaded after the
 * last change; otherwise the PDF is generated now and marked unsigned. Every attempt is recorded (sent_emails).
 * The password is never logged, stored or shown.
 */
final class AgencyMailer
{
    /** @return array{username: string, password: string}|null */
    public function credentials(Agency $agency): ?array
    {
        $key = strtoupper((string) $agency->code);
        $set = config("agency_mail.agencies.{$key}");
        if (! $this->complete($set)) {
            $key = config('agency_mail.prefixes.'.strtoupper((string) $agency->numberPrefix()));
            $set = $key ? config("agency_mail.agencies.{$key}") : null;
        }

        return $this->complete($set) ? ['username' => trim($set['username']), 'password' => preg_replace('/\s+/', '', $set['password'])] : null;
    }

    public function isConfigured(Agency $agency): bool
    {
        return $this->credentials($agency) !== null;
    }

    /** Sub-bill + its covering letter. @return list<array{name: string, data: string, signed: bool}> */
    public function documentsForInvoice(Invoice $invoice): array
    {
        $data = InvoiceController::pdfData($invoice);
        $name = Codes::fileSafe($invoice->invoice_no);

        return [
            $this->signedOr(SignedDocument::latestFor('sub_bill', invoiceId: $invoice->id), $invoice->updated_at, "{$name}.pdf",
                fn () => Pdf::loadView('pdf.invoice', $data)->setPaper('a4')->output()),
            ['name' => "covering-letter-{$name}.pdf", 'data' => Pdf::loadView('pdf.covering-letter', $data)->setPaper('a4')->output(), 'signed' => false],
        ];
    }

    /** Tax invoice + every sub-bill in it. @return list<array{name: string, data: string, signed: bool}> */
    public function documentsForTaxInvoice(TaxInvoice $tax): array
    {
        $docs = [$this->signedOr(SignedDocument::latestFor('tax_invoice', taxInvoiceId: $tax->id), $tax->updated_at, 'tax-invoice-'.Codes::fileSafe($tax->number).'.pdf',
            fn () => Pdf::loadView('pdf.tax-invoice', TaxInvoiceController::pdfData($tax))->setPaper('a4')->output())];
        foreach ($tax->subBills()->orderBy('id')->get() as $bill) {
            $docs[] = $this->signedOr(SignedDocument::latestFor('sub_bill', invoiceId: $bill->id), $bill->updated_at, Codes::fileSafe($bill->invoice_no).'.pdf',
                fn () => Pdf::loadView('pdf.invoice', InvoiceController::pdfData($bill))->setPaper('a4')->output());
        }

        return $docs;
    }

    /** What will be attached, without making the PDFs (for the page). @return list<array{name: string, signed: bool}> */
    public function previewForInvoice(Invoice $invoice): array
    {
        $name = Codes::fileSafe($invoice->invoice_no);

        return [['name' => "{$name}.pdf", 'signed' => $this->isSigned(SignedDocument::latestFor('sub_bill', invoiceId: $invoice->id), $invoice->updated_at)],
            ['name' => "covering-letter-{$name}.pdf", 'signed' => false]];
    }

    public function previewForTaxInvoice(TaxInvoice $tax): array
    {
        $list = [['name' => 'tax-invoice-'.Codes::fileSafe($tax->number).'.pdf', 'signed' => $this->isSigned(SignedDocument::latestFor('tax_invoice', taxInvoiceId: $tax->id), $tax->updated_at)]];
        foreach ($tax->subBills()->orderBy('id')->get() as $bill) {
            $list[] = ['name' => Codes::fileSafe($bill->invoice_no).'.pdf', 'signed' => $this->isSigned(SignedDocument::latestFor('sub_bill', invoiceId: $bill->id), $bill->updated_at)];
        }

        return $list;
    }

    /**
     * Sends and records the e-mail. Throws RuntimeException with a plain message when it could not be sent
     * (the failed attempt is recorded too).
     *
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  array{kind: string, invoice_id?: int, tax_invoice_id?: int, document_no: string}  $about
     */
    public function send(Agency $agency, array $to, array $cc, string $subject, string $body, array $documents, array $about, int $userId): SentEmail
    {
        $credentials = $this->credentials($agency);
        if ($credentials === null) {
            throw new RuntimeException("E-mail is not switched on for {$agency->name}: its Gmail address and app password are not set on the server.");
        }
        $record = ['agency_id' => $agency->id, 'from_address' => $credentials['username'], 'to_addresses' => implode(', ', $to),
            'cc_addresses' => $cc ? implode(', ', $cc) : null, 'subject' => $subject, 'sent_by' => $userId,
            'attachments' => array_map(fn ($d) => ['name' => $d['name'], 'signed' => $d['signed']], $documents)] + $about;

        $mail = new AgencyDocuments($credentials['username'], $agency->name, $subject, $body, $documents);
        try {
            $mailer = config('agency_mail.use_default_mailer') ? Mail::mailer() : Mail::build([
                'transport' => 'smtp', 'scheme' => 'smtp', 'host' => config('agency_mail.host'), 'port' => config('agency_mail.port'),
                'username' => $credentials['username'], 'password' => $credentials['password'], 'timeout' => config('agency_mail.timeout'),
            ]);
            $mailer->to($to)->cc($cc)->send($mail);
        } catch (\Throwable $e) {
            $why = $this->explain($e);
            // Only the exception class goes to the log: SMTP replies can echo the login.
            Log::warning('Agency e-mail failed', ['agency' => $agency->code, 'error' => $e::class]);
            SentEmail::query()->create($record + ['status' => 'failed', 'error' => $why]);
            throw new RuntimeException($why);
        }

        return SentEmail::query()->create($record + ['status' => 'sent']);
    }

    private function complete(mixed $set): bool
    {
        return is_array($set) && filled($set['username'] ?? null) && filled($set['password'] ?? null);
    }

    private function isSigned(?SignedDocument $signed, $changedAt): bool
    {
        return $signed !== null && ! ($changedAt && $changedAt->gt($signed->created_at)) && Storage::disk('local')->exists($signed->path);
    }

    private function signedOr(?SignedDocument $signed, $changedAt, string $name, callable $generate): array
    {
        return $this->isSigned($signed, $changedAt)
            ? ['name' => $name, 'data' => Storage::disk('local')->get($signed->path), 'signed' => true]
            : ['name' => $name, 'data' => $generate(), 'signed' => false];
    }

    /** A plain message for the operator; never includes the password. */
    private function explain(\Throwable $e): string
    {
        $m = $e->getMessage();

        return match (true) {
            str_contains($m, '535') || stripos($m, 'Username and Password not accepted') !== false || stripos($m, 'authenticat') !== false
                => 'Gmail did not accept the login. Check the Gmail address and the 16-letter app password set on the server (2-Step Verification must be on).',
            stripos($m, 'timed out') !== false || stripos($m, 'Connection could not be established') !== false || stripos($m, 'Unable to connect') !== false
                => 'Could not reach Gmail (network problem or timeout). Try again in a few minutes.',
            str_contains($m, '552') || stripos($m, 'size') !== false
                => 'Gmail refused the e-mail because the attachments are too large (limit 25 MB).',
            str_contains($m, '550') || str_contains($m, '553')
                => 'Gmail refused an address. Check the e-mail addresses and try again.',
            default => 'The e-mail could not be sent ('.class_basename($e).'). Nothing was sent; try again.',
        };
    }
}
