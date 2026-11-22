<?php

namespace App\Http\Controllers;

use App\Models\GemContract;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function store(Request $request, GemContract $contract): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $data = $this->validated($request);
        \App\Support\PiAllotment::assertSameAgency($data['project_incharge_id'] ?? null, $contract);
        $contract->projects()->create($data);

        return back()->with('status', 'Project added.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $data = $this->validated($request);
        \App\Support\PiAllotment::assertSameAgency($data['project_incharge_id'] ?? null, $project->contract);
        $oldCode = $project->code;
        $project->update($data);
        if ($project->code === $oldCode) {
            return back()->with('status', 'Project saved.');
        }
        // New short name: the project's open sub-bills take it in their number, the serial stays (owner, 22 Nov 2026).
        $result = app(\App\Services\Billing\SubBillRenamer::class)->forProject($project, $request->user());
        $message = "Project saved. Short name changed from {$oldCode} to {$project->code}.";
        if ($result['renamed']) {
            $message .= ' Sub-bills renamed: '.collect($result['renamed'])->map(fn ($new, $old) => "{$old} → {$new}")->implode(', ')
                .'. Send the new PDF of any bill already sent to the client; an approved bill needs approving again.';
        }
        if ($result['kept']) {
            $message .= ' Paid bills keep their number: '.implode(', ', $result['kept']).'.';
        }

        return back()->with('status', $message);
    }

    /**
     * Admin: deletes a project entered by mistake or as an example. Only when it has no attendance, no sub-bills and
     * no employees on its posts; its empty posts go with it and it is taken off its Project In-charge and PI logins.
     */
    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete-project');
        if ($why = self::cannotDelete($project)) {
            return back()->withErrors(['project' => $why]);
        }
        $label = $project->name.' ('.$project->code.')';
        $contractId = $project->gem_contract_id;
        \Illuminate\Support\Facades\DB::transaction(function () use ($project) {
            $project->pis()->detach();
            $project->posts()->delete();
            $project->delete();
        });
        \App\Support\Activity\Activity::log('deleted', null, [], "Project deleted: {$label}", section: 'Masters', label: $label);

        return redirect()->route('contracts.show', $contractId)->with('status', "Project {$label} deleted.");
    }

    /** Why a project cannot be deleted, or null when it is empty. */
    public static function cannotDelete(Project $project): ?string
    {
        if ($project->attendanceSheets()->exists()) {
            return 'This project has attendance. Reset its months first (Attendance → open the month → Reset this month).';
        }
        if (\App\Models\Invoice::query()->where('project_id', $project->id)->exists()) {
            return 'This project has sub-bills, so it cannot be deleted.';
        }
        $staff = \App\Models\Employee::query()->whereIn('post_id', $project->posts()->pluck('id'))->pluck('name');
        if ($staff->isNotEmpty()) {
            return 'Move these employees to another post first: '.$staff->implode(', ').'.';
        }

        return null;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', \App\Support\Codes::PROJECT_PATTERN, 'max:20'],
            'title' => ['nullable', 'string', 'max:2000'],
            'project_incharge_id' => ['nullable', new \App\Rules\ExistsInMyAgency(\App\Models\ProjectIncharge::class)],
            'pi_name' => ['nullable', 'string', 'max:255'],
            'pi_designation' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ], ['code.regex' => \App\Support\Codes::PROJECT_MESSAGE], ['code' => 'short project name']);
        $data['code'] = strtoupper($data['code']);
        $data = \App\Support\PiAllotment::syncNames($data);
        $data['saturday_off'] = $request->boolean('saturday_off');

        return $data;
    }

    /**
     * Super Admin (owner, 23 Nov 2026): moves a project to another work order of the same agency, e.g. out of a stand-in
     * contract number made when one contract could hold only one project. Posts, staff, attendance and appointment letters
     * follow the project; letter numbers use the project's short name, so they do not change.
     */
    public function move(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('move-projects');
        $data = $request->validate(['gem_contract_id' => ['required', new \App\Rules\ExistsInMyAgency(GemContract::class)]],
            ['gem_contract_id.required' => 'Choose the work order to move the project to.']);
        $from = $project->contract;
        $to = GemContract::query()->findOrFail($data['gem_contract_id']);
        if ($why = self::cannotMove($project, $to)) {
            return back()->withErrors(['move' => $why]);
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($project, $from, $to) {
            $project->update(['gem_contract_id' => $to->id]);
            if ((int) $from->client_id !== (int) $to->client_id) {
                \App\Models\Employee::query()->whereIn('post_id', $project->posts()->pluck('id'))->update(['client_id' => $to->client_id]);
            }
            \App\Support\Activity\Activity::log('updated', $project, ['gem_contract_id' => [$from->contract_no, $to->contract_no]],
                "Project {$project->code} moved from work order {$from->contract_no} to {$to->contract_no}", section: 'Masters', label: $project->code);
        });

        return redirect()->route('contracts.show', $to)->with('status', "Project {$project->name} ({$project->code}) moved from {$from->contract_no} to {$to->contract_no}, with its posts, staff and attendance."
            .($from->projects()->exists() ? '' : " {$from->contract_no} now has no projects and can be deleted (Show delete options)."));
    }

    /** Why a project cannot move to $to, or null. */
    public static function cannotMove(Project $project, GemContract $to): ?string
    {
        if ((int) $to->id === (int) $project->gem_contract_id) {
            return 'The project is already under this work order.';
        }
        if ((int) $to->agency_id !== (int) $project->contract->agency_id) {
            return 'A project can only move to a work order of the same agency.';
        }
        if (\App\Models\Invoice::query()->where('project_id', $project->id)->exists()) {
            return 'This project already has sub-bills under '.$project->contract->contract_no.'; their bill numbers belong to that work order, so it cannot move.';
        }
        if ($to->projects()->whereRaw('UPPER(code) = ?', [strtoupper($project->code)])->exists()) {
            return 'Work order '.$to->contract_no.' already has a project '.$project->code.'. Change one short name first.';
        }

        return null;
    }
}
