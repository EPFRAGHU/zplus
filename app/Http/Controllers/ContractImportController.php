<?php

namespace App\Http\Controllers;

use App\Models\GemContract;
use App\Models\Project;
use App\Services\Contracts\GemContractPdf;
use App\Support\Activity\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Work order → "Import from GeM contract PDF" (owner, 17 Nov 2026): reads the contract's posts, dates and value,
 * shows them to check and correct, then adds the ticked posts to a project. The PDF itself is not kept.
 */
class ContractImportController extends Controller
{
    public function upload(Request $request, GemContract $contract, GemContractPdf $reader): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $request->validate(['contract_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240']],
            ['contract_pdf.required' => 'Choose the GeM contract PDF.', 'contract_pdf.mimes' => 'Upload the contract PDF downloaded from GeM.']);
        try {
            $read = $reader->read($request->file('contract_pdf')->getRealPath());
        } catch (RuntimeException $e) {
            return back()->withErrors(['contract_pdf' => $e->getMessage()]);
        }
        if ($read['posts'] === []) {
            return back()->withErrors(['contract_pdf' => 'No posts were found in this PDF. Is it a GeM manpower (fixed remuneration) contract?']);
        }
        if ($read['contract_no'] && strcasecmp($read['contract_no'], $contract->contract_no) !== 0) {
            return back()->withErrors(['contract_pdf' => "This PDF is contract {$read['contract_no']}, but this work order is {$contract->contract_no}. Open the right work order, or add a new one for this contract."]);
        }
        session(["contract-import.{$contract->id}" => $read]);

        return redirect()->route('contracts.import.show', $contract);
    }

    public function show(GemContract $contract): View|RedirectResponse
    {
        Gate::authorize('manage-masters');
        $read = session("contract-import.{$contract->id}");
        if (! $read) {
            return redirect()->route('contracts.show', $contract);
        }
        $contract->load('agency', 'client', 'projects.posts');

        return view('contracts.import', ['contract' => $contract, 'read' => $read]);
    }

    public function store(Request $request, GemContract $contract): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $read = session("contract-import.{$contract->id}");
        if (! $read) {
            return redirect()->route('contracts.show', $contract)->withErrors(['contract_pdf' => 'Upload the contract PDF again.']);
        }
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'posts' => ['array'],
            'posts.*.add' => ['nullable', 'boolean'],
            'posts.*.designation' => ['required_if:posts.*.add,1', 'nullable', 'string', 'max:255'],
            'posts.*.sanctioned_qty' => ['required_if:posts.*.add,1', 'nullable', 'integer', 'min:0', 'max:9999'],
            'posts.*.rate' => ['required_if:posts.*.add,1', 'nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'fill' => ['array'],
            'fill.*' => ['in:contract_date,start_date,end_date,contract_value'],
        ], ['project_id.required' => 'Choose the project the posts belong to.']);

        // The project must be one of THIS work order's projects (so also of this agency).
        $project = Project::query()->where('gem_contract_id', $contract->id)->find($data['project_id']);
        if (! $project) {
            throw ValidationException::withMessages(['project_id' => 'Choose a project of this work order.']);
        }

        $added = $skipped = [];
        DB::transaction(function () use ($data, $project, $contract, $read, &$added, &$skipped) {
            foreach ($data['posts'] ?? [] as $row) {
                if (empty($row['add'])) {
                    continue;
                }
                $designation = trim($row['designation']);
                $rate = number_format((float) $row['rate'], 2, '.', '');
                if ($project->posts()->where('designation', $designation)->where('rate', $rate)->exists()) {
                    $skipped[] = "{$designation} (₹{$rate})";

                    continue;
                }
                $project->posts()->create(['designation' => $designation, 'sanctioned_qty' => (int) $row['sanctioned_qty'], 'rate' => $rate]);
                $added[] = "{$designation} ×{$row['sanctioned_qty']} @ ₹{$rate}";
            }
            $fill = array_intersect_key(array_filter([
                'contract_date' => $read['contract_date'], 'start_date' => $read['start_date'],
                'end_date' => $read['end_date'], 'contract_value' => $read['contract_value'],
            ]), array_flip($data['fill'] ?? []));
            if ($fill) {
                $contract->update($fill);
            }
        });
        session()->forget("contract-import.{$contract->id}");
        Activity::log('created', $contract, [], 'Imported from GeM contract PDF into project '.$project->code.': '.count($added).' posts added'
            .($skipped ? ', '.count($skipped).' already there' : ''), section: 'Masters', label: $contract->contract_no);

        $message = count($added).' '.\Illuminate\Support\Str::plural('post', count($added))." added to {$project->name}.";
        if ($skipped) {
            $message .= ' Already there, not added again: '.implode(', ', $skipped).'.';
        }

        return redirect()->route('contracts.show', $contract)->with('status', $message);
    }
}
