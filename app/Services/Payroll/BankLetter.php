<?php

namespace App\Services\Payroll;

use App\Models\Agency;
use App\Models\AttendanceSheet;
use App\Services\Billing\BillNumbers;
use App\Support\AmountInWords;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Salary transfer letter to the agency's bank for a month: every employee of the agency's projects whose month is
 * locked, with net pay (as on the salary payout sheet). SBI accounts: Emp ID, name, amount; other banks (NEFT):
 * bank, account no., IFSC, amount. Staff without bank details are listed apart and not included in the total.
 */
final class BankLetter
{
    public function __construct(private readonly SalaryPayoutSheet $payouts) {}

    /** Locked and not-yet-locked sheets of the agency for the month (optionally only some projects). */
    public function sheets(Agency $agency, CarbonImmutable $month, ?array $projectIds = null): array
    {
        $all = AttendanceSheet::query()->with('project.contract', 'entries')
            ->where('month', $month->startOfMonth()->toDateString())
            ->whereHas('project.contract', fn ($q) => $q->where('agency_id', $agency->id))
            ->when($projectIds, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->get()->sortBy(fn ($s) => $s->project->contract->contract_no.'|'.$s->project->code)->values();

        return [$all->filter->isLocked()->values(), $all->reject->isLocked()->values()];
    }

    /** @param Collection<int, AttendanceSheet> $sheets locked sheets */
    public function build(Agency $agency, CarbonImmutable $month, Collection $sheets, ?string $projectCode = null): array
    {
        $lines = $sheets->flatMap(fn ($sheet) => $this->payouts->lines($sheet->loadMissing('project'))
            ->map(fn ($l) => $l + ['project' => $sheet->project]))
            ->filter(fn ($l) => $l['net'] > 0)
            ->sortBy(fn ($l) => $l['employee']->emp_code, SORT_NATURAL)->values();
        [$withBank, $missing] = $lines->partition(fn ($l) => filled($l['employee']->account_number) && filled($l['employee']->ifsc));
        [$sbi, $neft] = $withBank->partition(fn ($l) => str_starts_with(strtoupper((string) $l['employee']->ifsc), 'SBIN'));
        $total = (int) $withBank->sum('net');
        $fy = BillNumbers::financialYear($month);

        return [
            'agency' => $agency, 'month' => $month, 'sheets' => $sheets,
            'sbi' => $sbi->values(), 'neft' => $neft->values(), 'missing' => $missing->values(),
            'total' => $total, 'totalWords' => AmountInWords::rupees(Money::format($total)),
            'reference' => $agency->numberPrefix().'/BANK/'.($projectCode ? $projectCode.'/' : '').$month->format('m').'/'.$fy,
        ];
    }
}
