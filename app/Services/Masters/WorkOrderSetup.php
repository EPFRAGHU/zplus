<?php

namespace App\Services\Masters;

use App\Models\Employee;
use App\Models\GemContract;
use App\Models\ProjectIncharge;
use App\Models\User;
use App\Services\Approvals\StaffChanges;
use App\Support\PiAllotment;
use Illuminate\Support\Facades\DB;

/**
 * Saves the projects, posts and staff entered on the one-page "Add work order" form (owner, 21 Nov 2026).
 * Staff go through StaffChanges like any new employee, so they stay Inactive until a Super Admin approves them.
 */
class WorkOrderSetup
{
    public function __construct(private StaffChanges $staffChanges) {}

    /** @param array<int, array<string, mixed>> $projects validated rows (see GemContractController::setupRules) */
    public function addProjects(GemContract $contract, array $projects, User $maker): array
    {
        return DB::transaction(function () use ($contract, $projects, $maker) {
            $counts = ['projects' => 0, 'posts' => 0, 'staff' => 0, 'pis' => 0];
            foreach ($projects as $row) {
                $inchargeId = $row['project_incharge_id'] ?? null;
                PiAllotment::assertSameAgency($inchargeId, $contract);
                if (! $inchargeId && filled($row['pi_name'] ?? null)) {
                    $inchargeId = $this->incharge($contract, $row['pi_name'], $row['pi_mobile'] ?? null, $counts);
                }
                $project = $contract->projects()->create(PiAllotment::syncNames([
                    'code' => strtoupper($row['code']), 'name' => $row['name'], 'project_incharge_id' => $inchargeId, 'saturday_off' => false,
                ]));
                $counts['projects']++;

                $posts = [];
                foreach ($row['posts'] ?? [] as $i => $p) {
                    $posts[$i] = $project->posts()->create(['designation' => $p['designation'], 'sanctioned_qty' => $p['sanctioned_qty'], 'rate' => $p['rate']]);
                    $counts['posts']++;
                }
                foreach ($row['staff'] ?? [] as $s) {
                    $this->staffChanges->create([
                        'emp_code' => Employee::nextCode((int) $contract->agency_id),
                        'agency_id' => $contract->agency_id, 'client_id' => $contract->client_id, 'post_id' => $posts[$s['post']]->id,
                        'name' => $s['name'], 'mobile' => $s['mobile'] ?? null, 'joining_date' => $s['joining_date'] ?? null, 'status' => 'active',
                        'pf_applicable' => ! empty($s['pf']), 'esic_applicable' => ! empty($s['esic']), 'tds_applicable' => ! empty($s['tds']),
                    ], $maker);
                    $counts['staff']++;
                }
            }

            return $counts;
        });
    }

    /** One PI can look after several projects: a typed name that is already saved for this agency is reused, not added twice. */
    private function incharge(GemContract $contract, string $name, ?string $mobile, array &$counts): int
    {
        $name = preg_replace('/\s+/', ' ', trim($name));
        $existing = ProjectIncharge::query()->where('agency_id', $contract->agency_id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($existing) {
            if ($mobile && ! $existing->mobile) {
                $existing->update(['mobile' => $mobile]);
            }

            return $existing->id;
        }
        $counts['pis']++;

        return ProjectIncharge::query()->create(['agency_id' => $contract->agency_id, 'name' => $name, 'mobile' => $mobile, 'status' => 'active'])->id;
    }
}
