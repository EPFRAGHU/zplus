<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AttendanceEntry;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\GeneratedDocument;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\ProjectIncharge;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Support\Agency\AgencyContext;

/**
 * Super Admin deletes (owner, 17 Nov 2026): a record may be deleted only when nothing depends on it, so real bills,
 * attendance and GST records can never disappear by accident. Each method returns why it cannot be deleted, or null.
 */
final class SafeDelete
{
    public static function agency(Agency $a): ?string
    {
        return AgencyContext::unscoped(fn () => self::first([
            [Client::query()->where('agency_id', $a->id)->count(), 'client'],
            [GemContract::query()->where('agency_id', $a->id)->count(), 'work order'],
            [Employee::query()->where('agency_id', $a->id)->count(), 'employee'],
            [ProjectIncharge::query()->where('agency_id', $a->id)->count(), 'Project In-charge'],
            [Invoice::query()->where('agency_id', $a->id)->count(), 'sub-bill'],
            [TaxInvoice::query()->where('agency_id', $a->id)->count(), 'tax invoice'],
            [User::query()->where('agency_id', $a->id)->count(), 'login'],
        ]));
    }

    public static function client(Client $c): ?string
    {
        return AgencyContext::unscoped(fn () => self::first([
            [GemContract::query()->where('client_id', $c->id)->count(), 'work order'],
            [Employee::query()->where('client_id', $c->id)->count(), 'employee'],
            [TaxInvoice::query()->where('client_id', $c->id)->count(), 'tax invoice'],
        ]));
    }

    public static function contract(GemContract $k): ?string
    {
        return AgencyContext::unscoped(fn () => self::first([
            [$k->projects()->count(), 'project'],
            [Invoice::query()->where('gem_contract_id', $k->id)->count(), 'sub-bill'],
            [TaxInvoice::query()->where('gem_contract_id', $k->id)->count(), 'tax invoice'],
        ]));
    }

    public static function incharge(ProjectIncharge $i): ?string
    {
        $n = $i->projects()->count();

        return $n ? "It is the In-charge of {$n} ".\Illuminate\Support\Str::plural('project', $n).'. Untick them under "Edit / allot projects" first.' : null;
    }

    /** Employees with attendance, bills or letters are kept (set them Inactive); only ones entered by mistake can go. */
    public static function employee(Employee $e): ?string
    {
        if (AttendanceEntry::query()->where('employee_id', $e->id)->exists() || InvoiceLine::query()->where('employee_id', $e->id)->exists()) {
            return 'This employee has attendance or bills, so the record is kept for the history. Set the status to Inactive instead.';
        }
        if (GeneratedDocument::query()->where('documentable_type', Employee::class)->where('documentable_id', $e->id)->exists()) {
            return 'An appointment letter was issued to this employee, so the record is kept. Set the status to Inactive instead.';
        }

        return null;
    }

    /** @param array<int, array{0: int, 1: string}> $checks */
    private static function first(array $checks): ?string
    {
        foreach ($checks as [$count, $what]) {
            if ($count > 0) {
                return "It still has {$count} ".\Illuminate\Support\Str::plural($what, $count).'. Delete or move '.($count === 1 ? 'it' : 'them').' first.';
            }
        }

        return null;
    }
}
