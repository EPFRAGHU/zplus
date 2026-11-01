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
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();

        // Every figure on the page is narrowed by the same agency / client filter.
        $byContract = fn (Builder $q) => $q
            ->when($agencyId, fn ($c) => $c->where('agency_id', $agencyId))
            ->when($clientId, fn ($c) => $c->where('client_id', $clientId));
        $contracts = fn () => GemContract::query()->tap($byContract);
        $projects = fn () => Project::query()->whereHas('contract', $byContract);
        $invoices = fn () => Invoice::query()->whereHas('contract', $byContract);
        $employees = fn () => Employee::query()->whereIn('status', ['active', 'notice'])
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId));

        $sanctioned = (int) Post::query()->whereHas('project.contract', $byContract)->sum('sanctioned_qty');
        $filled = $employees()->whereNotNull('post_id')->count();
        $month = $lastMonth->toDateString();

        return view('dashboard', [
            'agencies' => Agency::query()->orderBy('name')->get(),
            'clients' => Client::query()->with('agency')->orderBy('name')->get(),
            'agencyId' => $agencyId,
            'clientId' => $clientId,
            'stats' => [
                'clients' => Client::query()->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))->when($clientId, fn ($q) => $q->whereKey($clientId))->count(),
                'contracts' => $contracts()->where('status', 'active')->count(),
                'employees' => $employees()->count(),
                'sanctioned' => $sanctioned,
                'vacant' => max(0, $sanctioned - $filled),
                'billedThisYear' => (string) $invoices()->whereYear('month', now()->year)->sum('rounded_total'),
                'unpaid' => (string) $invoices()->whereIn('status', ['approved', 'submitted'])->sum('rounded_total'),
            ],
            'lastMonth' => $lastMonth,
            'projectsTotal' => $projects()->count(),
            'projectsLocked' => AttendanceSheet::query()->where('month', $month)->where('status', 'locked')->whereHas('project.contract', $byContract)->count(),
            'projectsBilled' => $invoices()->where('month', $month)->count(),
            'bankProblems' => $employees()->where(fn ($q) => $q->whereNull('ifsc')->orWhereNull('account_last4'))->count(),
            'drafts' => $invoices()->where('status', 'draft')->count(),
            'recent' => $invoices()->with('project', 'contract.agency', 'contract.client')->latest()->limit(6)->get(),
            'monthly' => $invoices()->whereYear('month', now()->year)->get(['month', 'rounded_total', 'status'])
                ->groupBy(fn ($i) => (int) $i->month->format('n'))
                ->map(fn ($g) => ['billed' => (float) $g->sum('rounded_total'), 'paid' => (float) $g->where('status', 'paid')->sum('rounded_total')]),
            'summary' => $this->summary($agencyId, $clientId, $month),
        ]);
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
