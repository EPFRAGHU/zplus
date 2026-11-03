<?php

namespace App\Http\Middleware;

use App\Support\Agency\AgencyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For a single-agency login, refuses any record number in a form that belongs to the other agency
 * (forms only offer the user's own records; this stops a tampered request).
 */
class GuardAgencyInput
{
    private const FIELDS = [
        'agency_id' => \App\Models\Agency::class, 'client_id' => \App\Models\Client::class, 'gem_contract_id' => \App\Models\GemContract::class,
        'project_id' => \App\Models\Project::class, 'post_id' => \App\Models\Post::class, 'attendance_sheet_id' => \App\Models\AttendanceSheet::class,
        'employee_id' => \App\Models\Employee::class, 'invoice_id' => \App\Models\Invoice::class, 'invoice_ids' => \App\Models\Invoice::class,
        'sub_bills' => \App\Models\Invoice::class, 'projects' => \App\Models\Project::class,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (AgencyContext::id() && ! $request->isMethod('GET') && blank($request->input('agency_id'))) {
            $request->merge(['agency_id' => AgencyContext::id()]);   // an agency login always works for its own agency, sent or not
        }
        if (AgencyContext::id() && ! $request->isMethod('GET')) {
            foreach (self::FIELDS as $field => $model) {
                $ids = collect((array) $request->input($field))->flatten()->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v)->unique();
                if ($ids->isNotEmpty() && $model::query()->whereKey($ids->all())->count() !== $ids->count()) {
                    abort(404);
                }
            }
        }

        return $next($request);
    }
}
