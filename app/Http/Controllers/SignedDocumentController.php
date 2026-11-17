<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Invoice;
use App\Models\SignedDocument;
use App\Models\TaxInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * DSC-signed copies (owner, 17 Nov 2026): the PDF is signed on the owner's computer with the DSC token (Adobe Reader or
 * JSignPdf) and uploaded back here, so the app keeps the copy that was sent. The latest upload is the one shown.
 */
class SignedDocumentController extends Controller
{
    private const RULES = ['signed' => ['required', 'file', 'mimes:pdf', 'max:10240']];

    private const MESSAGES = ['signed.required' => 'Choose the signed PDF.', 'signed.mimes' => 'Upload the signed PDF file.', 'signed.max' => 'The PDF must be under 10 MB.'];

    public function storeForInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('billing');
        $request->validate(self::RULES, self::MESSAGES);
        $this->save($request, ['agency_id' => $invoice->agency_id, 'kind' => 'sub_bill', 'invoice_id' => $invoice->id], $invoice->invoice_no);

        return back()->with('status', "Signed copy of {$invoice->invoice_no} saved.");
    }

    public function storeForTaxInvoice(Request $request, TaxInvoice $taxInvoice): RedirectResponse
    {
        Gate::authorize('billing');
        $request->validate(self::RULES, self::MESSAGES);
        $this->save($request, ['agency_id' => $taxInvoice->agency_id, 'kind' => 'tax_invoice', 'tax_invoice_id' => $taxInvoice->id], $taxInvoice->number);

        return back()->with('status', "Signed copy of tax invoice {$taxInvoice->number} saved.");
    }

    public function storeForBankLetter(Request $request): RedirectResponse
    {
        Gate::authorize('view-bank-details');
        $request->validate(self::RULES + ['agency' => ['required', new \App\Rules\ExistsInMyAgency(Agency::class)], 'month' => ['required', 'date_format:Y-m']], self::MESSAGES);
        $agency = Agency::query()->findOrFail($request->integer('agency'));
        $month = CarbonImmutable::createFromFormat('!Y-m', $request->input('month'));
        $this->save($request, ['agency_id' => $agency->id, 'kind' => 'bank_letter', 'month' => $month->toDateString()], 'bank letter '.$month->format('M Y'));

        return back()->with('status', "Signed bank letter for {$month->format('F Y')} saved.");
    }

    /** All the agency's PDFs of a month in one ZIP, to sign together with the DSC token. */
    public function zip(Request $request, \App\Services\Billing\MonthDocuments $documents): Response
    {
        Gate::authorize('billing');
        $request->validate(['agency' => ['required', new \App\Rules\ExistsInMyAgency(Agency::class)], 'month' => ['required', 'date_format:Y-m']]);
        $agency = Agency::query()->findOrFail($request->integer('agency'));
        try {
            [$path, $name, $count] = $documents->zip($agency, CarbonImmutable::createFromFormat('!Y-m', $request->input('month')), Gate::allows('view-bank-details'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['zip' => $e->getMessage()]);
        }
        \App\Support\Activity\Activity::log('downloaded', null, [], "Month's PDFs as ZIP: {$name} ({$count} PDFs)", section: 'Signed documents', label: $name);

        return response()->download($path, $name, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    public function show(SignedDocument $signedDocument): Response
    {
        Gate::authorize($signedDocument->kind === 'bank_letter' ? 'view-bank-details' : 'billing');

        return \Illuminate\Support\Facades\Storage::disk('local')->response($signedDocument->path, $signedDocument->original_name);
    }

    private function save(Request $request, array $attributes, string $what): void
    {
        $file = $request->file('signed');
        $path = $file->store('signed/'.now()->format('Y-m'), 'local');
        $name = \App\Support\Codes::fileSafe(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'signed';
        $doc = SignedDocument::query()->create($attributes + ['path' => $path, 'original_name' => $name.'.pdf', 'uploaded_by' => $request->user()->id]);
        \App\Support\Activity\Activity::log('updated', $doc, [], "Signed copy uploaded: {$what}", section: 'Signed documents', label: $what);
    }
}
