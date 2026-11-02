<?php

namespace App\Services\Payroll;

use App\Models\AttendanceSheet;
use App\Services\Billing\BillLine;
use App\Services\Billing\GemBillCalculator;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Salary payout (bank transfer) sheet for a locked attendance month.
 *
 * Earned salary is worked out exactly as on the sub-bill (rate x paid days / days in month, or rate x paid days
 * for daily posts, rounded to whole rupees). Employee deductions, as confirmed by the user ("12% and 0.75% as per rule"):
 *   EPF 12%   on earned wages up to the Rs 15,000 wage ceiling; deducted when the employee is marked
 *             "PF applicable", or always when the monthly wage is Rs 15,000 or less (membership is compulsory then)
 *   ESIC 0.75% on earned wages, only when the monthly wage is Rs 21,000 or less; rounded up to the next rupee
 * EPF is rounded to the nearest rupee. "Monthly wage" is the monthly rate, or the month's earnings for daily posts.
 */
final class SalaryPayoutSheet
{
    public const EPF_PERCENT = '12';
    public const EPF_WAGE_CEILING = 15000;
    public const ESIC_PERCENT = '0.75';
    public const ESIC_WAGE_LIMIT = 21000;

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
        $totals = $daily ? (new GemBillCalculator('0', '0'))->daily($billLines) : (new GemBillCalculator('0', '0'))->monthly($billLines);

        return collect($totals->lines)->map(function (BillLine $line) use ($daily) {
            $employee = $line->ref->employee;
            $earned = Money::toPaise($line->amount);
            $monthlyWage = $daily ? $earned : Money::toPaise($line->rate);

            return [
                'entry' => $line->ref, 'employee' => $employee, 'paid_days' => $line->paidDays, 'rate' => $line->rate,
                'earned' => $earned, ...$this->deductions($earned, $monthlyWage, (bool) $employee->pf_applicable),
            ];
        });
    }

    /** @return array{epf: int, esic: int, net: int} in paise */
    public function deductions(int $earned, int $monthlyWage, bool $pfApplicable): array
    {
        $epf = 0;
        if ($pfApplicable || $monthlyWage <= self::EPF_WAGE_CEILING * 100) {
            $epf = $this->roundNearest(Money::percent(min($earned, self::EPF_WAGE_CEILING * 100), self::EPF_PERCENT));
        }
        $esic = 0;
        if ($monthlyWage <= self::ESIC_WAGE_LIMIT * 100) {
            $esic = $this->roundUp(Money::percent($earned, self::ESIC_PERCENT));
        }

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
