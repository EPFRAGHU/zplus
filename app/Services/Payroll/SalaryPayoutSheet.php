<?php

namespace App\Services\Payroll;

use App\Models\AttendanceSheet;
use App\Services\Billing\BillLine;
use App\Services\Billing\GemBillCalculator;
use App\Support\Money;
use App\Support\Statutory;
use Illuminate\Support\Collection;

/**
 * Salary payout (bank transfer) sheet for a locked attendance month.
 *
 * Earned salary is worked out exactly as on the sub-bill (rate x paid days / days in month, or rate x paid days
 * for daily posts, rounded to whole rupees). Employee deductions apply only when switched on for the employee:
 *   EPF 12%    on earned wages up to the Rs 15,000 ceiling, rounded to the nearest rupee
 *   ESIC 0.75% on earned wages, rounded up to the next rupee
 */
final class SalaryPayoutSheet
{
    /** @return Collection<int, array{entry: mixed, employee: mixed, paid_days: string, rate: string, earned: int, epf: int, esic: int, net: int}> amounts in paise */
    public function lines(AttendanceSheet $sheet): Collection
    {
        $sheet->loadMissing('entries.employee.post', 'project.contract');
        $daily = $sheet->project->contract->isDaily();
        $days = $sheet->month->daysInMonth;

        $billLines = $sheet->entries->sortBy('employee.name')->values()->map(function ($entry) use ($daily, $days) {
            $paid = number_format((float) $entry->paid_days, 1, '.', '');

            return $daily ? BillLine::daily($entry->employee->name, $entry->employee->rate(), $paid, $entry)
                : BillLine::monthly($entry->employee->name, $entry->employee->rate(), $days, $paid, $entry);
        })->all();
        $calc = new GemBillCalculator('0', '0');
        $totals = $daily ? $calc->daily($billLines) : $calc->monthly($billLines);

        return collect($totals->lines)->map(function (BillLine $line) {
            $employee = $line->ref->employee;
            $earned = Money::toPaise($line->amount);

            return [
                'entry' => $line->ref, 'employee' => $employee, 'paid_days' => $line->paidDays, 'rate' => $line->rate,
                'earned' => $earned, ...$this->deductions($earned, (bool) $employee->pf_applicable, (bool) $employee->esic_applicable),
            ];
        });
    }

    /** @return array{epf: int, esic: int, net: int} in paise */
    public function deductions(int $earned, bool $epfOn, bool $esicOn): array
    {
        $epf = $epfOn ? $this->roundNearest(Money::percent(Statutory::epfBase($earned), Statutory::EMPLOYEE_EPF_PERCENT)) : 0;
        $esic = $esicOn ? $this->roundUp(Money::percent($earned, Statutory::EMPLOYEE_ESIC_PERCENT)) : 0;

        return ['epf' => $epf, 'esic' => $esic, 'net' => $earned - $epf - $esic];
    }

    private function roundNearest(int $paise): int
    {
        return intdiv($paise + 50, 100) * 100;
    }

    private function roundUp(int $paise): int
    {
        return intdiv($paise + 99, 100) * 100;
    }
}
