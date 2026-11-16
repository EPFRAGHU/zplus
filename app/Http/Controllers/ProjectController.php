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

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', \App\Support\Codes::PATTERN, 'max:12'],
            'title' => ['nullable', 'string', 'max:2000'],
            'project_incharge_id' => ['nullable', new \App\Rules\ExistsInMyAgency(\App\Models\ProjectIncharge::class)],
            'pi_name' => ['nullable', 'string', 'max:255'],
            'pi_designation' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data = \App\Support\PiAllotment::syncNames($data);
        $data['saturday_off'] = $request->boolean('saturday_off');

        return $data;
    }
}
