<?php

namespace App\Http\Middleware;

use App\Support\Activity\Activity;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Logs who opened sensitive pages and who downloaded documents (only when the request succeeded). */
class LogAccess
{
    /** route name => [action, what] */
    private const ROUTES = [
        'employees.show' => ['viewed', 'Employee details'],
        'attendance.payouts' => ['downloaded', 'Salary payout sheet (bank details)'],
        'attendance.certificate' => ['downloaded', 'Attendance certificate'],
        'attendance.scan' => ['downloaded', 'Signed attendance certificate'],
        'attendance.submitted-pdf' => ['downloaded', 'Attendance PDF as submitted by PI'],
        'invoices.pdf' => ['downloaded', 'Sub-bill PDF'],
        'invoices.covering-letter' => ['downloaded', 'Covering letter'],
        'tax-invoices.pdf' => ['downloaded', 'Tax invoice PDF'],
        'employees.appointment-letter' => ['downloaded', 'Appointment letter'],
        'documents.show' => ['downloaded', 'Employee document'],
        'master-import.template' => ['downloaded', 'Excel template'],
        'pi.pdf' => ['downloaded', 'Attendance PDF (PI)'],
        'pi.signed.show' => ['downloaded', 'Signed attendance copy (PI)'],
        'pi.bills.pdf' => ['downloaded', 'Sub-bill PDF (PI)'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $name = $request->route()?->getName();
        if ($name && isset(self::ROUTES[$name]) && $request->user() && $response->getStatusCode() < 400) {
            [$action, $what] = self::ROUTES[$name];
            $subject = collect($request->route()->parameters())->first(fn ($p) => $p instanceof Model);
            if ($name === 'master-import.template') {
                $what = ($request->boolean('data') ? 'Data export with current records' : 'Blank template').': '.$request->route('type');
            }
            if ($name === 'employees.show' && $request->user()->can('view-bank-details')) {
                $what .= ' (bank details visible)';
            }
            Activity::log($action, $subject, [], $what);
        }

        return $response;
    }
}
