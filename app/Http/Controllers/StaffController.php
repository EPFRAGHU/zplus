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
        $employees = Employee::query()->with('agency', 'client', 'post.project')->get()
            ->sortBy(fn ($e) => ($e->agency?->name ?? 'zz').'|'.$e->name)->values();
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
            'employees' => $employees, 'employee' => $employee, 'months' => $months,
            'letters' => $employee ? GeneratedDocument::query()->where('documentable_type', Employee::class)->where('documentable_id', $employee->id)->get() : collect(),
            'showBank' => Gate::allows('view-bank-details'),
        ]);
    }
}
