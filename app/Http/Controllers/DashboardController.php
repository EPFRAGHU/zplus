<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\AttendanceSheet;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $agencyId = $request->integer('agency') ?: null;
        $clientId = $request->integer('client') ?: null;
        $contractId = $request->integer('contract') ?: null;
        $projectId = $request->integer('project') ?: null;
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();
        $month = $lastMonth->toDateString();

        // Every figure on the page is narrowed by the same agency / client / contract / project filter.
        $byContract = fn (Builder $q) => $q
            ->when($agencyId, fn ($c) => $c->where('agency_id', $agencyId))
            ->when($clientId, fn ($c) => $c->where('client_id', $clientId))
            ->when($contractId, fn ($c) => $c->whereKey($contractId));
        $byProject = fn (Builder $q) => $q->whereHas('contract', $byContract)
            ->when($projectId, fn ($p) => $p->whereKey($projectId));
        $narrowed = $contractId || $projectId;

        $contracts = fn () => GemContract::query()->tap($byContract)
            ->when($projectId, fn ($q) => $q->whereHas('projects', fn ($p) => $p->whereKey($projectId)));
        $projects = fn () => Project::query()->tap($byProject);
        $invoices = fn () => Invoice::query()->whereHas('project', $byProject);
        $employees = fn () => Employee::query()->whereIn('status', ['active', 'notice'])
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($narrowed, fn ($q) => $q->whereHas('post.project', $byProject));

        $sanctioned = (int) Post::query()->whereHas('project', $byProject)->sum('sanctioned_qty');
        $filled = $employees()->whereNotNull('post_id')->count();

        return view('dashboard', [
            'agencies' => Agency::query()->orderBy('name')->get(),
            'clients' => Client::query()->with('agency')->orderBy('name')->get(),
            'contractOptions' => GemContract::query()->orderBy('bid_no')->get(['id', 'agency_id', 'client_id', 'bid_no']),
            'projectOptions' => Project::query()->with('contract:id,agency_id,client_id')->orderBy('name')->get(['id', 'gem_contract_id', 'name', 'pi_name']),
            'agencyId' => $agencyId,
            'clientId' => $clientId,
            'contractId' => $contractId,
            'projectId' => $projectId,
            'stats' => [
                'clients' => Client::query()->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))->when($clientId, fn ($q) => $q->whereKey($clientId))
                    ->when($narrowed, fn ($q) => $q->whereHas('contracts', fn ($c) => $c->tap($byContract)->when($projectId, fn ($k) => $k->whereHas('projects', fn ($p) => $p->whereKey($projectId)))))->count(),
                'contracts' => $contracts()->where('status', 'active')->count(),
                'employees' => $employees()->count(),
                'sanctioned' => $sanctioned,
                'vacant' => max(0, $sanctioned - $filled),
                'billedThisYear' => (string) $invoices()->whereYear('month', now()->year)->sum('rounded_total'),
                'unpaid' => (string) $invoices()->whereIn('status', ['approved', 'submitted'])->sum('rounded_total'),
            ],
            'lastMonth' => $lastMonth,
            'projectsTotal' => $projects()->count(),
            'projectsLocked' => AttendanceSheet::query()->where('month', $month)->where('status', 'locked')->whereHas('project', $byProject)->count(),
            'projectsBilled' => $invoices()->where('month', $month)->count(),
            'bankProblems' => $employees()->where(fn ($q) => $q->whereNull('ifsc')->orWhereNull('account_last4'))->count(),
            'drafts' => $invoices()->where('status', 'draft')->count(),
            // Latest bills, shown in agency blocks.
            'recent' => $invoices()->with('project', 'contract.agency', 'contract.client')->latest()->limit(8)->get()
                ->sortBy(fn ($i) => $i->contract->agency->name)->values(),
            'monthly' => $invoices()->whereYear('month', now()->year)->get(['month', 'rounded_total', 'status'])
                ->groupBy(fn ($i) => (int) $i->month->format('n'))
                ->map(fn ($g) => ['billed' => (float) $g->sum('rounded_total'), 'paid' => (float) $g->where('status', 'paid')->sum('rounded_total')]),
            'compliance' => $this->compliance($contracts(), $lastMonth),
            'pipeline' => $this->pipeline($projects(), $invoices, $contracts(), $lastMonth),
            'waiting' => \App\Services\Approvals\Approvals::canApprove($request->user())
                ? \App\Models\Approval::query()->with('agency', 'submitter')->where('status', 'pending')->where('submitted_by', '<>', $request->user()->id)
                    ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))->oldest('submitted_at')->limit(4)->get()
                : collect(),
            'ending' => $contracts()->with('agency', 'client')->where('status', 'active')->whereNotNull('end_date')
                ->where('end_date', '<=', now()->addDays(30)->toDateString())->orderBy('end_date')->get(),
            'overPosts' => Post::query()->whereHas('project', $byProject)->with('project.contract')->withCount('activeEmployees')->get()
                ->filter(fn ($p) => $p->active_employees_count > $p->sanctioned_qty)->values(),
            'summary' => $this->summary($agencyId, $clientId, $month),
            'projectRows' => $this->projectRows($projects(), $month),
        ]);
    }

    /**
     * "Where the month stands" (UI redesign): how far last month has gone through the monthly flow, for the same filters
     * as the rest of the page. Read-only counts; nothing here changes any record.
     */
    private function pipeline(Builder $projects, \Closure $invoices, Builder $contracts, \Carbon\CarbonInterface $month): array
    {
        $projectIds = $projects->pluck('id');
        $sheets = AttendanceSheet::query()->whereIn('project_id', $projectIds)->where('month', $month->toDateString())->get();
        $locked = $sheets->filter->isLocked();
        $approvals = app(\App\Services\Approvals\Approvals::class);
        $approved = $locked->filter(fn ($s) => $approvals->cleared('attendance', $s))->count();
        $billedContracts = $invoices()->where('month', $month->toDateString())->distinct()->pluck('gem_contract_id');
        $taxes = \App\Models\TaxInvoice::query()->whereIn('gem_contract_id', $billedContracts)->where('month', $month->toDateString())->get(['id', 'status']);

        return [
            ['label' => 'Attendance locked', 'done' => $locked->count(), 'of' => $projectIds->count(), 'url' => route('attendance.index', ['month' => $month->format('Y-m')])],
            ['label' => 'Approved by owner', 'done' => $approved, 'of' => $projectIds->count(), 'url' => route('approvals.index')],
            ['label' => 'Sub-bills made', 'done' => $invoices()->where('month', $month->toDateString())->count(), 'of' => $projectIds->count(), 'url' => route('invoices.index')],
            ['label' => 'Tax invoices', 'done' => $taxes->count(), 'of' => $billedContracts->count(), 'url' => route('tax-invoices.index')],
            ['label' => 'Submitted to client', 'done' => $taxes->whereIn('status', ['submitted', 'paid'])->count(), 'of' => $billedContracts->count(), 'url' => route('tax-invoices.index')],
        ];
    }

    /**
     * Last month's wages and statutory papers per active work order that has attendance that month: wages must be paid
     * by the 7th and EPF / ESIC deposited by the 15th of this month (GeM contract SLA). "Done" = the paper is uploaded.
     */
    private function compliance(Builder $contracts, \Carbon\CarbonInterface $wageMonth): array
    {
        $list = $contracts->with('agency', 'client')->where('status', 'active')
            ->whereHas('projects.attendanceSheets', fn ($q) => $q->where('month', $wageMonth->toDateString()))->get();
        $papers = \App\Models\MonthlyPaper::query()->whereIn('gem_contract_id', $list->pluck('id'))->where('month', $wageMonth->toDateString())
            ->get(['gem_contract_id', 'kind'])->groupBy('gem_contract_id');
        $rows = $list->map(function ($c) use ($papers) {
            $have = ($papers[$c->id] ?? collect())->pluck('kind')->unique();
            $needs = GemContractController::statutoryNeeds($c);

            return ['contract' => $c,
                'salary_proof' => $have->contains('salary_proof'),
                'epf_challan' => $needs['epf_challan'] ? $have->contains('epf_challan') : null,
                'esi_challan' => $needs['esi_challan'] ? $have->contains('esi_challan') : null];
        })->sortBy(fn ($r) => $r['contract']->agency->name.'|'.$r['contract']->contract_no)->values();
        $due = $wageMonth->copy()->addMonthNoOverflow();

        return ['month' => $wageMonth, 'rows' => $rows,
            'wagesDue' => $due->copy()->day(7), 'statutoryDue' => $due->copy()->day(15),
            'wagesPending' => $rows->where('salary_proof', false)->count(),
            'statutoryPending' => $rows->filter(fn ($r) => $r['epf_challan'] === false || $r['esi_challan'] === false)->count()];
    }

    /** One row per project: contract, PI, staff vs sanctioned, last month's attendance and bill, billed this year. */
    private function projectRows(Builder $projects, string $month)
    {
        $rows = $projects->with(['contract.agency', 'contract.client', 'attendanceSheets' => fn ($q) => $q->where('month', $month)->with('invoice')])
            ->withSum('posts as sanctioned', 'sanctioned_qty')
            ->withCount(['employees as staff' => fn ($q) => $q->whereIn('employees.status', ['active', 'notice'])])
            ->get();
        $billed = Invoice::query()->whereIn('project_id', $rows->pluck('id'))->whereYear('month', now()->year)
            ->groupBy('project_id')->selectRaw('project_id, sum(rounded_total) as total')->pluck('total', 'project_id');

        return $rows->map(function ($p) use ($billed) {
            $sheet = $p->attendanceSheets->first();

            return [
                'project' => $p,
                'staff' => (int) $p->staff,
                'sanctioned' => (int) $p->sanctioned,
                'last' => ! $sheet ? 'not started' : ($sheet->invoice ? 'billed' : ($sheet->isLocked() ? 'locked' : ($sheet->isSubmitted() ? 'submitted' : 'draft'))),
                'billed' => (string) ($billed[$p->id] ?? 0),
            ];
        })->sortBy(fn ($r) => $r['project']->contract->agency->name.'|'.$r['project']->contract->client->name.'|'.$r['project']->contract->bid_no.'|'.$r['project']->name)->values();
    }

    /** One row per client record (each belongs to one agency). */
    private function summary(?int $agencyId, ?int $clientId, string $month)
    {
        $clients = Client::query()->with('agency')
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->when($clientId, fn ($q) => $q->whereKey($clientId))
            ->withCount([
                'contracts as contracts_count' => fn ($q) => $q->where('status', 'active'),
                'employees as employees_count' => fn ($q) => $q->whereIn('status', ['active', 'notice']),
                'employees as placed_count' => fn ($q) => $q->whereIn('status', ['active', 'notice'])->whereNotNull('post_id'),
            ])
            ->get();

        $ids = $clients->pluck('id');
        $sanctioned = Post::query()->join('projects', 'projects.id', '=', 'posts.project_id')
            ->join('gem_contracts', 'gem_contracts.id', '=', 'projects.gem_contract_id')
            ->whereIn('gem_contracts.client_id', $ids)->groupBy('gem_contracts.client_id')
            ->selectRaw('gem_contracts.client_id, sum(posts.sanctioned_qty) as total')->pluck('total', 'client_id');
        $invoiceTotals = fn ($q) => Invoice::query()->join('gem_contracts', 'gem_contracts.id', '=', 'invoices.gem_contract_id')
            ->whereIn('gem_contracts.client_id', $ids)->tap($q)->groupBy('gem_contracts.client_id')
            ->selectRaw('gem_contracts.client_id, sum(invoices.rounded_total) as total')->pluck('total', 'client_id');
        $billed = $invoiceTotals(fn ($q) => $q->whereYear('invoices.month', now()->year));
        $unpaid = $invoiceTotals(fn ($q) => $q->whereIn('invoices.status', ['approved', 'submitted']));
        $projects = Project::query()->join('gem_contracts', 'gem_contracts.id', '=', 'projects.gem_contract_id')
            ->whereIn('gem_contracts.client_id', $ids)->groupBy('gem_contracts.client_id')
            ->selectRaw('gem_contracts.client_id, count(*) as total')->pluck('total', 'client_id');
        $locked = AttendanceSheet::query()->join('projects', 'projects.id', '=', 'attendance_sheets.project_id')
            ->join('gem_contracts', 'gem_contracts.id', '=', 'projects.gem_contract_id')
            ->whereIn('gem_contracts.client_id', $ids)->where('attendance_sheets.month', $month)->where('attendance_sheets.status', 'locked')
            ->groupBy('gem_contracts.client_id')->selectRaw('gem_contracts.client_id, count(*) as total')->pluck('total', 'client_id');

        return $clients->map(fn ($c) => [
            'client' => $c,
            'contracts' => $c->contracts_count,
            'employees' => $c->employees_count,
            'placed' => $c->placed_count,
            'sanctioned' => (int) ($sanctioned[$c->id] ?? 0),
            'projects' => (int) ($projects[$c->id] ?? 0),
            'locked' => (int) ($locked[$c->id] ?? 0),
            'billed' => (string) ($billed[$c->id] ?? 0),
            'unpaid' => (string) ($unpaid[$c->id] ?? 0),
        ])->sortBy(fn ($r) => $r['client']->agency?->name.'|'.$r['client']->name)->values();
    }
}
