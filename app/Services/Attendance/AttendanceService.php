<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSheet;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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

    /** @param array<int, array<int,string>> $codesByEntry entry id => [day => code] */
    public function save(AttendanceSheet $sheet, array $codesByEntry): void
    {
        $this->assertEditable($sheet);
        $days = $sheet->month->daysInMonth;

        DB::transaction(function () use ($sheet, $codesByEntry, $days) {
            foreach ($sheet->entries as $entry) {
                if (! isset($codesByEntry[$entry->id])) {
                    continue;
                }
                $codes = [];
                for ($d = 1; $d <= $days; $d++) {
                    $codes[$d] = strtoupper(trim((string) ($codesByEntry[$entry->id][$d] ?? 'A')));
                }
                $entry->update(['codes' => $codes, 'paid_days' => AttendanceCodes::paidDays($codes)]);
            }
        });
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
