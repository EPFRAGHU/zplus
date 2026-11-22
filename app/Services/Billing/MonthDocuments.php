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
    public function zip(Agency $agency, CarbonImmutable $month, bool $withBankLetter, ?\App\Models\GemContract $contract = null): array
    {
        $month = $month->startOfMonth();
        // One work order only (owner, 22 Nov 2026), or the whole month with a folder per work order.
        $bills = Invoice::query()->with('contract')->where('agency_id', $agency->id)->where('month', $month->toDateString())
            ->when($contract, fn ($q) => $q->where('gem_contract_id', $contract->id))->orderBy('invoice_no')->get();
        $taxes = TaxInvoice::query()->with('contract')->where('agency_id', $agency->id)->where('month', $month->toDateString())
            ->when($contract, fn ($q) => $q->where('gem_contract_id', $contract->id))->orderBy('number')->get();
        if ($bills->isEmpty() && $taxes->isEmpty()) {
            throw new RuntimeException('No sub-bills or tax invoices for '.($contract ? "work order {$contract->contract_no}" : $agency->name)." in {$month->format('F Y')}.");
        }
        $folder = fn ($k) => $contract ? '' : Codes::fileSafe((string) ($k?->contract_no ?: 'no-work-order')).'/';
        $withBankLetter = $withBankLetter && ! $contract;   // the bank letter covers the whole agency, so only in the month ZIP

        // Each PDF goes to its own temp file as soon as it is made (addFromString would keep them all in memory until the
        // ZIP is closed: a month of 50+ PDFs ran out of memory, owner 22 Nov 2026). A higher limit just for this download.
        self::raiseMemoryLimit('512M');
        @set_time_limit(600);   // a full month (50+ PDFs) takes longer than PHP's usual 30 seconds (owner, 22 Nov 2026)
        $tmp = tempnam(sys_get_temp_dir(), 'zplus-zip-');
        $dir = $tmp.'-files';
        @mkdir($dir, 0700, true);
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not make the ZIP file.');
        }
        $parts = [];
        $add = function (string $name, string $view, array $data) use ($zip, $dir, &$parts) {
            $file = $dir.'/'.count($parts).'.pdf';
            file_put_contents($file, Pdf::loadView($view, $data)->setPaper('a4')->output());
            $zip->addFile($file, $name);
            $parts[] = $file;
            gc_collect_cycles();
        };
        try {
            foreach ($bills as $bill) {
                $data = InvoiceController::pdfData($bill);
                $add($folder($bill->contract).'sub-bills/'.Codes::fileSafe($bill->invoice_no).'.pdf', 'pdf.invoice', $data);
                $add($folder($bill->contract).'covering-letters/covering-letter-'.Codes::fileSafe($bill->invoice_no).'.pdf', 'pdf.covering-letter', $data);
                unset($data);
            }
            foreach ($taxes as $tax) {
                $add($folder($tax->contract).'tax-invoice/'.Codes::fileSafe($tax->number).'.pdf', 'pdf.tax-invoice', TaxInvoiceController::pdfData($tax));
            }
            if ($withBankLetter) {
                [$locked] = $this->letters->sheets($agency, $month);
                if ($locked->isNotEmpty()) {
                    $add('bank-letter-'.$month->format('Y-m').'.pdf', 'pdf.bank-letter', $this->letters->build($agency, $month, $locked));
                }
            }
            $count = count($parts);
            $zip->close();   // reads the temp files into the ZIP one by one
        } finally {
            foreach ($parts as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }

        return [$tmp, $agency->numberPrefix().'-'.($contract ? Codes::fileSafe($contract->contract_no).'-' : '').'documents-'.$month->format('Y-m').'.zip', $count];
    }

    /** Raises PHP's memory limit for this request only, never lowers it (-1 = no limit stays). */
    private static function raiseMemoryLimit(string $limit): void
    {
        $current = (string) ini_get('memory_limit');
        $bytes = fn (string $v) => $v === '-1' ? PHP_INT_MAX : (int) $v * match (strtolower(substr($v, -1))) { 'g' => 1 << 30, 'm' => 1 << 20, 'k' => 1 << 10, default => 1 };
        if ($bytes($current) < $bytes($limit)) {
            ini_set('memory_limit', $limit);
        }
    }
}
