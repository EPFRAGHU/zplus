<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Support\Activity\Activity;
use App\Support\Agency\AgencyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * After signing in, a login for both agencies chooses which company to work in: SWACHHA, Z Plus or both
 * (owner, 18 Nov 2026). The choice narrows every page exactly like an agency login, until changed or signed out.
 */
class AgencyChoiceController extends Controller
{
    public function show(Request $request): View
    {
        abort_unless(AgencyContext::canSwitch($request->user()), 403);

        return view('auth.choose-agency', [
            'agencies' => AgencyContext::unscoped(fn () => Agency::query()->orderBy('name')->get()),
            'current' => AgencyContext::chosen($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(AgencyContext::canSwitch($request->user()), 403);
        $ids = AgencyContext::unscoped(fn () => Agency::query()->pluck('id')->map(fn ($id) => (string) $id)->all());
        $data = $request->validate(['agency' => ['required', Rule::in(array_merge($ids, ['both']))]]);
        if ($data['agency'] === 'both') {
            $request->session()->forget(AgencyContext::SESSION_KEY);
            $name = 'Both agencies';
        } else {
            $request->session()->put(AgencyContext::SESSION_KEY, (int) $data['agency']);
            $name = AgencyContext::unscoped(fn () => Agency::query()->find($data['agency']))->name;
        }
        Activity::log('agency_chosen', null, [], 'Working in: '.$name, $request->user(), 'Sign-in');

        return redirect()->intended(route('dashboard'));
    }
}
