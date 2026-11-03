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
    public function index(): View
    {
        Gate::authorize('manage-users');
        $users = User::query()->with('projects.contract.client')->orderByRaw("case role when 'admin' then 1 when 'accounts' then 2 when 'supervisor' then 3 else 4 end")
            ->orderBy('name')->get();

        return view('users.index', ['users' => $users,
            'recentLogins' => LoginEvent::query()->latest('id')->limit(15)->get()->map(fn ($e) => $e->setRelation('user', $users->firstWhere('id', $e->user_id)))]);
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
        $user = User::query()->create([...collect($data)->except('projects')->all(), 'must_change_password' => true]);
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
        $user->update(collect($data)->except('projects')->all());
        $user->projects()->sync($user->isPi() ? ($data['projects'] ?? []) : []);

        return redirect()->route('users.index')->with('status', "{$user->name} saved.".(isset($data['must_change_password']) ? ' The new password is temporary.' : ''));
    }

    /** Only the Super Admin can change the Super Admin's own account. */
    private function guardOwner(Request $request, User $user): void
    {
        abort_if($user->is_super_admin && ! $request->user()->is($user), 403, 'Only the Super Admin can change this account.');
    }

    private function formData(): array
    {
        return ['projects' => Project::query()->with('contract.agency', 'contract.client')->get()
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
            'projects.*' => ['integer', 'exists:projects,id'],
        ], ['projects.required' => 'Choose at least one project for this PI.', 'phone.regex' => 'Enter a 10-digit mobile number.']);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
