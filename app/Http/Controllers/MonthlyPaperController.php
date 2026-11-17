<?php

namespace App\Http\Controllers;

use App\Models\GemContract;
use App\Models\MonthlyPaper;
use App\Services\Billing\BillPack;
use App\Support\Activity\Activity;
use App\Support\Codes;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Monthly papers of a work order (EPF / ESIC challans, salary payment proof, logbook, feedback) and the GeM bill pack. */
class MonthlyPaperController extends Controller
{
    public function store(Request $request, GemContract $contract): RedirectResponse
    {
        Gate::authorize('billing');
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'kind' => ['required', Rule::in(array_keys(MonthlyPaper::KINDS))],
            'paper' => ['required', 'array', 'max:10'],
            'paper.*' => ['file', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,csv', 'max:10240'],
        ], ['paper.required' => 'Choose the file(s) to upload.', 'paper.*.mimes' => 'Upload PDF, image or Excel files.', 'paper.*.max' => 'Each file must be under 10 MB.']);
        if (in_array($data['kind'], MonthlyPaper::BANK_KINDS, true)) {
            Gate::authorize('view-bank-details');
        }
        $month = CarbonImmutable::createFromFormat('!Y-m', $data['month']);
        foreach ($request->file('paper') as $file) {
            $name = (Codes::fileSafe(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: $data['kind']).'.'.strtolower($file->getClientOriginalExtension() ?: 'pdf');
            $paper = MonthlyPaper::query()->create(['agency_id' => $contract->agency_id, 'gem_contract_id' => $contract->id, 'month' => $month->toDateString(),
                'kind' => $data['kind'], 'path' => $file->store('papers/'.$month->format('Y-m'), 'local'), 'original_name' => $name, 'uploaded_by' => $request->user()->id]);
            Activity::log('created', $paper, [], "Monthly paper uploaded: {$paper->label()}, {$month->format('M Y')}, work order {$contract->contract_no}", section: 'Monthly papers', label: $contract->contract_no);
        }

        return redirect()->route('contracts.show', ['contract' => $contract, 'month' => $data['month']])->withFragment('papers')
            ->with('status', MonthlyPaper::KINDS[$data['kind']]." for {$month->format('F Y')} uploaded.");
    }

    public function show(MonthlyPaper $paper): Response
    {
        Gate::authorize('billing');
        if (in_array($paper->kind, MonthlyPaper::BANK_KINDS, true)) {
            Gate::authorize('view-bank-details');
        }
        abort_unless(Storage::disk('local')->exists($paper->path), 404);

        return Storage::disk('local')->response($paper->path, $paper->original_name);
    }

    /** Admin only: removes a paper uploaded by mistake. */
    public function destroy(MonthlyPaper $paper): RedirectResponse
    {
        Gate::authorize('reset-month');
        $paper->load('contract');
        $what = "{$paper->label()}, {$paper->month->format('M Y')}, work order {$paper->contract->contract_no} ({$paper->original_name})";
        $paper->delete();
        Activity::log('deleted', null, [], "Monthly paper removed: {$what}", section: 'Monthly papers', label: $paper->contract->contract_no);

        return back()->with('status', 'Paper removed.');
    }

    public function pack(Request $request, GemContract $contract, BillPack $pack): Response
    {
        Gate::authorize('billing');
        $request->validate(['month' => ['required', 'date_format:Y-m']]);
        try {
            [$path, $name, $count, $missing] = $pack->zip($contract, CarbonImmutable::createFromFormat('!Y-m', $request->input('month')), Gate::allows('view-bank-details'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['pack' => $e->getMessage()]);
        }
        Activity::log('downloaded', null, [], "GeM bill pack: {$name} ({$count} files".($missing ? '; missing: '.implode(', ', $missing) : '').')', section: 'Monthly papers', label: $contract->contract_no);

        return response()->download($path, $name, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
