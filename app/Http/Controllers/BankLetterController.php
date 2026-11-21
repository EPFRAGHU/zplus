<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Services\Payroll\BankLetter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bank Letter: one salary transfer letter per agency and month, for all the employees of all its projects whose month
 * is locked (or the ticked projects), on the agency's own letterhead.
 */
class BankLetterController extends Controller
{
    public function __construct(private readonly BankLetter $letters) {}

    public function index(Request $request): View
    {
        Gate::authorize('view-bank-details');
        $agencies = Agency::query()->orderBy('name')->get();
        $agency = $agencies->firstWhere('id', $request->integer('agency')) ?? ($agencies->count() === 1 ? $agencies->first() : null);
        $month = $this->month($request->input('month'));
        $data = ['agencies' => $agencies, 'agency' => $agency, 'month' => $month, 'letter' => null, 'pending' => collect(), 'all' => collect()];
        if ($agency) {
            [$locked, $pending] = $this->letters->sheets($agency, $month);
            $chosen = $request->has('projects') ? $locked->whereIn('project_id', array_map('intval', (array) $request->input('projects')))->values() : $locked;
            $data = ['letter' => $this->letters->build($agency, $month, $chosen), 'pending' => $pending, 'all' => $locked, 'chosen' => $chosen->pluck('project_id')->all()] + $data;
        }

        return view('bank-letters.index', $data);
    }

    public function pdf(Request $request): Response
    {
        $data = $this->approvedLetter($request);

        return Pdf::loadView('pdf.bank-letter', $data)->setPaper('a4')
            ->stream('bank-letter-'.$data['agency']->numberPrefix().'-'.$data['month']->format('Y-m').'.pdf');
    }

    /** The same letter as an Excel file (owner, 22 Nov 2026): one sheet for SBI accounts, one for NEFT, one for missing bank details. */
    public function excel(Request $request): Response
    {
        $data = $this->approvedLetter($request);
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $book->removeSheetByIndex(0);
        $sheets = ['SBI' => $data['sbi'], 'NEFT' => $data['neft'], 'Missing bank details' => $data['missing']];
        foreach ($sheets as $title => $lines) {
            $ws = $book->createSheet()->setTitle($title);
            $ws->fromArray([[$data['agency']->name.' · salary for '.$data['month']->format('F Y').' · '.$title], ['Ref: '.$data['reference']]], null, 'A1');
            $ws->fromArray([['Sl.', 'Emp ID', 'Name as per bank', 'Project', 'Bank', 'IFSC', 'Account no.', 'Net salary (Rs.)']], null, 'A4');
            $row = 5;
            foreach ($lines->values() as $i => $l) {
                $e = $l['employee'];
                $cells = [$i + 1, $e->emp_code, $e->account_holder ?: $e->name, $l['project']->code, $e->bank_name, $e->ifsc, (string) $e->account_number];
                foreach ($cells as $c => $value) {
                    // Text, never a formula or a number in scientific form (account numbers, names typed by users).
                    $ws->setCellValueExplicit([$c + 1, $row], (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $ws->setCellValue([8, $row], $l['net'] / 100);
                $row++;
            }
            if ($lines->isNotEmpty()) {
                $ws->setCellValue([7, $row], 'Total');
                $ws->setCellValue([8, $row], $lines->sum('net') / 100);
                $ws->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
            }
            $ws->getStyle('A4:H4')->getFont()->setBold(true);
            $ws->getStyle('A1')->getFont()->setBold(true);
            $ws->getStyle('H5:H'.max(5, $row))->getNumberFormat()->setFormatCode('#,##0.00');
            foreach (range('A', 'H') as $col) {
                $ws->getColumnDimension($col)->setAutoSize(true);
            }
        }
        $book->setActiveSheetIndex(0);
        $file = tempnam(sys_get_temp_dir(), 'bank');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file);

        return response()->download($file, 'bank-letter-'.$data['agency']->numberPrefix().'-'.$data['month']->format('Y-m').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /** Bank details leave the app only after the owner has approved the month's salary payout. */
    private function approvedLetter(Request $request): array
    {
        Gate::authorize('view-bank-details');
        $request->validate(['agency' => ['required', new \App\Rules\ExistsInMyAgency(Agency::class)], 'month' => ['required', 'date_format:Y-m'],
            'projects' => ['nullable', 'array'], 'projects.*' => ['integer']]);
        $agency = Agency::query()->findOrFail($request->integer('agency'));
        $month = $this->month($request->input('month'));
        [$locked] = $this->letters->sheets($agency, $month, $request->input('projects') ? array_map('intval', (array) $request->input('projects')) : null);
        abort_if($locked->isEmpty(), 422, 'No locked attendance for this agency and month.');
        $approvals = app(\App\Services\Approvals\Approvals::class);
        abort_unless(\App\Services\Approvals\Approvals::canApprove($request->user()) || $locked->every(fn ($s) => $approvals->payoutClearedFor($s)), 403,
            'The salary payout for this month is not approved by the owner yet. Use "Send for approval" on the Bank Letter page.');

        return $this->letters->build($agency, $month, $locked);
    }

    private function month(?string $value): CarbonImmutable
    {
        return $value && preg_match('/^\d{4}-\d{2}$/', $value) ? CarbonImmutable::createFromFormat('!Y-m', $value)
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    }
}
