<?php

namespace App\Services\Billing;

use App\Models\AttendanceSheet;
use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\MonthlyPaper;
use App\Support\Codes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Everything GeM asks for with one work order's monthly bill, in one ZIP (owner, 17 Nov 2026): the tax invoice,
 * sub-bills and covering letters (DSC-signed copies where uploaded), the PI attendance certificates, and the
 * month's papers (EPF / ESIC challans, salary payment proof, logbook, feedback).
 */
final class BillPack
{
    public function __construct(private readonly DocumentFiles $files) {}

    /** @return array{0: string, 1: string, 2: int, 3: list<string>} [temp file, download name, number of files, what is missing] */
    public function zip(GemContract $contract, CarbonImmutable $month, bool $withBankPapers): array
    {
        $month = $month->startOfMonth();
        $bills = Invoice::query()->where('gem_contract_id', $contract->id)->where('month', $month->toDateString())->orderBy('invoice_no')->get();
        $taxes = $contract->taxInvoices()->where('month', $month->toDateString())->orderBy('number')->get();
        $papers = MonthlyPaper::query()->where('gem_contract_id', $contract->id)->where('month', $month->toDateString())->orderBy('kind')->orderBy('id')->get();
        if ($bills->isEmpty() && $taxes->isEmpty() && $papers->isEmpty()) {
            throw new RuntimeException("Nothing yet for {$month->format('F Y')}: no sub-bills, tax invoice or papers on this work order.");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'zplus-pack-');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not make the ZIP file.');
        }
        $count = 0;
        $add = function (string $path, string $data) use ($zip, &$count) {
            $zip->addFromString($path, $data);
            $count++;
        };
        foreach ($taxes as $tax) {
            $f = $this->files->taxInvoice($tax);
            $add('1-tax-invoice/'.$f['name'], $f['data']);
        }
        foreach ($bills as $bill) {
            $f = $this->files->subBill($bill);
            $add('2-sub-bills/'.$f['name'], $f['data']);
            $f = $this->files->coveringLetter($bill);
            $add('2-sub-bills/'.$f['name'], $f['data']);
        }
        $sheets = AttendanceSheet::query()->with('project')->whereIn('project_id', $contract->projects()->pluck('id'))->where('month', $month->toDateString())->get();
        foreach ($sheets as $sheet) {
            foreach (['certificate_path' => 'pi-certificate', 'submitted_pdf_path' => 'attendance-as-submitted'] as $column => $label) {
                if ($sheet->{$column} && Storage::disk('local')->exists($sheet->{$column})) {
                    $ext = pathinfo($sheet->{$column}, PATHINFO_EXTENSION) ?: 'pdf';
                    $add('3-attendance/'.Codes::fileSafe($sheet->project->code)."-{$label}.{$ext}", Storage::disk('local')->get($sheet->{$column}));
                }
            }
        }
        $n = [];
        foreach ($papers as $paper) {
            if (in_array($paper->kind, MonthlyPaper::BANK_KINDS, true) && ! $withBankPapers) {
                continue;
            }
            if (! Storage::disk('local')->exists($paper->path)) {
                continue;
            }
            $n[$paper->kind] = ($n[$paper->kind] ?? 0) + 1;
            $ext = strtolower(pathinfo($paper->original_name, PATHINFO_EXTENSION)) ?: 'pdf';
            $add("4-papers/{$paper->kind}-{$n[$paper->kind]}.{$ext}", Storage::disk('local')->get($paper->path));
        }
        $zip->close();

        $missing = collect(MonthlyPaper::CHECKLIST)->reject(fn ($k) => $papers->contains('kind', $k))->map(fn ($k) => MonthlyPaper::KINDS[$k])->values()->all();
        if ($taxes->isEmpty()) {
            array_unshift($missing, 'Tax invoice');
        }

        return [$tmp, 'bill-pack-'.Codes::fileSafe($contract->contract_no).'-'.$month->format('Y-m').'.zip', $count, $missing];
    }
}
