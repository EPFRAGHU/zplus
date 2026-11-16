<?php

namespace App\Services\Billing;

use App\Models\DocumentSequence;
use App\Models\GemContract;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Bill numbers as the agencies write them (owner's rule, November 2026):
 *   tax invoice of a work order for a month:  SW/001/26-27
 *   its sub-bills, one per project:           SW/001/26-27(HTA), SW/001/26-27(CCRH), SW/001/26-27(MPH)
 * SW / ZP is the agency's number prefix; 001 runs per agency per financial year (April–March) and is taken once per
 * work order and month, so every sub-bill of that work order and month shares it with the tax invoice.
 */
final class BillNumbers
{
    /** "26-27" for any month from April 2026 to March 2027. */
    public static function financialYear(CarbonInterface $month): string
    {
        $start = $month->month >= 4 ? $month->year : $month->year - 1;

        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }

    /** The serial (1, 2, 3 …) of this work order and month, taken from the agency's series the first time it is needed. */
    public function serial(GemContract $contract, CarbonInterface $month): int
    {
        $mapKey = "bill-serial-of:{$contract->id}:{$month->format('Y-m')}";

        return DB::transaction(function () use ($contract, $month, $mapKey) {
            $mapped = DocumentSequence::query()->where('key', $mapKey)->lockForUpdate()->value('last_number');
            if ($mapped) {
                return (int) $mapped;
            }
            $serial = DocumentSequence::next("bill-serial:{$contract->agency_id}:".self::financialYear($month));
            DocumentSequence::query()->create(['key' => $mapKey, 'last_number' => $serial]);

            return $serial;
        });
    }

    public function taxInvoiceNumber(GemContract $contract, CarbonInterface $month): string
    {
        return sprintf('%s/%03d/%s', $contract->agency->numberPrefix(), $this->serial($contract, $month), self::financialYear($month));
    }

    public function subBillNumber(GemContract $contract, Project $project, CarbonInterface $month): string
    {
        return $this->taxInvoiceNumber($contract, $month).'('.$project->code.')';
    }

    /** What the next tax invoice number would be, without taking a number. */
    public function preview(GemContract $contract, CarbonInterface $month): string
    {
        $mapped = DocumentSequence::query()->where('key', "bill-serial-of:{$contract->id}:{$month->format('Y-m')}")->value('last_number');
        $fy = self::financialYear($month);
        $serial = $mapped ?: ((int) DocumentSequence::query()->where('key', "bill-serial:{$contract->agency_id}:{$fy}")->value('last_number')) + 1;

        return sprintf('%s/%03d/%s', $contract->agency->numberPrefix(), $serial, $fy);
    }
}
