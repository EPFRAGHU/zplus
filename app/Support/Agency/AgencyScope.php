<?php

namespace App\Support\Agency;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

/**
 * Limits every query of an agency's records to the signed-in user's agency. Users who work for both agencies
 * (and PIs without an agency, and the console) see everything. Records of the other agency are simply "not found".
 */
final class AgencyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $agency = AgencyContext::id();
        if ($agency === null) {
            return;
        }
        $contracts = fn () => DB::table('gem_contracts')->select('id')->where('agency_id', $agency);
        $projects = fn () => DB::table('projects')->select('id')->whereIn('gem_contract_id', $contracts());

        match ($model::class) {
            \App\Models\Agency::class => $builder->where($model->qualifyColumn('id'), $agency),
            \App\Models\Client::class, \App\Models\GemContract::class, \App\Models\Employee::class,
            \App\Models\Invoice::class, \App\Models\TaxInvoice::class => $builder->where($model->qualifyColumn('agency_id'), $agency),
            \App\Models\Project::class => $builder->whereIn($model->qualifyColumn('gem_contract_id'), $contracts()),
            \App\Models\Post::class, \App\Models\AttendanceSheet::class => $builder->whereIn($model->qualifyColumn('project_id'), $projects()),
            \App\Models\EmployeeDocument::class => $builder->whereIn($model->qualifyColumn('employee_id'),
                DB::table('employees')->select('id')->where('agency_id', $agency)),
            default => null,
        };
    }
}
