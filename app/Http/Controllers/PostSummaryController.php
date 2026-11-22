<?php

namespace App\Http\Controllers;

use App\Models\GemContract;
use App\Models\Invoice;
use App\Services\Reports\PostSummary;
use App\Support\Activity\Activity;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Post-wise summary of sub-bills for filing GeM returns (owner, 22 Nov 2026): work order → sub-bill → post, with the
 * wages, EPF, ESIC, service charge, value before GST and GST per post; page, Excel and PDF. Read only.
 */
class PostSummaryController extends Controller
{
    public const HEADINGS = ['wages' => 'Wages', 'epf' => 'EPF', 'esic' => 'ESIC', 'subtotal' => 'Sub total', 'service_charge' => 'Service charge',
        'taxable' => 'Before GST', 'cgst' => 'CGST', 'sgst' => 'SGST', 'igst' => 'IGST', 'total' => 'Total'];

    public function show(Request $request, PostSummary $summary): View
    {
        Gate::authorize('billing');
        $f = $this->filters($request);
        $data = $f['contract'] ? $summary->build($f['month'], null, $f['contract']->id, $f['bill']?->id, $f['post']) : null;

        return view('reports.post-summary', $f + ['data' => $data, 'headings' => self::HEADINGS]);
    }

    public function pdf(Request $request, PostSummary $summary): Response
    {
        Gate::authorize('billing');
        $f = $this->filters($request);
        abort_unless($f['contract'], 404);
        $data = $summary->build($f['month'], null, $f['contract']->id, $f['bill']?->id, $f['post']);
        abort_if($data['rows'] === [], 404, 'No sub-bills for this choice.');
        Activity::log('downloaded', $f['contract'], [], 'Post-wise summary '.$f['month']->format('F Y').' (PDF)', section: 'Billing', label: $f['contract']->contract_no.' '.$f['month']->format('M Y'));

        return Pdf::loadView('pdf.post-summary', $f + ['data' => $data, 'headings' => self::HEADINGS])
            ->setPaper('a4', 'landscape')->stream($this->fileName($f).'.pdf');
    }

