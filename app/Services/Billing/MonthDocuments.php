<?php

namespace App\Services\Billing;

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TaxInvoiceController;
use App\Models\Agency;
use App\Models\Invoice;
use App\Models\TaxInvoice;
use App\Services\Payroll\BankLetter;
use App\Support\Codes;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use RuntimeException;
use ZipArchive;

/**
 * One ZIP with all of an agency's PDFs for a month, to sign together with the DSC token (e.g. JSignPdf):
 * every sub-bill, its covering letter, every tax invoice and (if allowed) the bank letter.
 */
final class MonthDocuments
{
    public function __construct(private readonly BankLetter $letters) {}

    /** @return array{0: string, 1: string, 2: int} [temp file path, download name, number of PDFs] */
    public function zip(Agency $agency, CarbonImmutable $month, bool $withBankLetter): array
    {
        $month = $month->startOfMonth();
        $bills = Invoice::query()->where('agency_id', $agency->id)->where('month', $month->toDateString())->orderBy('invoice_no')->get();
        $taxes = TaxInvoice::query()->where('agency_id', $agency->id)->where('month', $month->toDateString())->orderBy('number')->get();
        if ($bills->isEmpty() && $taxes->isEmpty()) {
            throw new RuntimeException("No sub-bills or tax invoices for {$agency->name} in {$month->format('F Y')}.");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'zplus-zip-');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not make the ZIP file.');
        }
        $count = 0;
        foreach ($bills as $bill) {
            $data = InvoiceController::pdfData($bill);
            $zip->addFromString('sub-bills/'.Codes::fileSafe($bill->invoice_no).'.pdf', Pdf::loadView('pdf.invoice', $data)->setPaper('a4')->output());
            $zip->addFromString('covering-letters/covering-letter-'.Codes::fileSafe($bill->invoice_no).'.pdf', Pdf::loadView('pdf.covering-letter', $data)->setPaper('a4')->output());
            $count += 2;
        }
        foreach ($taxes as $tax) {
            $zip->addFromString('tax-invoices/'.Codes::fileSafe($tax->number).'.pdf', Pdf::loadView('pdf.tax-invoice', TaxInvoiceController::pdfData($tax))->setPaper('a4')->output());
            $count++;
        }
        if ($withBankLetter) {
            [$locked] = $this->letters->sheets($agency, $month);
            if ($locked->isNotEmpty()) {
                $zip->addFromString('bank-letter-'.$month->format('Y-m').'.pdf', Pdf::loadView('pdf.bank-letter', $this->letters->build($agency, $month, $locked))->setPaper('a4')->output());
                $count++;
            }
        }
        $zip->close();

        return [$tmp, $agency->numberPrefix().'-documents-'.$month->format('Y-m').'.zip', $count];
    }
}
