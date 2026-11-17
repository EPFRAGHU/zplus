<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\TaxInvoice;
use App\Services\Mail\AgencyMailer;
use App\Support\Activity\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** "E-mail to client" buttons on the sub-bill and tax invoice pages (owner, 17 Nov 2026). */
class AgencyEmailController extends Controller
{
    public function __construct(private readonly AgencyMailer $mailer) {}

    public function invoice(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('billing');
        $invoice->load('contract.agency');
        [$to, $cc, $subject, $body] = $this->validated($request);

        return $this->deliver($invoice->contract->agency, $to, $cc, $subject, $body, fn () => $this->mailer->documentsForInvoice($invoice),
            ['kind' => 'sub_bill', 'invoice_id' => $invoice->id, 'document_no' => $invoice->invoice_no], "sub-bill {$invoice->invoice_no}");
    }

    public function taxInvoice(Request $request, TaxInvoice $taxInvoice): RedirectResponse
    {
        Gate::authorize('billing');
        $taxInvoice->load('agency');
        [$to, $cc, $subject, $body] = $this->validated($request);

        return $this->deliver($taxInvoice->agency, $to, $cc, $subject, $body, fn () => $this->mailer->documentsForTaxInvoice($taxInvoice),
            ['kind' => 'tax_invoice', 'tax_invoice_id' => $taxInvoice->id, 'document_no' => $taxInvoice->number], "tax invoice {$taxInvoice->number}");
    }

    private function deliver($agency, array $to, array $cc, string $subject, string $body, callable $documents, array $about, string $what): RedirectResponse
    {
        if (! $this->mailer->isConfigured($agency)) {
            return back()->withErrors(['email' => "E-mail is not switched on for {$agency->name}. Its Gmail address and app password must first be added on the server (Coolify)."]);
        }
        try {
            $sent = $this->mailer->send($agency, $to, $cc, $subject, $body, $documents(), $about, (int) auth()->id());
        } catch (RuntimeException $e) {
            Activity::log('updated', null, [], \Illuminate\Support\Str::limit("E-mail of {$what} FAILED (to ".implode(', ', $to).'): '.$e->getMessage(), 490), section: 'E-mail', label: $about['document_no']);

            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }
        $files = count($sent->attachments);
        Activity::log('updated', null, [], \Illuminate\Support\Str::limit("E-mailed {$what} ({$files} PDFs) to ".implode(', ', $to).($cc ? ' (cc '.implode(', ', $cc).')' : ''), 490), section: 'E-mail', label: $about['document_no']);

        return back()->with('status', ucfirst($what).' e-mailed to '.implode(', ', $to).'.');
    }

    /** @return array{0: list<string>, 1: list<string>, 2: string, 3: string} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:1000'],
            'cc' => ['nullable', 'string', 'max:1000'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ], ['to.required' => 'Enter the client\'s e-mail address.']);

        $to = $this->addresses($data['to'], 'to');
        $cc = $this->addresses($data['cc'] ?? '', 'cc');
        if ($to === []) {
            throw ValidationException::withMessages(['to' => 'Enter the client\'s e-mail address.']);
        }

        // Subject is a single line (no header injection); the message is sent as plain text.
        return [$to, $cc, trim(preg_replace('/[\r\n]+/', ' ', $data['subject'])), trim($data['message'])];
    }

    /** "a@x.in, b@y.in; c@z.in" -> validated list (at most 10). */
    private function addresses(string $value, string $field): array
    {
        $list = collect(preg_split('/[,;\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY))->map(fn ($a) => strtolower(trim($a)))->unique()->values();
        $bad = $list->reject(fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL));
        if ($bad->isNotEmpty()) {
            throw ValidationException::withMessages([$field => 'Not a valid e-mail address: '.$bad->implode(', ')]);
        }
        if ($list->count() > 10) {
            throw ValidationException::withMessages([$field => 'At most 10 addresses.']);
        }

        return $list->all();
    }
}
