<?php

namespace App\Http\Controllers;

use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Services\Approvals\Approvals;
use App\Services\Payments\PaymentReports;
use App\Services\Payments\Receipts;
use App\Support\Activity\Activity;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payments received from the client (owner, 22 Nov 2026): receipts (UTR / cheque, dates, account, advice) split over the
 * sub-bills they pay with the client's TDS / GST TDS / other deductions; outstanding and TDS registers with Excel and PDF.
 */
class PaymentReceiptController extends Controller
{
    public const TABS = ['receipts' => 'Receipts', 'outstanding' => 'Outstanding', 'tds' => 'TDS & GST TDS'];

    public function index(Request $request, PaymentReports $reports): View
    {
        Gate::authorize('billing');
        $f = $this->filters($request);
        $receipts = $reports->receipts($f['from'], $f['to'], $f['contract']?->id);
        $approvedLines = $receipts->where('status', 'approved')->flatMap->lines;

        return view('payments.index', $f + [
            'receipts' => $receipts,
            'outstanding' => $reports->outstanding($f['contract']?->id),
            'tds' => $f['tab'] === 'tds' ? $reports->tds($f['from'], $f['to'], $f['contract']?->id, $request->boolean('pending')) : collect(),
            'kpi' => PaymentReports::lineTotals($approvedLines) + ['waiting' => PaymentReceipt::query()->where('status', 'pending')->count()],
            'contracts' => GemContract::query()->with('client:id,name,short_code', 'agency:id,code')->orderBy('contract_no')->get(['id', 'contract_no', 'agency_id', 'client_id']),
            'tabs' => self::TABS,
        ]);
    }

    public function create(Request $request, Receipts $receipts): View
    {
        Gate::authorize('billing');
        $contract = $request->integer('contract') ? GemContract::query()->with('agency', 'client')->findOrFail($request->integer('contract')) : null;

        return $this->form(new PaymentReceipt(['mode' => 'neft', 'credit_date' => now()->toDateString()]), $contract, $receipts, (array) $request->input('bills', []));
    }

    public function store(Request $request, Receipts $receipts): RedirectResponse
    {
        Gate::authorize('billing');
        $contract = GemContract::query()->findOrFail($request->integer('gem_contract_id'));
        try {
            $receipt = $receipts->save(null, $contract, $this->validated($request), $this->lines($request), $request->user(), $request->file('advice'));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['receipt' => $e->getMessage()]);
        }

