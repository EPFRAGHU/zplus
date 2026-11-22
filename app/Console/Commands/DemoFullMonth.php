<?php

namespace App\Console\Commands;

use App\Models\AttendanceSheet;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\User;
use App\Services\Approvals\Approvals;
use App\Services\Attendance\AttendanceCodes;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\TaxInvoiceBuilder;
use App\Support\Activity\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * LAPTOP DEMO ONLY (owner, 22 Nov 2026), on a copy of the live data: for every work order (or one), every project's staff get a
 * full month of attendance, the month is locked, a sub-bill is made per project (SW/001/26-27(CODE)) and one tax invoice per
 * work order clubs those sub-bills. Approvals are recorded as "(demo)". Refused on the live site. Months already locked or
 * billed are left exactly as they are.
 */
class DemoFullMonth extends Command
{
    protected $signature = 'demo:full-month
        {month=2026-10 : Month as YYYY-MM}
        {--contract= : Only this GeM contract number (default: every work order)}
        {--user= : E-mail of the login that makes the bills (default: the developer)}
        {--force : Do not ask to type DEMO}';

    protected $description = 'Laptop demo: full attendance for a month, sub-bills per project and one tax invoice per work order (never on the live site)';

    public const LIVE_HOST = 'epf-dashboard.online';

    public function handle(AttendanceService $attendance, InvoiceGenerator $bills, TaxInvoiceBuilder $taxInvoices, Approvals $approvals): int
    {
        if (app()->environment('production') || str_contains((string) config('app.url'), self::LIVE_HOST)) {
            $this->error('This demo is for a laptop copy only. It refuses to run on the live site (APP_ENV=production or APP_URL is the live address).');

            return self::FAILURE;
        }
        if (! preg_match('/^\d{4}-\d{2}$/', (string) $this->argument('month'))) {
            $this->error('Give the month as YYYY-MM, e.g. 2026-10.');

            return self::FAILURE;
        }
        $month = CarbonImmutable::createFromFormat('!Y-m', $this->argument('month'))->startOfMonth();

        $user = $this->option('user')
            ? User::query()->where('email', $this->option('user'))->first()
            : (User::query()->where('is_developer', true)->first() ?? User::query()->where('role', 'admin')->orderBy('id')->first());
        if (! $user) {
            $this->error('No login found to make the bills. Pass --user=your@email.');

            return self::FAILURE;
        }

        $contracts = GemContract::query()->with(['agency', 'projects' => fn ($q) => $q->orderBy('code')])
            ->when($this->option('contract'), fn ($q, $no) => $q->where('contract_no', $no))
            ->orderBy('agency_id')->orderBy('contract_no')->get()
            ->filter(fn ($c) => $c->projects->isNotEmpty())->values();
        if ($contracts->isEmpty()) {
            $this->error($this->option('contract') ? "No work order {$this->option('contract')} with projects." : 'No work orders with projects.');

            return self::FAILURE;
        }

        $this->warn('LAPTOP DEMO on '.config('database.connections.'.config('database.default').'.database').' ('.config('database.default').'), month '.$month->format('F Y').', bills made as '.$user->email);
        $this->line('Work orders: '.$contracts->count().', projects: '.$contracts->sum(fn ($c) => $c->projects->count()).'. Months already locked or billed are left as they are.');
        if (! $this->option('force') && $this->ask('Type DEMO to go ahead') !== 'DEMO') {
            $this->line('Stopped. Nothing was changed.');

            return self::SUCCESS;
        }

        $needsApproval = Approvals::applies($month);
        $rows = [];
        $agencies = collect();
        foreach ($contracts as $contract) {
            foreach ($contract->projects as $project) {
                $label = [$contract->contract_no, $project->code];
                try {
                    $existing = AttendanceSheet::query()->where('project_id', $project->id)->where('month', $month->toDateString())->first();
                    if ($existing?->invoice()->exists() || $existing?->isLocked()) {
                        $rows[] = [...$label, $existing->entries()->count(), '—', '—', 'Skipped: '.$month->format('F').' already locked or billed (kept as it is)'];

                        continue;
                    }
                    $staff = Employee::query()->whereIn('post_id', $project->posts()->pluck('id'))->whereIn('status', ['active', 'notice'])->count();
                    if ($staff === 0 && ! $existing) {
                        $rows[] = [...$label, 0, '—', '—', 'Skipped: no active staff on this project'];

                        continue;
                    }
                    $sheet = $attendance->open($project, $month);
                    $full = AttendanceCodes::defaultMonth($month, $sheet->holidayDays(), $project->saturday_off ?? true);
                    $attendance->save($sheet, $sheet->entries->mapWithKeys(fn ($e) => [$e->id => $full])->all(), $user);   // every day present (joining / last day still respected)
                    $attendance->lock($sheet->fresh(), $user, null);
                    if ($needsApproval) {
                        $approvals->approveForDemo('attendance', $sheet->fresh(), $user);
                    }
                    $bill = $bills->generate($sheet->fresh(), $user);
                    if ($needsApproval) {
                        $approvals->approveForDemo('sub_bill', $bill->fresh(), $user);
                    }
                    $agencies[$contract->agency_id] = $contract->agency;
                    $rows[] = [...$label, $sheet->entries()->count(), $bill->invoice_no, inr($bill->rounded_total), 'Sub-bill made'];
                } catch (Throwable $e) {
                    $rows[] = [...$label, '—', '—', '—', 'Not done: '.$e->getMessage()];
                }
            }

            try {
                $pending = $taxInvoices->pending($contract, $month);
                if ($pending->isNotEmpty()) {
                    $tax = $taxInvoices->create($contract, $month, $pending->pluck('id')->all(), $user);
                    if ($needsApproval) {
                        $approvals->approveForDemo('tax_invoice', $tax->fresh(), $user);
                    }
                    $rows[] = [$contract->contract_no, 'ALL', $pending->count().' sub-bills', $tax->number, inr($tax->rounded_total), 'Tax invoice made'];
                }
            } catch (Throwable $e) {
                $rows[] = [$contract->contract_no, 'ALL', '—', '—', '—', 'Tax invoice not made: '.$e->getMessage()];
            }
        }
        if ($needsApproval) {
            foreach ($agencies as $agency) {   // salary payout sheet and bank letter open too
                $approvals->approveForDemo('payout', ['agency' => $agency, 'month' => $month->toDateString()], $user);
            }
        }

        $this->table(['Work order', 'Project', 'Staff', 'Bill no.', 'Amount', 'Result'], $rows);
        Activity::log('created', null, [], 'Laptop demo: full attendance and bills for '.$month->format('F Y'), $user, section: 'Demo', label: 'demo:full-month');
        $this->info('Done. Sign in on the laptop and open Sub-bills / Tax Invoices for '.$month->format('F Y').'.');

        return self::SUCCESS;
    }
}
