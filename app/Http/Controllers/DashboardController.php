<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSheet;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\Project;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $sanctioned = (int) Post::query()->sum('sanctioned_qty');
        $filled = Employee::query()->whereNotNull('post_id')->whereIn('status', ['active', 'notice'])->count();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();

        $active = Employee::query()->whereIn('status', ['active', 'notice']);
        $bankProblems = (clone $active)->where(fn ($q) => $q->whereNull('ifsc')->orWhereNull('account_last4'))->count();

        $lockedProjects = AttendanceSheet::query()->where('month', $lastMonth->toDateString())->where('status', 'locked')->pluck('project_id');
        $billedSheets = Invoice::query()->where('month', $lastMonth->toDateString())->pluck('project_id');

        return view('dashboard', [
            'stats' => [
                'clients' => Client::query()->count(),
                'contracts' => GemContract::query()->where('status', 'active')->count(),
                'employees' => (clone $active)->count(),
                'sanctioned' => $sanctioned,
                'vacant' => max(0, $sanctioned - $filled),
                'billedThisYear' => (string) Invoice::query()->whereYear('month', now()->year)->sum('rounded_total'),
                'unpaid' => (string) Invoice::query()->whereIn('status', ['approved', 'submitted'])->sum('rounded_total'),
            ],
            'lastMonth' => $lastMonth,
            'projectsTotal' => Project::query()->count(),
            'projectsLocked' => $lockedProjects->count(),
            'projectsBilled' => $billedSheets->count(),
            'bankProblems' => $bankProblems,
            'drafts' => Invoice::query()->where('status', 'draft')->count(),
            'recent' => Invoice::query()->with('project')->latest()->limit(6)->get(),
            'monthly' => Invoice::query()->whereYear('month', now()->year)->get(['month', 'rounded_total', 'status'])
                ->groupBy(fn ($i) => (int) $i->month->format('n'))
                ->map(fn ($g) => ['billed' => (float) $g->sum('rounded_total'), 'paid' => (float) $g->where('status', 'paid')->sum('rounded_total')]),
        ]);
    }
}
