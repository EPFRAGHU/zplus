<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Post;
use App\Models\Project;
use App\Services\MasterImport\MasterImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Excel templates for master data (agencies → employees): download, fill, upload, check, save. */
class MasterImportController extends Controller
{
    public function __construct(private readonly MasterImporter $importer) {}

    public function index(): View
    {
        Gate::authorize('import');
        $counts = ['agencies' => Agency::count(), 'clients' => Client::count(), 'contracts' => GemContract::count(),
            'projects' => Project::count(), 'posts' => Post::count(), 'employees' => Employee::count()];

        return view('master-import.index', ['sheets' => $this->importer->sheets(), 'counts' => $counts]);
    }

    public function template(Request $request, string $type): StreamedResponse
    {
        Gate::authorize('import');
        abort_unless(in_array($type, MasterImporter::ORDER, true), 404);
        $withData = $request->boolean('data');
        $book = $this->importer->template($type, $withData);
        $name = 'ZPlus-'.str_replace(' ', '-', $this->importer->sheet($type)->title()).($withData ? '-'.now()->format('Y-m-d') : '-template').'.xlsx';

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $name,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function preview(Request $request, string $type): View|RedirectResponse
    {
        Gate::authorize('import');
        abort_unless(in_array($type, MasterImporter::ORDER, true), 404);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']],
            ['file.required' => 'Choose the filled Excel file.', 'file.mimes' => 'Upload the Excel template (.xlsx).']);
        $path = $request->file('file')->store('imports/masters', 'local');
        \App\Support\Uploads\StaleImports::purge('imports/masters');
        try {
            $analysis = $this->importer->analyse($type, Storage::disk('local')->path($path));
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            report($e);

            return back()->withErrors(["file_{$type}" => 'This file could not be read. Check it opens in Excel and was made from the template on this page.']);
        }
        if ($analysis['problems']) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(["file_{$type}" => implode(' ', $analysis['problems'])]);
        }
        $request->session()->put("master_import.{$type}", $path);

        return view('master-import.preview', ['sheet' => $this->importer->sheet($type), 'analysis' => $analysis,
            'fileName' => $request->file('file')->getClientOriginalName()]);
    }

    public function commit(Request $request, string $type): RedirectResponse
    {
        Gate::authorize('import');
        abort_unless(in_array($type, MasterImporter::ORDER, true), 404);
        $path = $request->session()->pull("master_import.{$type}");
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return redirect()->route('master-import.index')->withErrors(["file_{$type}" => 'The check expired. Upload the file again.']);
        }
        $result = $this->importer->commit($type, Storage::disk('local')->path($path));
        Storage::disk('local')->delete($path);

        $title = $this->importer->sheet($type)->title();
        $msg = "{$title}: {$result['created']} added, {$result['updated']} updated.";
        \App\Support\Activity\Activity::log('imported', null, [], "{$msg} Skipped with errors: {$result['skipped']}.", section: 'Data upload', label: $title);
        if ($result['skipped']) {
            $msg .= " {$result['skipped']} rows with errors were skipped; fix them in the file and upload it again.";
        }

        return redirect()->route('master-import.index')->with('status', $msg);
    }
}
