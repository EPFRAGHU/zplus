<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Approval;
use App\Models\AttendanceSheet;
use App\Models\Invoice;
use App\Models\TaxInvoice;
use App\Services\Approvals\Approvals;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/** Maker–checker: "Send for approval" (makers) and the owner's Approvals inbox with Approve / Return (owner, 17 Nov 2026). */
class ApprovalController extends Controller
{
    public function __construct(private readonly Approvals $approvals) {}

    public function index(Request $request): View
    {
        $me = $request->user();
        $base = fn () => Approval::query()->with('agency', 'submitter', 'decider', 'employee');

        return view('approvals.index', [
            'canApprove' => Approvals::canApprove($me),
            'pending' => $base()->where('status', 'pending')->oldest('submitted_at')->get()->sortBy(fn ($a) => $a->agency->name)->values(),
            // Makers: what their agency sent that a Super Admin has not decided yet (agency scope keeps it to their agency).
            'waiting' => Approvals::canApprove($me) ? collect() : $base()->where('status', 'pending')->oldest('submitted_at')->get()->sortBy(fn ($a) => $a->agency->name)->values(),
            // Returned to me, and not sent again since (for the same record).
            'returned' => $base()->where('status', 'returned')->where('submitted_by', $me->id)->latest('decided_at')->limit(30)->get()
                ->filter(fn ($a) => ! Approval::query()->where('type', $a->type)->where('id', '>', $a->id)->where(self::sameRecord($a))->exists())->values(),
            'recent' => $base()->whereIn('status', ['approved', 'returned'])->latest('decided_at')->limit(25)->get(),
        ]);
    }

    /** Query condition: approvals of the same record as $a. */
    private static function sameRecord(Approval $a): \Closure
    {
        return fn ($q) => match (true) {
            (bool) $a->attendance_sheet_id => $q->where('attendance_sheet_id', $a->attendance_sheet_id),
            (bool) $a->invoice_id => $q->where('invoice_id', $a->invoice_id),
            (bool) $a->tax_invoice_id => $q->where('tax_invoice_id', $a->tax_invoice_id),
            (bool) $a->employee_id => $q->where('employee_id', $a->employee_id),
            (bool) $a->post_id => $q->where('post_id', $a->post_id),
            default => $q->where('agency_id', $a->agency_id)->where('month', $a->month->toDateString()),
        };
    }

    public function sendAttendance(Request $request, AttendanceSheet $sheet): RedirectResponse
    {
        return $this->send('attendance', $sheet, $request);
    }

    public function sendInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('billing');

        return $this->send('sub_bill', $invoice, $request);
    }

    public function sendTaxInvoice(Request $request, TaxInvoice $taxInvoice): RedirectResponse
    {
        Gate::authorize('billing');

        return $this->send('tax_invoice', $taxInvoice, $request);
    }

    public function sendPayout(Request $request): RedirectResponse
    {
        Gate::authorize('view-bank-details');
        $request->validate(['agency' => ['required', new \App\Rules\ExistsInMyAgency(Agency::class)], 'month' => ['required', 'date_format:Y-m']]);

        return $this->send('payout', ['agency' => Agency::query()->findOrFail($request->integer('agency')),
            'month' => CarbonImmutable::createFromFormat('!Y-m', $request->input('month'))], $request);
    }

    public function approve(Request $request, Approval $approval): RedirectResponse
    {
        abort_unless(Approvals::canApprove($request->user()), 403);
        try {
            $message = $this->approvals->approve($approval, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }

    public function returnBack(Request $request, Approval $approval): RedirectResponse
    {
        abort_unless(Approvals::canApprove($request->user()), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']],
            ['reason.required' => 'Write why it is returned, so it can be corrected.', 'reason.min' => 'Write why it is returned, so it can be corrected.']);
        try {
            $message = $this->approvals->returnBack($approval, $request->user(), trim($data['reason']));
        } catch (RuntimeException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }

    private function send(string $type, $subject, Request $request): RedirectResponse
    {
        abort_if($request->user()->isPi(), 403);
        try {
            $this->approvals->send($type, $subject, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', 'Sent to the owner for approval.');
    }
}
