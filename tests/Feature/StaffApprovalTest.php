<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Approval;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner approval, release 2 (v2.15.0): new staff and changes of post, rate, bank details and EPF/ESIC/TDS wait for the owner. */
class StaffApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $owner2;

    private User $accounts;

    private array $r;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'admin', 'name' => 'Owner One']);
        $this->owner->forceFill(['is_super_admin' => true])->save();
        $this->owner2 = User::factory()->create(['role' => 'admin', 'name' => 'Owner Two']);
        $this->owner2->forceFill(['is_super_admin' => true])->save();
        $this->accounts = User::factory()->create(['role' => 'accounts', 'name' => 'Accounts']);
        $this->actingAs($this->owner);
        $agency = Agency::query()->create(['code' => 'SWH', 'name' => 'SWACHHA', 'number_prefix' => 'SW']);
        $client = Client::query()->create(['agency_id' => $agency->id, 'name' => 'ICMR', 'short_code' => 'ICMR']);
        $contract = GemContract::query()->create(['agency_id' => $agency->id, 'client_id' => $client->id, 'bid_no' => 'GEM/1', 'contract_no' => 'GEMC-1',
            'epf_percent' => '13', 'esic_percent' => '3.25', 'service_charge_percent' => '3.85', 'gst_percent' => '18']);
        $project = $contract->projects()->create(['name' => 'Lab', 'code' => 'LAB']);
        $post = $project->posts()->create(['designation' => 'Analyst', 'sanctioned_qty' => 2, 'rate' => 30000]);
        $post2 = $project->posts()->create(['designation' => 'Assistant', 'sanctioned_qty' => 2, 'rate' => 20000]);
        $existing = Employee::query()->create(['emp_code' => 'SW-1001', 'name' => 'Old Staff', 'agency_id' => $agency->id, 'client_id' => $client->id,
            'post_id' => $post->id, 'status' => 'active', 'bank_name' => 'SBI', 'account_number' => '11112222333', 'ifsc' => 'SBIN0000001']);
        $this->r = compact('agency', 'client', 'contract', 'project', 'post', 'post2', 'existing');
    }

    private function form(array $extra = []): array
    {
        return array_merge(['agency_id' => $this->r['agency']->id, 'client_id' => $this->r['client']->id, 'post_id' => $this->r['post']->id,
            'name' => 'New Staff', 'status' => 'active', 'tds_applicable' => 1], $extra);
    }

    public function test_new_employee_stays_inactive_until_the_owner_approves(): void
    {
        $this->actingAs($this->accounts)->post(route('employees.store'), $this->form())->assertSessionHas('status', fn ($s) => str_contains($s, 'sent to the owner'));
        $new = Employee::query()->where('name', 'New Staff')->sole();
        $this->assertSame('inactive', $new->status);
        $sheet = app(AttendanceService::class)->open($this->r['project'], CarbonImmutable::parse('2026-11-01'));
        $this->assertFalse($sheet->entries()->where('employee_id', $new->id)->exists());   // not on attendance

        $approval = Approval::query()->where('type', 'new_employee')->sole();
        $this->actingAs($this->owner)->get(route('approvals.index'))->assertSee($new->emp_code)->assertSee('New Staff')->assertSee('Analyst')->assertSee('<span class="badge text-bg-primary">LAB</span>', false);
        $this->actingAs($this->owner)->post(route('approvals.approve', $approval))->assertSessionHasNoErrors();
        $this->assertSame('active', $new->fresh()->status);
    }

    public function test_returned_new_employee_is_corrected_and_sent_again(): void
    {
        $this->actingAs($this->accounts)->post(route('employees.store'), $this->form());
        $new = Employee::query()->where('name', 'New Staff')->sole();
        $this->actingAs($this->owner)->post(route('approvals.return', Approval::query()->sole()), ['reason' => 'Wrong post, should be Assistant']);
        $this->actingAs($this->accounts)->get(route('employees.show', $new))->assertSee('Wrong post, should be Assistant')->assertSee('Send again for approval');
        $this->actingAs($this->accounts)->put(route('employees.update', $new), $this->form(['post_id' => $this->r['post2']->id, 'emp_code' => $new->emp_code]))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $new->fresh()->status);   // still not approved, whatever the form said
        $this->actingAs($this->accounts)->post(route('employees.send-for-approval', $new))->assertSessionHas('status');
        $this->actingAs($this->owner)->post(route('approvals.approve', Approval::query()->latest('id')->first()))->assertSessionHasNoErrors();
        $this->assertSame(['active', $this->r['post2']->id], [$new->fresh()->status, $new->fresh()->post_id]);
    }

    public function test_bank_and_switch_changes_are_held_until_approved_other_details_save_at_once(): void
    {
        $e = $this->r['existing'];
        $this->actingAs($this->accounts)->put(route('employees.update', $e), $this->form(['name' => 'Old Staff Renamed', 'emp_code' => 'SW-1001',
            'account_number' => '99998888777', 'ifsc' => 'HDFC0000001', 'bank_name' => 'HDFC', 'pf_applicable' => 1]))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Account no.') && str_contains($s, 'EPF'));
        $e->refresh();
        $this->assertSame('Old Staff Renamed', $e->name);                                  // saved at once
        $this->assertSame(['11112222333', 'SBIN0000001', false], [$e->account_number, $e->ifsc, $e->pf_applicable]);   // old details stay in use

        $approval = Approval::query()->where('type', 'employee_change')->sole();
        $this->assertStringNotContainsString('99998888777', (string) \DB::table('approvals')->where('id', $approval->id)->value('changes'));   // stored encrypted
        // A second change of watched details while one is waiting is refused.
        $this->actingAs($this->accounts)->patch(route('employees.statutory', $e), ['esic_applicable' => 1])->assertSessionHasErrors('approval');

        $this->actingAs($this->owner)->get(route('employees.show', $e))->assertSee('99998888777')->assertSee('HDFC0000001');
        $this->actingAs($this->owner)->post(route('approvals.approve', $approval))->assertSessionHasNoErrors();
        $e->refresh();
        $this->assertSame(['99998888777', 'HDFC0000001', true, '8777'], [$e->account_number, $e->ifsc, $e->pf_applicable, $e->account_last4]);
    }

    public function test_switch_on_the_employees_list_is_held_and_four_eyes_applies(): void
    {
        $e = $this->r['existing'];
        $this->actingAs($this->owner)->patch(route('employees.statutory', $e), ['pf_applicable' => 1, 'tds_applicable' => 1])->assertSessionHas('status', fn ($s) => str_contains($s, 'EPF'));
        $this->assertFalse($e->fresh()->pf_applicable);
        $approval = Approval::query()->sole();
        $this->actingAs($this->owner)->post(route('approvals.approve', $approval))->assertSessionHasErrors('approval');   // he made it himself
        $this->actingAs($this->owner2)->post(route('approvals.approve', $approval))->assertSessionHasNoErrors();
        $this->assertTrue($e->fresh()->pf_applicable);
    }

    public function test_post_rate_change_is_held_and_returned_change_is_never_applied(): void
    {
        $post = $this->r['post'];
        $this->actingAs($this->accounts)->put(route('posts.update', $post), ['designation' => 'Data Analyst', 'sanctioned_qty' => 3, 'rate' => 31000])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'owner'));
        $post->refresh();
        $this->assertSame(['Data Analyst', 3, '30000.00'], [$post->designation, (int) $post->sanctioned_qty, (string) $post->rate]);
        $this->actingAs($this->owner)->post(route('approvals.return', Approval::query()->sole()), ['reason' => 'Rate stays 30,000 till the new contract']);
        $this->assertSame('30000.00', (string) $post->fresh()->rate);
        $this->actingAs($this->accounts)->get(route('approvals.index'))->assertSee('Rate stays 30,000 till the new contract');

        $this->actingAs($this->accounts)->put(route('posts.update', $post), ['designation' => 'Data Analyst', 'sanctioned_qty' => 3, 'rate' => 31000]);
        $this->actingAs($this->owner)->post(route('approvals.approve', Approval::query()->latest('id')->first()))->assertSessionHasNoErrors();
        $this->assertSame('31000.00', (string) $post->fresh()->rate);
    }

    public function test_change_made_another_way_meanwhile_is_not_overwritten(): void
    {
        $e = $this->r['existing'];
        $this->actingAs($this->accounts)->patch(route('employees.statutory', $e), ['pf_applicable' => 1, 'tds_applicable' => 1]);
        $e->forceFill(['pf_applicable' => true])->save();   // changed some other way after sending
        $this->actingAs($this->owner)->post(route('approvals.approve', Approval::query()->sole()))->assertSessionHasErrors('approval');
        $this->assertSame('pending', Approval::query()->sole()->status);
    }

    public function test_staff_added_without_switches_can_be_approved(): void
    {
        // Owner, 23 Nov 2026: a new employee saved without EPF / ESIC (e.g. GeM import) was refused as "changed after sending".
        $staff = app(\App\Services\Approvals\StaffChanges::class);
        $e = $staff->create(['emp_code' => 'SW-2001', 'name' => 'Imported', 'agency_id' => $this->r['agency']->id, 'status' => 'active'], $this->accounts);
        $this->actingAs($this->owner)->post(route('approvals.approve', Approval::query()->where('employee_id', $e->id)->sole()))->assertSessionHasNoErrors();
        $this->assertSame('active', $e->fresh()->status);
    }

    public function test_request_sent_with_blank_switches_before_the_fix_still_approves_but_a_real_change_does_not(): void
    {
        $make = function (string $code) {
            $e = Employee::query()->create(['emp_code' => $code, 'name' => 'Legacy '.$code, 'agency_id' => $this->r['agency']->id, 'status' => 'inactive']);
            $snapshot = ['name' => $e->name, 'emp_code' => $code];
            foreach (\App\Services\Approvals\StaffChanges::WATCHED as $f) {
                $snapshot[$f] = null;   // what the old code stored for switches the form left out
            }

            return [$e, Approval::query()->create(['agency_id' => $e->agency_id, 'type' => 'new_employee', 'employee_id' => $e->id, 'month' => '2026-11-01',
                'label' => 'New employee '.$code, 'fingerprint' => hash('sha256', json_encode($snapshot)), 'changes' => $snapshot,
                'status' => 'pending', 'submitted_by' => $this->accounts->id, 'submitted_at' => now()])];
        };
        [$ok, $okApproval] = $make('SW-3001');
        $ok->forceFill(['tds_applicable' => true])->save();   // database default before 22 Nov
        $this->actingAs($this->owner)->post(route('approvals.approve', $okApproval))->assertSessionHasNoErrors();
        $this->assertSame('active', $ok->fresh()->status);

        [$changed, $changedApproval] = $make('SW-3002');
        $changed->update(['pf_applicable' => true]);            // really changed after sending
        $this->post(route('approvals.approve', $changedApproval))->assertSessionHasErrors();
        $this->assertSame('inactive', $changed->fresh()->status);
    }

    public function test_correcting_a_waiting_new_employee_updates_the_request_so_it_can_be_approved(): void
    {
        // Owner, 23 Nov 2026: the maker switched EPF on while the new employee was waiting; approving then failed.
        $this->actingAs($this->accounts)->post('/employees', $this->form())->assertSessionHasNoErrors();
        $e = Employee::query()->where('name', 'New Staff')->firstOrFail();
        $this->patch("/employees/{$e->id}/statutory", ['pf_applicable' => '1'])->assertRedirect();
        $this->assertTrue($e->fresh()->pf_applicable);
        $open = Approval::query()->where('employee_id', $e->id)->where('status', 'pending')->sole();
        $this->assertSame('1', $open->changes['pf_applicable']);   // the owner sees the corrected details

        $this->actingAs($this->owner)->post(route('approvals.approve', $open))->assertSessionHasNoErrors();
        $this->assertSame('active', $e->fresh()->status);
    }

    public function test_a_request_already_out_of_date_shows_what_changed_and_can_be_sent_again(): void
    {
        $this->actingAs($this->accounts)->post('/employees', $this->form())->assertSessionHasNoErrors();
        $e = Employee::query()->where('name', 'New Staff')->firstOrFail();
        $e->forceFill(['esic_applicable' => true])->save();   // changed the old way, without updating the request
        $old = Approval::query()->where('employee_id', $e->id)->where('status', 'pending')->sole();

        $this->actingAs($this->owner)->post(route('approvals.approve', $old))->assertSessionHasErrors();
        $this->get(route('approvals.index'))->assertSee('Changed after sending: <b>ESIC</b>', false);
        $this->actingAs($this->accounts)->get(route('employees.show', $e))->assertSee('Details changed after sending: <b>ESIC</b>', false)->assertSee('Send again for approval');
        $this->post(route('employees.send-for-approval', $e))->assertSessionHasNoErrors();
        $this->assertSame('superseded', $old->fresh()->status);

        $new = Approval::query()->where('employee_id', $e->id)->where('status', 'pending')->sole();
        $this->actingAs($this->owner)->post(route('approvals.approve', $new))->assertSessionHasNoErrors();
        $this->assertSame('active', $e->fresh()->status);
    }

    public function test_new_staff_show_in_columns_with_approve_return_and_delete(): void
    {
        $this->actingAs($this->accounts)->post('/employees', $this->form(['bank_name' => 'STATE BANK OF INDIA', 'account_holder' => 'New Staff',
            'account_number' => '22007837186', 'ifsc' => 'SBIN0001234', 'pf_applicable' => '1']))->assertSessionHasNoErrors();
        $e = Employee::query()->where('name', 'New Staff')->firstOrFail();
        $html = $this->actingAs($this->owner)->get(route('approvals.index'))->assertOk()->getContent();
        foreach (['Name as per bank</th><th>A/c no.</th><th>IFSC</th>', 'STATE BANK OF INDIA', '22007837186', 'SBIN0001234',
            'Approve', 'Return to the maker', route('employees.destroy', $e)] as $t) {
            $this->assertStringContainsString($t, $html);
        }
        $this->assertSame(1, substr_count($html, 'text-bg-success">On</span>'));   // EPF on; ESIC off; TDS off by default for new staff

        // Delete an employee entered by mistake from here: the request is closed, not left behind.
        $this->delete(route('employees.destroy', $e), ['back' => 'approvals'])->assertRedirect(route('approvals.index'));
        $this->assertNull(Employee::query()->find($e->id));
        $this->assertSame('withdrawn', Approval::query()->where('type', 'new_employee')->latest('id')->first()->status);
        $this->get(route('approvals.index'))->assertSee('Nothing is waiting for approval.');
    }

    public function test_delete_button_on_the_employee_edit_page(): void
    {
        $this->actingAs($this->accounts)->post('/employees', $this->form())->assertSessionHasNoErrors();
        $e = Employee::query()->where('name', 'New Staff')->firstOrFail();
        $this->get(route('employees.edit', $e))->assertSee('form="delete-employee"', false)->assertSee('id="delete-employee"', false);
        $this->get(route('employees.edit', $this->r['existing']))->assertSee('form="delete-employee"', false);
        $this->delete(route('employees.destroy', $e))->assertRedirect(route('employees.index'));
        $this->assertNull(Employee::query()->find($e->id));
        $this->assertSame('withdrawn', Approval::query()->where('type', 'new_employee')->latest('id')->first()->status);
    }
}
