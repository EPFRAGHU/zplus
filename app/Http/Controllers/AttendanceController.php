<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSheet;
use App\Models\Project;
use App\Services\Attendance\AttendanceCodes;
use App\Services\Attendance\AttendanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request): View
    {
        $month = $this->month($request->input('month'));
        $sheets = AttendanceSheet::query()->where('month', $month->toDateString())->withCount('entries')->with('invoice')->get()->keyBy('project_id');
        $projects = Project::query()->with('contract.agency')->withCount(['employees' => fn ($q) => $q->whereIn('employees.status', ['active', 'notice'])])
            ->orderBy('name')->get();

        return view('attendance.index', compact('month', 'sheets', 'projects'));
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'month' => ['required', 'date_format:Y-m'],
            'holidays' => ['nullable', 'regex:/^\s*\d{1,2}(\s*,\s*\d{1,2})*\s*$/'],
        ], ['holidays.regex' => 'List holiday dates as numbers separated by commas, e.g. 2, 19, 20.']);
        $holidays = array_values(array_filter(array_map('intval', explode(',', (string) ($data['holidays'] ?? '')))));
        $sheet = $this->attendance->open(Project::query()->findOrFail($data['project_id']), $this->month($data['month']), $holidays);

        return redirect()->route('attendance.show', $sheet);
    }

    public function show(AttendanceSheet $sheet): View
    {
        $sheet->load('entries.employee.post', 'project.contract', 'invoice');

        return view('attendance.show', ['sheet' => $sheet, 'codes' => AttendanceCodes::LABELS]);
    }

    public function update(Request $request, AttendanceSheet $sheet): RedirectResponse
    {
        $data = $request->validate([
            'codes' => ['required', 'array'],
            'codes.*' => ['array'],
            'codes.*.*' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceCodes::LABELS))],
        ]);

        return $this->attempt(fn () => $this->attendance->save($sheet->load('entries'), $data['codes']), 'Attendance saved.');
    }

    public function lock(Request $request, AttendanceSheet $sheet): RedirectResponse
    {
        $request->validate(['certificate' => [$sheet->certificate_path ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']],
            ['certificate.required' => 'Upload the certificate signed by the PI before locking the month.']);
        $path = $request->file('certificate')?->store("certificates/{$sheet->month->format('Y-m')}", 'local');

        return $this->attempt(fn () => $this->attendance->lock($sheet, $request->user(), $path), 'Month locked. You can now make the bill.');
    }

    public function unlock(AttendanceSheet $sheet): RedirectResponse
    {
        Gate::authorize('unlock-attendance');

        return $this->attempt(fn () => $this->attendance->unlock($sheet), 'Month unlocked.');
    }

    /** Printable "Monthly Attendance and Working Certificate" for the PI to sign. */
    public function certificate(AttendanceSheet $sheet): Response
    {
        $sheet->load('entries.employee.post', 'project.contract.client');
        $showBank = Gate::allows('view-bank-details');

        return Pdf::loadView('pdf.attendance-certificate', compact('sheet', 'showBank'))
            ->setPaper('a4', 'landscape')->stream("attendance-{$sheet->project->code}-{$sheet->month->format('Y-m')}.pdf");
    }

    public function scan(AttendanceSheet $sheet): Response
    {
        abort_unless($sheet->certificate_path && Storage::disk('local')->exists($sheet->certificate_path), 404);

        return Storage::disk('local')->response($sheet->certificate_path);
    }

    private function month(?string $value): CarbonImmutable
    {
        return $value ? CarbonImmutable::createFromFormat('Y-m', substr($value, 0, 7))->startOfMonth()
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    }

    private function attempt(callable $action, string $done): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->withErrors(['attendance' => $e->getMessage()]);
        }

        return back()->with('status', $done);
    }
}
