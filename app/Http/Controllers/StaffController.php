<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEntry;
use App\Models\Employee;
use App\Models\GeneratedDocument;
use App\Models\InvoiceLine;
use App\Services\Payroll\SalaryPayoutSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Employee Management: all employees in one list; pick one to see everything about them in one place:
 * profile, KYC and documents, month-wise attendance, and month-wise payments (earned, EPF, ESIC, net, bank, bill).
 */
class StaffController extends Controller
{
    public function index(Request $request, SalaryPayoutSheet $payouts): View
    {
        // "All employees" list: click Emp ID, Name or Post · project to sort A→Z, again for Z→A (natural order: SW-1002 before SW-1010).
        $sort = in_array($request->query('sort'), ['emp_code', 'name', 'post'], true) ? $request->query('sort') : null;
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $key = fn ($e) => match ($sort) {
            'emp_code' => (string) $e->emp_code,
            'name' => (string) $e->name,
            'post' => $e->post ? $e->post->designation.' · '.$e->post->project->code : "\u{FFFF}",   // no post: last
            default => ($e->agency?->name ?? 'zz').'|'.$e->name,
        };
        $employees = Employee::query()->with('agency', 'client', 'post.project')->get()
            ->sort(fn ($a, $b) => ($dir === 'desc' && $sort ? -1 : 1) * strnatcasecmp($key($a), $key($b)))->values();
        $employee = $request->integer('employee') ? Employee::query()->with('agency', 'client', 'post.project.contract', 'documents')->find($request->integer('employee')) : null;

        $months = collect();
        if ($employee) {
            $entries = AttendanceEntry::query()->with('sheet.project.contract')->where('employee_id', $employee->id)->get()->filter(fn ($e) => $e->sheet)
                ->sortByDesc(fn ($e) => $e->sheet->month->format('Y-m').'-'.$e->sheet->project->code)->values();
            $billLines = InvoiceLine::query()->with('invoice')->where('employee_id', $employee->id)->get()->keyBy(fn ($l) => $l->invoice->attendance_sheet_id);
            $months = $entries->map(function ($entry) use ($payouts, $employee, $billLines) {
                $sheet = $entry->sheet;
                $pay = $sheet->isLocked() ? $payouts->lines($sheet)->first(fn ($l) => $l['employee']->id === $employee->id) : null;

                return [
                    'sheet' => $sheet, 'entry' => $entry,
                    'codes' => collect($entry->codes ?? [])->filter()->countBy()->sortDesc(),
                    'pay' => $pay, 'bill' => $billLines[$sheet->id] ?? null,
                ];
            });
        }

        return view('staff.index', [
            'sort' => $sort, 'dir' => $dir,
            'sortLinks' => collect(['emp_code', 'name', 'post'])->mapWithKeys(fn ($col) => [$col => [
                'url' => route('staff.index', array_filter(['employee' => $request->integer('employee') ?: null, 'sort' => $col,
                    'dir' => $sort === $col && $dir === 'asc' ? 'desc' : 'asc'])),
                'icon' => $sort === $col ? ($dir === 'asc' ? 'bi-sort-alpha-down' : 'bi-sort-alpha-up-alt') : 'bi-arrow-down-up text-secondary',
                'title' => $sort === $col && $dir === 'asc' ? 'Sorted A→Z. Click for Z→A' : 'Click to sort A→Z',
            ]])->all(),
            'employees' => $employees, 'employee' => $employee, 'months' => $months,
            'letters' => $employee ? GeneratedDocument::query()->where('documentable_type', Employee::class)->where('documentable_id', $employee->id)->get() : collect(),
            'showBank' => Gate::allows('view-bank-details'),
        ]);
    }
}
