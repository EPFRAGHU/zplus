<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\Import\GemWorkbookImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class ImportController extends Controller
{
    public function create(): View
    {
        Gate::authorize('gem-import');

        return view('import.create', ['clients' => Client::query()->with('agency')->orderBy('name')->get()]);
    }

    public function preview(Request $request, GemWorkbookImporter $importer): View|RedirectResponse
    {
        Gate::authorize('gem-import');
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'workbook' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], ['client_id.required' => 'Choose the client these contracts belong to. Add it under Clients first if it is missing.']);

        $path = $request->file('workbook')->store('imports', 'local');
        try {
            $plan = $importer->analyse(Storage::disk('local')->path($path));
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            report($e);

            return back()->withErrors(['workbook' => 'This file could not be read as a GeM workbook. Check it opens in Excel and has the agency sheets.']);
        }
        $request->session()->put('import', ['path' => $path, 'client_id' => (int) $data['client_id']]);

        return view('import.preview', ['plan' => $plan, 'summary' => $plan->summary(), 'client' => Client::query()->find($data['client_id']),
            'fileName' => $request->file('workbook')->getClientOriginalName()]);
    }

    public function commit(Request $request, GemWorkbookImporter $importer): RedirectResponse
    {
        Gate::authorize('gem-import');
        $pending = $request->session()->pull('import');
        if (! $pending || ! Storage::disk('local')->exists($pending['path'])) {
            return redirect()->route('import.create')->withErrors(['workbook' => 'Upload the workbook again; the preview expired.']);
        }
        $file = Storage::disk('local')->path($pending['path']);
        $result = $importer->commit($importer->analyse($file), Client::query()->findOrFail($pending['client_id']));
        Storage::disk('local')->delete($pending['path']);

        $msg = "Imported {$result['contracts']} contracts, {$result['posts']} posts and {$result['employees']} employees.";
        if ($result['skipped_contracts']) {
            $msg .= " {$result['skipped_contracts']} contracts were already in the app and were skipped.";
        }

        return redirect()->route('contracts.index')->with('status', $msg);
    }
}