    public function excel(Request $request, PostSummary $summary): StreamedResponse
    {
        Gate::authorize('billing');
        $f = $this->filters($request);
        abort_unless($f['contract'], 404);
        $data = $summary->build($f['month'], null, $f['contract']->id, $f['bill']?->id, $f['post']);
        abort_if($data['rows'] === [], 404, 'No sub-bills for this choice.');
        Activity::log('downloaded', $f['contract'], [], 'Post-wise summary '.$f['month']->format('F Y').' (Excel)', section: 'Billing', label: $f['contract']->contract_no.' '.$f['month']->format('M Y'));

        $money = array_keys(array_filter(self::HEADINGS, fn ($h, $k) => $k !== 'igst' || $data['igst'], ARRAY_FILTER_USE_BOTH));
        if ($data['igst'] && (float) $data['total']['cgst'] == 0) {
            $money = array_values(array_diff($money, ['cgst', 'sgst']));
        }
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet()->setTitle('Post-wise summary');
        $text = function (int $col, int $row, $value) use ($ws) {
            // Plain text: a post name starting with = + - @ must never become an Excel formula.
            $ws->setCellValueExplicit([$col, $row], (string) $value, DataType::TYPE_STRING);
        };
        $c = $f['contract'];
        $text(1, 1, $c->agency?->name.' – post-wise summary of sub-bills, '.$f['month']->format('F Y'));
        $text(1, 2, 'Work order: '.$c->contract_no.($c->bid_no ? ' (bid '.$c->bid_no.')' : '').' · Client: '.$c->client?->name);
        $text(1, 3, 'Sub-bill: '.($f['bill']?->invoice_no ?? 'all ('.$data['bills']->count().')').' · Post: '.($f['post'] ?: 'all'));
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $head = array_merge(['Sl', 'Post', 'Sub-bill(s)', 'Staff', 'Days paid'], array_map(fn ($k) => self::HEADINGS[$k].' (Rs.)', $money));
        foreach ($head as $i => $h) {
            $text($i + 1, 5, $h);
        }
        $last = count($head);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($last);
        $ws->getStyle('A5:'.$lastCol.'5')->getFont()->setBold(true);
        $ws->getStyle('A5:'.$lastCol.'5')->getFill()->setFillType('solid')->getStartColor()->setRGB('DCE6F5');
        $r = 6;
        $put = function (int $r, array $row, $sl) use ($ws, $text, $money) {
            $sl === null ? $text(1, $r, '') : $ws->setCellValue([1, $r], $sl);
            $text(2, $r, $row['post']);
            $text(3, $r, $row['bills'] ?? '');
            $ws->setCellValue([4, $r], (int) $row['staff']);
            $ws->setCellValue([5, $r], (float) $row['days']);
            foreach ($money as $i => $k) {
                $ws->setCellValueExplicit([6 + $i, $r], $row[$k], DataType::TYPE_NUMERIC);
            }
        };
        foreach ($data['rows'] as $n => $row) {
            $put($r++, $row, $n + 1);
        }
        $put($r, ['post' => 'Total', 'bills' => count($data['rows']).' posts'] + $data['total'], null);
        $ws->getStyle('A'.$r.':'.$lastCol.$r)->getFont()->setBold(true);
        $ws->getStyle('F6:'.$lastCol.$r)->getNumberFormat()->setFormatCode('#,##0.00');
        if (! $f['post']) {
            $r += 1;
            $put($r, ['post' => 'As on the sub-bill(s)', 'bills' => 'stored bill figures', 'staff' => $data['total']['staff'], 'days' => $data['total']['days']] + $data['stored'], null);
            $ws->getStyle('A'.$r.':'.$lastCol.$r)->getFont()->setItalic(true);
            $ws->getStyle('F'.$r.':'.$lastCol.$r)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (range(1, $last) as $i) {
            $ws->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        $text(1, $r + 2, 'Service charge and GST are worked out per post with each sub-bill\'s own rates; the bill rounds only its grand total. Printed '.now()->timezone('Asia/Kolkata')->format('d.m.Y h:i A').'.');

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $this->fileName($f).'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Month, work order (only the login's own agency: other work orders give 404), one sub-bill of that work order and
     * month (else 404), and post name. The month defaults to the latest month with sub-bills.
     */
    private function filters(Request $request): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month'))
            ? CarbonImmutable::createFromFormat('!Y-m', $request->input('month'))
            : (($latest = Invoice::query()->max('month')) ? CarbonImmutable::parse($latest)->startOfMonth() : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth());
        $contract = $request->integer('contract') ? GemContract::query()->with('agency', 'client')->findOrFail($request->integer('contract')) : null;
        $bill = null;
        if ($contract && $request->integer('bill')) {
            $bill = Invoice::query()->where('gem_contract_id', $contract->id)->where('month', $month->toDateString())->findOrFail($request->integer('bill'));
        }
        $post = trim((string) $request->input('post')) ?: null;
        $post = $post !== null ? mb_substr($post, 0, 150) : null;

        // Choices for the dropdowns: work orders with sub-bills that month, their sub-bills, and the posts on them.
        $contracts = GemContract::query()->with('client:id,name,short_code', 'agency:id,code,name')
            ->whereIn('id', Invoice::query()->where('month', $month->toDateString())->select('gem_contract_id'))->orderBy('contract_no')->get();
        $bills = $contract ? Invoice::query()->where('gem_contract_id', $contract->id)->where('month', $month->toDateString())->with('project:id,code,name')->orderBy('invoice_no')->get() : collect();
        $posts = $contract ? \App\Models\InvoiceLine::query()->whereIn('invoice_id', $bill ? [$bill->id] : $bills->pluck('id'))->pluck('designation')
            ->groupBy(fn ($d) => PostController::postKey($d))->map->first()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values() : collect();

        return ['month' => $month, 'contract' => $contract, 'bill' => $bill, 'post' => $post, 'contracts' => $contracts, 'bills' => $bills, 'posts' => $posts];
    }

    private function fileName(array $f): string
    {
        $name = 'post-wise-summary-'.$f['contract']->contract_no.'-'.$f['month']->format('Y-m')
            .($f['bill'] ? '-'.$f['bill']->invoice_no : '').($f['post'] ? '-'.$f['post'] : '');

        return trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $name), '-');
    }
}
