<?php

namespace App\Support;

use App\Models\GemContract;
use App\Models\ProjectIncharge;
use Illuminate\Validation\ValidationException;

/** Keeps a project's printed PI name in step with the Project In-charge master. */
final class PiAllotment
{
    /** When a PI is chosen from the master, the project's PI name and designation come from it. */
    public static function syncNames(array $data): array
    {
        if (! empty($data['project_incharge_id']) && $pi = ProjectIncharge::query()->find($data['project_incharge_id'])) {
            $data['pi_name'] = $pi->name;
            $data['pi_designation'] = $pi->designation;
        }

        return $data;
    }

    /** A project's PI must be one of its own agency's PIs (a "both agencies" login could otherwise mix them). */
    public static function assertSameAgency(mixed $inchargeId, GemContract $contract): void
    {
        if ($inchargeId && (int) ProjectIncharge::query()->whereKey($inchargeId)->value('agency_id') !== (int) $contract->agency_id) {
            throw ValidationException::withMessages(['project_incharge_id' => 'This Project In-charge belongs to the other agency. Add them under this agency in PI Allotment.']);
        }
    }

    /** After the PI master changes, refresh the names printed on their projects. */
    public static function refreshProjects(ProjectIncharge $pi): void
    {
        $pi->projects()->get()->each(fn ($p) => $p->update(['pi_name' => $pi->name, 'pi_designation' => $pi->designation]));
    }
}
