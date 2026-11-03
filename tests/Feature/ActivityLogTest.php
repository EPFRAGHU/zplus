<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AttendanceSheet;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $this->owner = User::factory()->create(['role' => 'admin', 'name' => 'Owner']);
        $this->owner->forceFill(['is_super_admin' => true])->save();
    }

    public function test_only_the_super_admin_sees_the_activity_log(): void
    {
        $this->actingAs($this->owner)->get('/activity')->assertOk()->assertSee('Activity log')->assertSee('entries cannot be changed or deleted');
        $this->get('/')->assertSee(route('activity.index'));

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/activity')->assertForbidden();
        $this->get('/activity/export')->assertForbidden();
        $this->get('/')->assertDontSee(route('activity.index'));
        foreach (['accounts', 'supervisor'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/activity')->assertForbidden();
        }
    }

    public function test_edits_are_logged_with_old_and_new_values_and_secrets_masked(): void
    {
        $operator = User::factory()->create(['role' => 'accounts', 'name' => 'Ravi']);
        $e = Employee::query()->firstOrFail();
        $this->actingAs($operator)->put("/employees/{$e->id}", [
            'emp_code' => $e->emp_code, 'agency_id' => $e->agency_id, 'client_id' => $e->client_id, 'post_id' => $e->post_id, 'name' => $e->name,
            'status' => 'active', 'mobile' => '9123456780', 'account_number' => '112233445566', 'ifsc' => 'SBIN0001234',
        ])->assertSessionHasNoErrors();

        $log = ActivityLog::query()->where('action', 'updated')->where('section', 'Employee')->latest('id')->firstOrFail();
        $this->assertSame('Ravi', $log->user_name);
        $this->assertSame('accounts', $log->user_role);
        $this->assertSame('9123456780', $log->changes['mobile'][1]);
        $this->assertSame('••••5566', $log->changes['account_number'][1]);
        $this->assertStringNotContainsString('112233445566', json_encode($log->changes));
        $this->assertStringContainsString($e->emp_code, $log->subject_label);

        $this->actingAs($this->owner)->get('/activity?section=Employee')->assertSee('Ravi')->assertSee('9123456780')->assertDontSee('112233445566');
    }

    public function test_adds_deletes_status_changes_attendance_and_data_upload_are_logged(): void
    {
        $this->actingAs($this->owner);
        $project = Project::query()->where('code', 'MPH')->firstOrFail();
        $this->post("/projects/{$project->id}/posts", ['designation' => 'Data Entry Operator', 'sanctioned_qty' => 1, 'rate' => '20000']);
        $post = $project->posts()->where('designation', 'Data Entry Operator')->firstOrFail();
        $this->delete("/posts/{$post->id}");
        $this->assertTrue(ActivityLog::query()->where('action', 'created')->where('section', 'Post')->exists());
        $this->assertSame('Data Entry Operator', ActivityLog::query()->where('action', 'deleted')->where('section', 'Post')->firstOrFail()->changes['designation']);

        $sheet = app(AttendanceService::class)->open($project, CarbonImmutable::parse('2026-10-01'))->load('entries');
        $codes = $sheet->entries->mapWithKeys(fn ($en) => [$en->id => array_replace($en->codes, [1 => 'CL'])])->all();
        $this->put("/attendance/{$sheet->id}", ['codes' => $codes]);
        $att = ActivityLog::query()->where('section', 'Attendance')->where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertStringContainsString('attendance codes changed', $att->note);
        $this->assertSame(['P', 'CL'], array_values($att->changes)[0]);

        app(AttendanceService::class)->lock($sheet, $this->owner, null);
        $this->assertTrue(ActivityLog::query()->where('section', 'Attendance')->where('action', 'status')->exists());
        $invoice = app(InvoiceGenerator::class)->generate($sheet->fresh(), $this->owner);
        $this->post("/invoices/{$invoice->id}/status", ['status' => 'approved']);
        $status = ActivityLog::query()->where('section', 'Sub-bill')->where('action', 'status')->latest('id')->firstOrFail();
        $this->assertSame(['draft', 'approved'], $status->changes['status']);
    }

    public function test_views_downloads_and_sign_ins_are_logged(): void
    {
        $e = Employee::query()->firstOrFail();
        $this->actingAs($this->owner)->get("/employees/{$e->id}")->assertOk();
        $this->assertSame('Employee details (bank details visible)', ActivityLog::query()->where('action', 'viewed')->latest('id')->value('note'));

        $sheet = app(AttendanceService::class)->open(Project::query()->where('code', 'MPH')->firstOrFail(), CarbonImmutable::parse('2026-10-01'));
        app(AttendanceService::class)->lock($sheet, $this->owner, null);
        $this->get("/attendance/{$sheet->id}/salary-payouts")->assertOk();
        $this->get('/data-upload/employees/template?data=1')->assertOk();
        $notes = ActivityLog::query()->where('action', 'downloaded')->pluck('note')->all();
        $this->assertContains('Salary payout sheet (bank details)', $notes);
        $this->assertContains('Data export with current records: employees', $notes);
        $this->get('/employees/999999')->assertNotFound();
        $this->assertSame(1, ActivityLog::query()->where('action', 'viewed')->count());   // failed requests are not logged

        $this->post('/logout');
        $user = User::factory()->create(['role' => 'supervisor', 'password' => 'Secret123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123']);
        $this->assertSame(['sign_in_failed', 'signed_in'], ActivityLog::query()->where('user_id', $user->id)->orderBy('id')->pluck('action')->all());
    }

    public function test_log_entries_cannot_be_changed_or_deleted_and_export_works(): void
    {
        $this->actingAs($this->owner)->get('/agencies');
        \App\Support\Activity\Activity::log('viewed', null, [], 'test entry');
        $log = ActivityLog::query()->latest('id')->firstOrFail();
        try {
            $log->update(['note' => 'tampered']);
            $this->fail('update should be refused');
        } catch (LogicException) {
        }
        try {
            $log->delete();
            $this->fail('delete should be refused');
        } catch (LogicException) {
        }
        $this->assertSame('test entry', $log->fresh()->note);

        $response = $this->get('/activity/export')->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
        $this->assertTrue(ActivityLog::query()->where('note', 'like', 'Activity log export%')->exists());
    }

    public function test_other_admins_cannot_edit_the_super_admin_and_the_role_can_be_moved(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'second@example.com']);
        $this->actingAs($admin)->get("/users/{$this->owner->id}/edit")->assertForbidden();
        $this->put("/users/{$this->owner->id}", ['name' => 'x', 'email' => $this->owner->email, 'role' => 'admin', 'active' => '0'])->assertForbidden();
        $this->get('/users')->assertSee('Super Admin');

        $this->artisan('users:super-admin', ['email' => 'second@example.com'])->assertSuccessful();
        $this->assertTrue($admin->fresh()->is_super_admin);
        $this->assertFalse($this->owner->fresh()->is_super_admin);
        $this->artisan('users:super-admin', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
