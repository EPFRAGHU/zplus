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
        $project->update($data);

        return back()->with('status', 'Project saved.');
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
            'code' => ['required', \App\Support\Codes::PATTERN, 'max:20'],
            'title' => ['nullable', 'string', 'max:2000'],
            'project_incharge_id' => ['nullable', new \App\Rules\ExistsInMyAgency(\App\Models\ProjectIncharge::class)],
            'pi_name' => ['nullable', 'string', 'max:255'],
            'pi_designation' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ], [], ['code' => 'short project name']);
        $data['code'] = strtoupper($data['code']);
        $data = \App\Support\PiAllotment::syncNames($data);
        $data['saturday_off'] = $request->boolean('saturday_off');

        return $data;
    }
}
