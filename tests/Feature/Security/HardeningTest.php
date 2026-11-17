<?php

namespace Tests\Feature\Security;

use App\Models\Agency;
use App\Models\Client;
use App\Models\GemContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Security audit Phase 2 (v2.15.1): each test proves one hole from SECURITY_AUDIT.md is closed. */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private array $r;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'admin']);
        $this->owner->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($this->owner);
        $agency = Agency::query()->create(['code' => 'SWH', 'name' => 'SWACHHA']);
        $client = Client::query()->create(['agency_id' => $agency->id, 'name' => 'ICMR', 'short_code' => 'ICMR']);
        $contract = GemContract::query()->create(['agency_id' => $agency->id, 'client_id' => $client->id, 'bid_no' => 'GEM/1', 'contract_no' => 'GEMC-1']);
        $this->r = compact('agency', 'client', 'contract');
    }

    /** H2: a project name can no longer break out of the "Delete project" pop-up into JavaScript. */
    public function test_project_name_cannot_inject_script_into_delete_popups(): void
    {
        $evil = "X'); fetch('/approvals'); ('";
        $project = $this->r['contract']->projects()->create(['name' => $evil, 'code' => 'EVIL']);
        foreach ([route('contracts.show', $this->r['contract']), route('attendance.index')] as $url) {
            $html = $this->withSession(['delete_mode' => true])->get($url)->assertOk()->getContent();
            $this->assertStringContainsString(route('projects.destroy', $project), $html, $url);
            $this->assertStringNotContainsString("confirm('Delete project", $html, $url);
            $this->assertStringNotContainsString("fetch('/approvals')", $html, $url);   // only ever escaped
            $this->assertStringContainsString('data-confirm="Delete project X&#039;); fetch(&#039;/approvals&#039;)', $html, $url);
        }
    }

    /** M2: text in the Activity log export can never become a live Excel formula. */
    public function test_activity_export_writes_formula_like_text_as_plain_text(): void
    {
        $payload = '=HYPERLINK("https://example.invalid/steal","Click")';
        \App\Support\Activity\Activity::log('updated', null, [], $payload, section: '+SUM(1,1)', label: '@cmd');
        $response = $this->get(route('activity.export'))->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'act');
        file_put_contents($file, $response->streamedContent());
        $ws = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
        $found = 0;
        foreach ($ws->getRowIterator(2) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                if (in_array((string) $cell->getValue(), [$payload, '+SUM(1,1)', '@cmd'], true)) {
                    $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $cell->getDataType());
                    $found++;
                }
            }
        }
        $this->assertSame(3, $found);
        unlink($file);
    }

    /** M5: PAN is shown in full only to those who may see bank details, and never in full in the Activity log. */
    public function test_pan_is_masked_for_operators_and_in_the_activity_log(): void
    {
        config(['approvals.staff' => false]);
        $employee = \App\Models\Employee::query()->create(['emp_code' => 'SW-1001', 'name' => 'Staff', 'agency_id' => $this->r['agency']->id,
            'client_id' => $this->r['client']->id, 'status' => 'active', 'pan' => 'ABCDE1234F']);
        $this->get(route('employees.show', $employee))->assertSee('ABCDE1234F');                       // owner / Admin / Accounts
        $operator = User::factory()->create(['role' => 'supervisor']);
        $this->actingAs($operator)->get(route('employees.show', $employee))->assertDontSee('ABCDE1234F')->assertSee('234F');
        $this->actingAs($operator)->get(route('staff.index', ['employee' => $employee->id]))->assertDontSee('ABCDE1234F');

        $this->actingAs($this->owner);
        $employee->update(['pan' => 'PQRST9876Z']);
        $logged = \App\Models\ActivityLog::query()->where('subject_id', $employee->id)->get()->toJson();
        $this->assertStringNotContainsString('ABCDE1234F', $logged);
        $this->assertStringNotContainsString('PQRST9876Z', $logged);
        $this->assertStringContainsString('876Z', $logged);
    }

    /** M1: every page sends a Content-Security-Policy (only this site's scripts, no framing by others, forms post only here). */
    public function test_content_security_policy_is_sent(): void
    {
        foreach ([route('dashboard'), route('login')] as $url) {
            $csp = (string) ($url === route('login') ? $this->post(route('logout'))->baseResponse : $this->get($url)->baseResponse)->headers->get('Content-Security-Policy');
            foreach (["default-src 'self'", "object-src 'none'", "frame-ancestors 'self'", "form-action 'self'", "base-uri 'self'"] as $rule) {
                $this->assertStringContainsString($rule, $csp, $url);
            }
            $this->assertStringNotContainsString('*', $csp);
        }
        $this->get(route('login'))->assertHeader('Content-Security-Policy', \App\Http\Middleware\SecurityHeaders::CSP);
    }

    /** L2: Laravel's signed /storage file routes are switched off (the app never uses them). */
    public function test_storage_file_routes_are_switched_off(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('storage.local'));
        auth()->logout();
        $this->get('/storage/certificates/x.pdf')->assertNotFound();
    }

    /** L3: in production only the site's own domain (and the health check's 127.0.0.1 / localhost) is answered. */
    public function test_only_the_sites_own_host_names_are_trusted(): void
    {
        config(['app.url' => 'https://zplus.epf-dashboard.online']);
        $patterns = (new \Illuminate\Http\Middleware\TrustHosts(app()))->hosts();
        $trusted = fn (string $host) => collect($patterns)->contains(fn ($p) => preg_match('{'.$p.'}i', $host));
        foreach (['zplus.epf-dashboard.online', 'localhost', '127.0.0.1'] as $ok) {
            $this->assertTrue($trusted($ok), $ok);
        }
        foreach (['evil.example.com', 'zplus.epf-dashboard.online.evil.com', 'epf-dashboard.online.attacker.in'] as $bad) {
            $this->assertFalse($trusted($bad), $bad);
        }
    }

    /** L4: heavy downloads are limited to 20 a minute per user. */
    public function test_heavy_downloads_are_rate_limited(): void
    {
        $url = route('activity.export');
        for ($i = 1; $i <= 20; $i++) {
            $this->get($url)->assertOk();
        }
        $this->get($url)->assertStatus(429);
    }

    /** L7: search engines are told not to list any page. */
    public function test_search_engines_are_told_not_to_index(): void
    {
        auth()->logout();
        $this->get(route('login'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('Disallow: /', file_get_contents(public_path('robots.txt')));
    }
}
