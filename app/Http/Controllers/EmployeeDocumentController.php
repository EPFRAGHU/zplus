<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Employee::DOCUMENT_TYPES))],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'expires_on' => ['nullable', 'date'],
        ]);
        $file = $request->file('file');
        $employee->documents()->create([
            'type' => $data['type'],
            'path' => $file->store("employees/{$employee->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'expires_on' => $data['expires_on'] ?? null,
        ]);

        return back()->with('status', 'Document uploaded.');
    }

    public function show(EmployeeDocument $document): StreamedResponse
    {
        Gate::authorize('view-bank-details');

        return Storage::disk('local')->response($document->path, $document->original_name);
    }

    public function destroy(EmployeeDocument $document): RedirectResponse
    {
        Gate::authorize('manage-masters');
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('status', 'Document removed.');
    }
}
