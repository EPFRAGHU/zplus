<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Client;
use App\Services\Masters\ClientMerge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Developer only: brings each agency down to the single NIHR client (see ClientMerge). */
class ClientMergeController extends Controller
{
    public function show(ClientMerge $merge): View
    {
        Gate::authorize('merge-clients');

        return view('clients.merge', [
            'rows' => Agency::query()->orderBy('name')->get()->map(fn (Agency $a) => [
                'agency' => $a, 'clients' => $merge->clients($a), 'suggested' => $merge->suggestedTarget($a),
            ])->filter(fn ($r) => $r['clients']->isNotEmpty())->values(),
            'name' => ClientMerge::NAME,
            'labels' => ClientMerge::labels(),
        ]);
    }

    public function store(Request $request, ClientMerge $merge): RedirectResponse
    {
        Gate::authorize('merge-clients');
        $data = $request->validate([
            'agency_id' => ['required', new \App\Rules\ExistsInMyAgency(Agency::class)],
            'target_id' => ['required', new \App\Rules\ExistsInMyAgency(Client::class)],
            'name' => ['required', 'string', 'max:255'],
            'confirm' => ['required', 'in:MERGE'],
        ], ['confirm.in' => 'Type MERGE in capitals to confirm.', 'confirm.required' => 'Type MERGE in capitals to confirm.']);
        $agency = Agency::query()->findOrFail($data['agency_id']);
        $target = Client::query()->where('agency_id', $agency->id)->findOrFail($data['target_id']);
        $moved = $merge->merge($agency, $target, trim($data['name']));

        return redirect()->route('clients.merge')->with('status', sprintf('%s: now one client. Moved %d work orders, %d employees and %d tax invoices.',
            $agency->name, $moved['gem_contracts'], $moved['employees'], $moved['tax_invoices']));
    }
}
