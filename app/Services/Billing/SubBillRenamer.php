<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Support\Activity\Activity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * When a project's short name (code) changes, its open sub-bills take the new code in their number (owner, 22 Nov 2026):
 * SW/004/26-27(SW/007/26-27) becomes SW/004/26-27(SDOH). The serial (004) never changes. Paid bills, and bills in a paid
 * tax invoice, keep their number. A tax invoice holding a renamed bill is worked out again; an approved bill needs
 * approving again (its approval covers the number). Every rename is recorded in the Activity log.
 */
final class SubBillRenamer
{
    /** @return array{renamed: array<string, string>, kept: list<string>} old number => new number, and paid bills left as they were */
    public function forProject(Project $project, ?User $user = null): array
    {
        $project->loadMissing('contract.agency');
        $prefix = preg_quote($project->contract->agency->numberPrefix(), '#');
        $renamed = [];
        $kept = [];

        DB::transaction(function () use ($project, $prefix, $user, &$renamed, &$kept) {
            $bills = Invoice::query()->with('taxInvoice')->where('project_id', $project->id)->orderBy('invoice_no')->get();
            $taxInvoices = collect();
            foreach ($bills as $bill) {
                if (! preg_match('#^('.$prefix.'/\d{3}/\d{2}-\d{2})\((.*)\)(-\d+)?$#', $bill->invoice_no, $m)) {
                    continue;   // old-style number (e.g. ICMR/MPH/01/2026): Recalculate brings it to the new style
                }
                $wanted = $m[1].'('.$project->code.')';
                if ($bill->invoice_no === $wanted) {
                    continue;
                }
                if ($bill->status === 'paid' || $bill->taxInvoice?->status === 'paid') {
                    $kept[] = $bill->invoice_no;

                    continue;
                }
                $number = $wanted;
                for ($n = 2; Invoice::query()->where('agency_id', $bill->agency_id)->where('invoice_no', $number)->whereKeyNot($bill->id)->exists(); $n++) {
                    $number = $wanted.'-'.$n;   // another project of the agency already uses this code in the same serial
                }
                $old = $bill->invoice_no;
                $bill->update(['invoice_no' => $number]);
                $renamed[$old] = $number;
                Activity::log('updated', $bill, ['invoice_no' => [$old, $number]], "Sub-bill renamed: {$old} → {$number} (project short name changed)", $user, section: 'Billing', label: $number);
                if ($bill->taxInvoice) {
                    $taxInvoices[$bill->taxInvoice->id] = $bill->taxInvoice;
                }
            }
            foreach ($taxInvoices as $tax) {
                try {
                    app(TaxInvoiceBuilder::class)->refresh($tax->refresh());
                } catch (RuntimeException) {
                    // a tax invoice that cannot be worked out again keeps its figures; its sub-bills show their new numbers
                }
            }
        });

        return ['renamed' => $renamed, 'kept' => $kept];
    }
}
