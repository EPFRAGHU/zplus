<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Project;
use App\Models\ProjectIncharge;
use App\Models\User;
use App\Support\PiAllotment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * PI Allotment: the master of Project In-charges (name, mobile, e-mail), each mapped to one or more projects,
 * which may sit under different work orders. Projects, certificates, bills and the PI login read the PI from here.
 */
class ProjectInchargeController extends Controller
{
    public function index(Request $request): View
    {
        $agencyId = $request->integer('agency') ?: null;
        $incharges = ProjectIncharge::query()->with(['agency', 'user', 'projects.contract'])
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->when($request->string('q')->trim()->value(), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('mobile', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->get()->sortBy(fn ($i) => $i->agency->name.'|'.$i->name)->values();
        $unallotted = Project::query()->with('contract.agency')->whereNull('project_incharge_id')
            ->when($agencyId, fn ($q) => $q->whereHas('contract', fn ($c) => $c->where('agency_id', $agencyId)))->get();

        return view('incharges.index', ['incharges' => $incharges, 'unallotted' => $unallotted, 'agencyId' => $agencyId,
            'agencies' => Agency::query()->orderBy('name')->get()]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('manage-masters');

        return view('incharges.form', $this->formData(new ProjectIncharge(['agency_id' => $request->integer('agency') ?: auth()->user()->agency_id, 'status' => 'active'])));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $incharge = DB::transaction(function () use ($request) {
            $incharge = ProjectIncharge::query()->create($this->validated($request));
            $this->allot($incharge, $request);

            return $incharge;
        });

        return redirect()->route('incharges.index', ['agency' => $incharge->agency_id])->with('status', "{$incharge->name} added to PI Allotment.");
    }

    public function edit(ProjectIncharge $incharge): View
    {
        Gate::authorize('manage-masters');

        return view('incharges.form', $this->formData($incharge));
    }

    public function update(Request $request, ProjectIncharge $incharge): RedirectResponse
    {
        Gate::authorize('manage-masters');
        DB::transaction(function () use ($request, $incharge) {
            $data = $this->validated($request, $incharge);
            if ((int) $data['agency_id'] !== (int) $incharge->agency_id && $incharge->projects()->exists()) {
                throw ValidationException::withMessages(['agency_id' => 'This PI has projects of this agency, so the agency cannot change.']);
            }
            $incharge->update($data);
            $this->allot($incharge, $request);
            PiAllotment::refreshProjects($incharge);
        });

        return redirect()->route('incharges.index', ['agency' => $incharge->agency_id])->with('status', "{$incharge->name} saved.");
    }

    private function formData(ProjectIncharge $incharge): array
    {
        return [
            'incharge' => $incharge,
            'agencies' => Agency::query()->orderBy('name')->get(),
            'projects' => Project::query()->with('contract.client', 'incharge')->get()
                ->sortBy(fn ($p) => $p->contract->contract_no.'|'.$p->name)->groupBy('gem_contract_id'),
            'piUsers' => User::query()->where('role', 'pi')->when(auth()->user()->agency_id, fn ($q, $a) => $q->where('agency_id', $a))
                ->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }

    private function validated(Request $request, ?ProjectIncharge $incharge = null): array
    {
        $data = $request->validate([
            'agency_id' => ['required', new \App\Rules\ExistsInMyAgency(Agency::class)],
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'pi')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], ['mobile.regex' => 'Enter a 10-digit mobile number.', 'user_id.exists' => 'Choose a PI login.']);

        // Linking a portal login gives it access to projects, so only those who manage logins (Admin) may change it.
        if (! Gate::allows('manage-users')) {
            unset($data['user_id']);
        } elseif (! empty($data['user_id'])) {
            $loginAgency = User::query()->whereKey($data['user_id'])->value('agency_id');
            if ($loginAgency !== null && (int) $loginAgency !== (int) $data['agency_id']) {
                throw ValidationException::withMessages(['user_id' => 'This PI login belongs to the other agency.']);
            }
        }

        return $data;
    }

    /**
     * Maps the ticked projects to this PI (a project has one PI; ticking it here moves it from its previous PI).
     * Unticked projects of this PI are released. If the PI has a login, its PI-portal projects follow.
     */
    private function allot(ProjectIncharge $incharge, Request $request): void
    {
        $ids = collect($request->input('projects', []))->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v)->unique();
        $projects = Project::query()->with('contract')->whereKey($ids->all())->get();
        if ($projects->count() !== $ids->count() || $projects->contains(fn ($p) => (int) $p->contract->agency_id !== (int) $incharge->agency_id)) {
            throw ValidationException::withMessages(['projects' => 'Choose only projects of this PI\'s agency.']);
        }

        Project::query()->where('project_incharge_id', $incharge->id)->whereNotIn('id', $ids->all())->get()
            ->each(fn ($p) => $p->update(['project_incharge_id' => null]));
        $projects->each(fn ($p) => $p->update(['project_incharge_id' => $incharge->id, 'pi_name' => $incharge->name, 'pi_designation' => $incharge->designation]));

        if ($incharge->user && Gate::allows('manage-users')) {
            // The PI portal shows exactly the projects allotted here (for this agency).
            $agencyProjects = Project::query()->whereHas('contract', fn ($c) => $c->where('agency_id', $incharge->agency_id))->pluck('id');
            $incharge->user->projects()->detach($agencyProjects->diff($ids)->all());
            $incharge->user->projects()->syncWithoutDetaching($ids->all());
        }
    }
}
