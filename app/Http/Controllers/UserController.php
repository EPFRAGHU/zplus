<?php

namespace App\Http\Controllers;

use App\Models\LoginEvent;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Admin: agency staff and PI logins. New and reset passwords are temporary: the user must change them at sign-in. */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-users');
        $mine = $request->user()->agency_id;
        $users = User::query()->with('projects.contract.client', 'agency')
            ->when($mine, fn ($q) => $q->where('agency_id', $mine)->where('is_super_admin', false))   // an agency Admin sees only their agency's logins
            ->orderByRaw("case role when 'admin' then 1 when 'accounts' then 2 when 'supervisor' then 3 else 4 end")
            ->orderBy('name')->get();

        return view('users.index', ['users' => $users,
            'recentLogins' => LoginEvent::query()->when($mine, fn ($q) => $q->whereIn('user_id', $users->pluck('id')))->latest('id')->limit(15)->get()->map(fn ($e) => $e->setRelation('user', $users->firstWhere('id', $e->user_id)))]);
    }

    public function create(): View
    {
        Gate::authorize('manage-users');

        return view('users.form', ['user' => new User(['role' => 'pi', 'active' => true])] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-users');
        $data = $this->validated($request);
        $user = (new User)->forceFill([...collect($data)->except('projects')->all(), 'must_change_password' => true]);
        $user->save();
        $user->projects()->sync($user->isPi() ? ($data['projects'] ?? []) : []);

        return redirect()->route('users.index')->with('status', "{$user->name} added. Give them the e-mail and temporary password; they must change it at first sign-in.");
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('manage-users');
        $this->guardOwner($request, $user);

        return view('users.form', ['user' => $user->load('projects')] + $this->formData());
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');
        $this->guardOwner($request, $user);
        $data = $this->validated($request, $user);
        if ($user->is($request->user()) && ($data['role'] !== 'admin' || ! $data['active'])) {
            return back()->withErrors(['role' => 'You cannot remove your own Admin role or switch off your own account.'])->withInput();
        }
        if (! empty($data['password'])) {
            $data['must_change_password'] = true;   // a reset password is temporary too
        } else {
            unset($data['password']);
        }
        $user->forceFill(collect($data)->except('projects')->all())->save();
        $user->projects()->sync($user->isPi() ? ($data['projects'] ?? []) : []);

        return redirect()->route('users.index')->with('status', "{$user->name} saved.".(isset($data['must_change_password']) ? ' The new password is temporary.' : ''));
    }

    /** Only the Super Admin can change the Super Admin's account; an agency Admin only reaches their own agency's logins. */
    private function guardOwner(Request $request, User $user): void
    {
        abort_if($user->is_super_admin && ! $request->user()->is($user), 403, 'Only the Super Admin can change this account.');
        $mine = $request->user()->agency_id;
        abort_if($mine && (int) $user->agency_id !== (int) $mine, 404);
    }

    private function formData(): array
    {
        return ['agencies' => \App\Models\Agency::query()->orderBy('name')->get(), 'canPickBoth' => auth()->user()->isSuperAdmin(),
            'projects' => Project::query()->with('contract.agency', 'contract.client')->get()
            ->sortBy(fn ($p) => $p->contract->agency->name.'|'.$p->contract->client->name.'|'.$p->name)->values()];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
            'projects' => ['array', Rule::requiredIf($request->input('role') === 'pi')],
            'projects.*' => ['integer', new \App\Rules\ExistsInMyAgency(\App\Models\Project::class)],
            'agency_id' => ['nullable', 'integer', new \App\Rules\ExistsInMyAgency(\App\Models\Agency::class)],
        ], ['projects.required' => 'Choose at least one project for this PI.', 'phone.regex' => 'Enter a 10-digit mobile number.']);
        $data['active'] = $request->boolean('active');

        // Which agency the login works for. An agency Admin can only create logins for their own agency;
        // only the Super Admin can create a login for both agencies.
        $editor = $request->user();
        if ($editor->agency_id) {
            $data['agency_id'] = (int) $editor->agency_id;
        } elseif (empty($data['agency_id'])) {
            if ($data['role'] === 'pi' && ! empty($data['projects'])) {
                // A PI is limited by their projects anyway; record their agency when all projects belong to one.
                $agencies = \App\Support\Agency\AgencyContext::unscoped(fn () => \App\Models\GemContract::query()
                    ->whereIn('id', Project::query()->whereIn('id', $data['projects'])->pluck('gem_contract_id'))->distinct()->pluck('agency_id'));
                $data['agency_id'] = $agencies->count() === 1 ? (int) $agencies->first() : null;

                return $data;
            }
            if (! $editor->isSuperAdmin() && ! ($user && $user->exists && $user->agency_id === null)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['agency_id' => 'Choose the agency this login works for. Only the Super Admin can create logins for both agencies.']);
            }
            $data['agency_id'] = null;
        }
        if ($data['role'] === 'pi' && $data['agency_id'] && ! empty($data['projects'])) {
            $outside = \App\Support\Agency\AgencyContext::unscoped(fn () => Project::query()->whereIn('id', $data['projects'])
                ->whereHas('contract', fn ($q) => $q->where('agency_id', '!=', $data['agency_id']))->exists());
            if ($outside) {
                throw \Illuminate\Validation\ValidationException::withMessages(['projects' => 'Choose only projects of this login\'s agency.']);
            }
        }

        return $data;
    }
}