        return redirect()->route('payments.show', $receipt)->with('status', 'Payment saved. A Super Admin (not you) approves it; then the sub-bills it settles in full turn Paid.');
    }

    public function show(PaymentReceipt $receipt, Receipts $receipts): View
    {
        Gate::authorize('billing');
        $receipt->load('lines.invoice.project', 'lines.invoice.taxInvoice', 'contract', 'agency', 'client', 'enteredBy', 'approvedBy');

        return view('payments.show', ['receipt' => $receipt, 'receipts' => $receipts, 'canApprove' => Approvals::canApprove(request()->user()) && $receipt->entered_by !== request()->user()->id,
            'totals' => PaymentReports::lineTotals($receipt->lines)]);
    }

    public function edit(PaymentReceipt $receipt, Receipts $receipts): View|RedirectResponse
    {
        Gate::authorize('billing');
        if ($receipt->status === 'approved') {
            return redirect()->route('payments.show', $receipt)->withErrors(['receipt' => 'This receipt is approved, so it cannot be changed. A Super Admin can delete it, then enter it again.']);
        }

        return $this->form($receipt->load('lines'), $receipt->contract()->with('agency', 'client')->first(), $receipts, []);
    }

    public function update(Request $request, PaymentReceipt $receipt, Receipts $receipts): RedirectResponse
    {
        Gate::authorize('billing');
        try {
            $receipts->save($receipt, $receipt->contract, $this->validated($request), $this->lines($request), $request->user(), $request->file('advice'));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['receipt' => $e->getMessage()]);
        }

        return redirect()->route('payments.show', $receipt)->with('status', 'Payment changed. It waits for a Super Admin\'s approval again.');
    }

    public function approve(Request $request, PaymentReceipt $receipt, Receipts $receipts): RedirectResponse
    {
        Gate::authorize('billing');
        try {
            $notes = $receipts->approve($receipt, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return back()->with('status', 'Payment approved.'.($notes ? ' '.ucfirst(implode('; ', $notes)).'.' : ' No sub-bill is paid in full yet.'));
    }

    public function sendBack(Request $request, PaymentReceipt $receipt, Receipts $receipts): RedirectResponse
    {
        Gate::authorize('billing');
        $data = $request->validate(['note' => ['required', 'string', 'max:500']], ['note.required' => 'Write why it is returned.']);
        try {
            $receipts->sendBack($receipt, $request->user(), $data['note']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return back()->with('status', 'Payment returned to Accounts to correct.');
    }

    public function destroy(PaymentReceipt $receipt, Receipts $receipts): RedirectResponse
    {
        Gate::authorize('delete-records');   // Super Admin
        $label = $receipt->label();
        $notes = $receipts->delete($receipt);

        return redirect()->route('payments.index')->with('status', "Payment deleted: {$label}.".($notes ? ' '.ucfirst(implode('; ', $notes)).'.' : ''));
    }

    public function advice(PaymentReceipt $receipt): Response
    {
        Gate::authorize('billing');
        abort_unless($receipt->advice_path && Storage::disk('local')->exists($receipt->advice_path), 404);

        return Storage::disk('local')->response($receipt->advice_path, $receipt->advice_name);
    }

    /** Ticks: TDS seen in Form 16A / 26AS, GST TDS accepted in GSTR-7A (approved receipts). */
    public function checks(Request $request, PaymentReceipt $receipt): RedirectResponse
    {
        Gate::authorize('billing');
        abort_unless($receipt->status === 'approved', 404);
        $data = $request->validate(['form16a' => ['array'], 'form16a.*' => ['integer'], 'gstr7a' => ['array'], 'gstr7a.*' => ['integer']]);
        foreach ($receipt->lines as $line) {
            $line->update(['form16a_seen' => in_array($line->id, $data['form16a'] ?? [], false), 'gstr7a_accepted' => in_array($line->id, $data['gstr7a'] ?? [], false)]);
        }

        return back()->with('status', 'TDS ticks saved.');
    }

    public function export(Request $request, string $tab, string $format, PaymentReports $reports): Response
    {
        Gate::authorize('billing');
        abort_unless(isset(self::TABS[$tab]) && in_array($format, ['xlsx', 'pdf'], true), 404);
        $f = $this->filters($request);
        $data = match ($tab) {
            'receipts' => $reports->receipts($f['from'], $f['to'], $f['contract']?->id),
            'outstanding' => $reports->outstanding($f['contract']?->id),
            'tds' => $reports->tds($f['from'], $f['to'], $f['contract']?->id, $request->boolean('pending')),
        };
        $period = $tab === 'outstanding' ? 'as on '.now()->timezone('Asia/Kolkata')->format('d.m.Y') : $f['from']->format('d.m.Y').' to '.$f['to']->format('d.m.Y');
        $title = ['receipts' => 'Payments received', 'outstanding' => 'Outstanding sub-bills', 'tds' => 'TDS and GST TDS deducted'][$tab];
        $file = \Illuminate\Support\Str::slug($title).'-'.($tab === 'outstanding' ? now()->format('Y-m-d') : $f['from']->format('Y-m-d').'-to-'.$f['to']->format('Y-m-d'));
        Activity::log('downloaded', null, [], "{$title} {$period} (".strtoupper($format).')', section: 'Payments received');
        [$head, $rows, $foot] = $this->table($tab, $data);

        if ($format === 'pdf') {
            return Pdf::loadView('pdf.payments-register', ['title' => $title, 'period' => $period, 'contract' => $f['contract'], 'head' => $head, 'rows' => $rows, 'foot' => $foot,
                'agency' => $f['contract']?->agency ?? \App\Support\Agency\AgencyContext::agency()])->setPaper('a4', 'landscape')->stream($file.'.pdf');
        }
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet()->setTitle(mb_substr($title, 0, 31));
        $text = fn (int $c, int $r, $v) => $ws->setCellValueExplicit([$c, $r], (string) $v, DataType::TYPE_STRING);
        $text(1, 1, $title.' – '.$period.($f['contract'] ? ' – work order '.$f['contract']->contract_no : ''));
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        foreach (array_keys($head) as $i => $h) {
            $text($i + 1, 3, $h);
        }
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($head));
        $ws->getStyle("A3:{$last}3")->getFont()->setBold(true);
        $ws->getStyle("A3:{$last}3")->getFill()->setFillType('solid')->getStartColor()->setRGB('DCE6F5');
        $r = 4;
        foreach (array_merge($rows, $foot ? [$foot] : []) as $row) {
            foreach (array_values($row) as $i => $v) {
                $kind = array_values($head)[$i];
                $kind === 'money' && $v !== '' ? $ws->setCellValueExplicit([$i + 1, $r], $v, DataType::TYPE_NUMERIC) : $text($i + 1, $r, $v);
                if ($kind === 'money') {
                    $ws->getStyle([$i + 1, $r])->getNumberFormat()->setFormatCode('#,##0.00');
                }
            }
            $r++;
        }
        if ($foot) {
            $ws->getStyle('A'.($r - 1).":{$last}".($r - 1))->getFont()->setBold(true);
        }
        foreach (range(1, count($head)) as $i) {
            $ws->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $file.'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Heading => kind ('text' | 'money'), rows and the total row of a register. */
    private function table(string $tab, $data): array
    {
        $m = fn ($v) => number_format((float) $v, 2, '.', '');
        if ($tab === 'receipts') {
            $head = ['Credit date' => 'text', 'Agency' => 'text', 'Work order' => 'text', 'Mode' => 'text', 'UTR / cheque no.' => 'text', 'Transfer / cheque date' => 'text',
                'Account credited' => 'text', 'Sub-bills' => 'text', 'Bill amount' => 'money', 'TDS' => 'money', 'GST TDS' => 'money', 'Other deduction' => 'money', 'Received' => 'money', 'Status' => 'text'];
            $rows = [];
            foreach ($data as $r) {
                $t = PaymentReports::lineTotals($r->lines);
                $rows[] = [$r->credit_date->format('d.m.Y'), $r->agency?->code, $r->contract?->contract_no, PaymentReceipt::MODES[$r->mode] ?? $r->mode, $r->reference(),
                    ($r->transfer_date ?? $r->cheque_date)?->format('d.m.Y') ?? '', (string) $r->bank_account, $r->lines->map(fn ($l) => $l->invoice?->invoice_no)->implode(', '),
                    $t['bill_amount'], $t['tds_it'], $m((float) $t['tds_cgst'] + (float) $t['tds_sgst'] + (float) $t['tds_igst']), $t['other_deduction'], $t['received'], PaymentReceipt::STATUSES[$r->status] ?? $r->status];
            }
            $ok = $data->where('status', 'approved');
            $t = PaymentReports::lineTotals($ok->flatMap->lines);
            $foot = ['Total of approved ('.$ok->count().')', '', '', '', '', '', '', '', $t['bill_amount'], $t['tds_it'], $m((float) $t['tds_cgst'] + (float) $t['tds_sgst'] + (float) $t['tds_igst']), $t['other_deduction'], $t['received'], ''];

            return [$head, $rows, $foot];
        }
        if ($tab === 'outstanding') {
            $head = ['Agency' => 'text', 'Work order' => 'text', 'Sub-bill no.' => 'text', 'Month' => 'text', 'Bill date' => 'text', 'Days' => 'text', 'Age' => 'text',
                'Bill amount' => 'money', 'Received + deducted' => 'money', 'Still due' => 'money'];
            $rows = $data['rows']->map(fn ($r) => [$r['bill']->contract?->agency?->code, $r['bill']->contract?->contract_no, $r['bill']->invoice_no, $r['bill']->month->format('M Y'),
                $r['bill']->invoice_date?->format('d.m.Y') ?? '', (string) $r['days'], PaymentReports::AGES[$r['age']], $r['billed'], $r['settled'], $r['due']])->all();

            return [$head, $rows, ['Total ('.count($rows).' sub-bills)', '', '', '', '', '', '', $data['total']['billed'], $data['total']['settled'], $data['total']['due']]];
        }
        $head = ['Credit date' => 'text', 'Agency' => 'text', 'Work order' => 'text', 'UTR / cheque no.' => 'text', 'Sub-bill no.' => 'text', 'Before GST' => 'money',
            'TDS (income tax)' => 'money', 'CGST TDS' => 'money', 'SGST TDS' => 'money', 'IGST TDS' => 'money', 'Form 16A / 26AS seen' => 'text', 'GSTR-7A accepted' => 'text'];
        $rows = $data->map(fn ($l) => [$l->receipt->credit_date->format('d.m.Y'), $l->receipt->agency?->code, $l->receipt->contract?->contract_no, $l->receipt->reference(), $l->invoice?->invoice_no,
            (string) $l->taxable, (string) $l->tds_it, (string) $l->tds_cgst, (string) $l->tds_sgst, (string) $l->tds_igst, $l->form16a_seen ? 'Yes' : 'No', $l->gstr7a_accepted ? 'Yes' : 'No'])->all();
        $t = PaymentReports::lineTotals($data);

        return [$head, $rows, ['Total ('.count($rows).')', '', '', '', '', $t['taxable'], $t['tds_it'], $t['tds_cgst'], $t['tds_sgst'], $t['tds_igst'], '', '']];
    }

    private function form(PaymentReceipt $receipt, ?GemContract $contract, Receipts $receipts, array $preselect): View
    {
        $bills = $contract ? $receipts->openBills($contract, $receipt->exists ? $receipt : null) : collect();
        $lines = $receipt->exists ? $receipt->lines->keyBy('invoice_id') : collect();
        $rows = $bills->map(function (Invoice $b) use ($receipts, $receipt, $lines) {
            $line = $lines->get($b->id);

            return ['bill' => $b, 'due' => \App\Support\Exact::money($receipts->outstanding($b, true, $receipt->id)), 'line' => $line,
                'values' => $line ? $line->only(['tds_it', 'tds_cgst', 'tds_sgst', 'tds_igst', 'other_deduction', 'received', 'other_reason']) : $receipts->expected($b, $receipt->id)];
        });
        $agency = $contract?->agency;

        return view('payments.form', ['receipt' => $receipt, 'contract' => $contract, 'rows' => $rows, 'preselect' => array_map('intval', $preselect),
            'contracts' => GemContract::query()->with('client:id,name,short_code', 'agency:id,code')->orderBy('contract_no')->get(['id', 'contract_no', 'agency_id', 'client_id']),
            'account' => $agency && $agency->bank_account_no ? trim($agency->bank_name.' A/c ****'.substr((string) $agency->bank_account_no, -4)) : null]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(array_keys(PaymentReceipt::MODES))],
            'utr_no' => ['nullable', 'string', 'max:40', 'required_if:mode,neft,rtgs'],
            'transfer_date' => ['nullable', 'date'],
            'cheque_no' => ['nullable', 'string', 'max:20', 'required_if:mode,cheque,dd'],
            'cheque_date' => ['nullable', 'date', 'required_if:mode,cheque,dd'],
            'cheque_bank' => ['nullable', 'string', 'max:120'],
            'credit_date' => ['required', 'date', 'before_or_equal:today'],
            'bank_account' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999', 'decimal:0,2'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'advice' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], ['utr_no.required_if' => 'Enter the UTR number of the NEFT / RTGS.', 'cheque_no.required_if' => 'Enter the cheque / DD number.',
            'cheque_date.required_if' => 'Enter the cheque / DD date.', 'credit_date.before_or_equal' => 'The credit date cannot be in the future.']);
        unset($data['advice']);

        return $data;
    }

    /** Ticked sub-bills only: invoice_id => figures. */
    private function lines(Request $request): array
    {
        $request->validate(['lines' => ['array'], 'lines.*.pick' => ['nullable'], 'lines.*.other_reason' => ['nullable', 'string', 'max:200']]
            + array_fill_keys(array_map(fn ($f) => "lines.*.{$f}", ['tds_it', 'tds_cgst', 'tds_sgst', 'tds_igst', 'other_deduction', 'received']), ['nullable', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2']));

        return collect((array) $request->input('lines', []))->filter(fn ($l) => ! empty($l['pick']))->all();
    }

    /** Tab, period (default: this financial year) and work order (own agency only). */
    private function filters(Request $request): array
    {
        [$fyFrom, $fyTo] = PaymentReports::financialYear(CarbonImmutable::today());
        $date = fn (string $k, CarbonImmutable $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input($k)) ? CarbonImmutable::parse($request->input($k)) : $d;
        $tab = isset(self::TABS[$request->input('tab')]) ? $request->input('tab') : 'receipts';

        return ['tab' => $tab, 'from' => $date('from', $fyFrom), 'to' => $date('to', $fyTo),
            'contract' => $request->integer('contract') ? GemContract::query()->findOrFail($request->integer('contract')) : null];
    }
}
