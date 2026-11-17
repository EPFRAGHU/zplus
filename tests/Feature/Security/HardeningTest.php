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
}
