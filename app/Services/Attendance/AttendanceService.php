<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSheet;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class AttendanceService
{
    /** Opens (or returns) the month's sheet, pre-filled for every active employee on the project's posts. */
    public function open(Project $project, CarbonImmutable $month, array $holidays = []): AttendanceSheet
    {
        $month = $month->startOfMonth();

        return DB::transaction(function () use ($project, $month, $holidays) {
            $sheet = AttendanceSheet::query()->firstOrCreate(
                ['project_id' => $project->id, 'month' => $month->toDateString()],
                ['holidays' => implode(',', $holidays), 'status' => 'draft'],
            );

            if (! $sheet->isLocked()) {
                $existing = $sheet->entries()->pluck('employee_id')->all();
                $employees = Employee::query()
                    ->whereIn('post_id', $project->posts()->pluck('id'))
                    ->whereIn('status', ['active', 'notice'])
                    ->whereNotIn('id', $existing)
                    ->get();

                foreach ($employees as $employee) {
                    $codes = AttendanceCodes::defaultMonth($month, $sheet->holidayDays(), $project->saturday_off ?? true);
                    $codes = $this->blankOutsideEmployment($codes, $month, $employee);
                    $sheet->entries()->create([
                        'employee_id' => $employee->id,
                        'codes' => $codes,
                        'paid_days' => AttendanceCodes::paidDays($codes),
                    ]);
                }
            }

            return $sheet->load('entries.employee.post', 'project.contract');
        });
    }

    /**
     * @param  array<int, array<int,string>>  $codesByEntry  entry id => [day => code]
     * Once the PI has submitted the month, every code the agency changes is recorded (who, when, old → new).
     */
    public function save(AttendanceSheet $sheet, array $codesByEntry, ?User $by = null): int
    {
        $this->assertEditable($sheet);
        $days = $sheet->month->daysInMonth;
        $record = $sheet->isSubmitted();

        return DB::transaction(function () use ($sheet, $codesByEntry, $days, $record, $by) {
            $changed = 0;
            $log = [];
            foreach ($sheet->entries()->with('employee')->get() as $entry) {
                if (! isset($codesByEntry[$entry->id])) {
                    continue;
                }
                $codes = [];
                for ($d = 1; $d <= $days; $d++) {
                    $codes[$d] = strtoupper(trim((string) ($codesByEntry[$entry->id][$d] ?? 'A')));
                    $old = strtoupper((string) ($entry->codes[$d] ?? $entry->codes[(string) $d] ?? 'A'));
                    if ($old !== $codes[$d] && count($log) < 200) {
                        $log[($entry->employee?->name ?? 'Staff').' · day '.$d] = [$old, $codes[$d]];
                    }
                    if ($record && $old !== $codes[$d]) {
                        $sheet->changes()->create(['employee_id' => $entry->employee_id, 'employee_name' => $entry->employee?->name ?? '',
                            'day' => $d, 'old_code' => $old, 'new_code' => $codes[$d], 'user_id' => $by?->id]);
                        $changed++;
                    }
                }
                $entry->update(['codes' => $codes, 'paid_days' => AttendanceCodes::paidDays($codes)]);
            }
            if ($log) {
                \App\Support\Activity\Activity::log('updated', $sheet, $log, count($log).' attendance '.\Illuminate\Support\Str::plural('code', count($log)).' changed', $by);
            }

            return $changed;
        });
    }

    /** The PI submits the month: it is frozen for the PI and the certificate PDF is made exactly as submitted. */
    public function submit(AttendanceSheet $sheet, User $pi): void
    {
        if (! in_array($sheet->status, ['draft'], true)) {
            throw new RuntimeException('This month has already been submitted.');
        }
        if ($sheet->entries()->count() === 0) {
            throw new RuntimeException('There is no one on this sheet yet. Ask the agency to link the staff to your project.');
        }
        $sheet->forceFill(['status' => 'submitted', 'submitted_at' => now(), 'submitted_by' => $pi->id])->save();
        $path = sprintf('attendance-submitted/%s/%s-%s-%s.pdf', $sheet->month->format('Y-m'), $sheet->project->code, $sheet->id, now()->format('YmdHis'));
        Storage::disk('local')->put($path, $this->certificatePdf($sheet->fresh()));
        $sheet->forceFill(['submitted_pdf_path' => $path])->save();
    }

    /** The Monthly Attendance and Working Certificate (no bank details: it goes to the PI). */
    public function certificatePdf(AttendanceSheet $sheet, bool $showBank = false): string
    {
        $sheet->loadMissing('entries.employee.post', 'project.contract.client', 'project.contract.agency', 'submitter', 'changes');

        return Pdf::loadView('pdf.attendance-certificate', ['sheet' => $sheet, 'showBank' => $showBank])->setPaper('a4', 'landscape')->output();
    }

    public function lock(AttendanceSheet $sheet, User $user, ?string $certificatePath): void
    {
        $this->assertEditable($sheet);
        if ($sheet->entries()->count() === 0) {
            throw new RuntimeException('There is no one on this sheet. Link employees to the project posts first.');
        }
        $sheet->forceFill([
            'status' => 'locked',
            'certificate_path' => $certificatePath ?? $sheet->certificate_path,
            'locked_at' => now(),
            'locked_by' => $user->id,
        ])->save();
    }

    public function unlock(AttendanceSheet $sheet): void
    {
        if ($sheet->invoice()->exists()) {
            throw new RuntimeException('A bill was made from this sheet. Delete the draft bill before unlocking.');
        }
        $sheet->forceFill(['status' => 'draft', 'locked_at' => null, 'locked_by' => null])->save();
    }

    private function assertEditable(AttendanceSheet $sheet): void
    {
        if ($sheet->isLocked()) {
            throw new RuntimeException('This month is locked. Unlock it to make changes.');
        }
    }

    /** Days before joining or after exit are not paid. */
    private function blankOutsideEmployment(array $codes, CarbonImmutable $month, Employee $e): array
    {
        foreach ($codes as $d => $code) {
            $date = $month->setDay($d);
            if (($e->joining_date && $date->lt($e->joining_date)) || ($e->exit_date && $date->gt($e->exit_date))) {
                $codes[$d] = 'A';
            }
        }

        return $codes;
    }
}
