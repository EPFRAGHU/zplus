<?php

namespace App\Services\MasterImport;

use App\Models\GemContract;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ProjectSheet extends MasterSheet
{
    private Collection $contracts;

    private Collection $projects;

    public function type(): string
    {
        return 'projects';
    }

    public function title(): string
    {
        return 'Projects';
    }

    public function description(): string
    {
        return 'Projects (with PI) under each GeM contract. Needs the GeM contracts first.';
    }

    public function columns(): array
    {
        return [
            new Column('contract_no', 'GeM contract no.', true, 'text', null, 'Must already be in the app (GeM contracts).', 'GEMC-511687731718030', 26, asText: true, identifies: true),
            new Column('code', 'Project code', true, help: 'Short code used in bill numbers, e.g. MPH. Letters and digits only.', example: 'MPH', width: 12, identifies: true),
            new Column('name', 'Project name', true, example: 'MPH', width: 22),
            new Column('title', 'Full title', help: 'Printed on the covering letter', example: 'MPH Academic Programme', width: 36),
            new Column('pi_name', 'PI name', example: 'Dr. S. Kanungo', width: 20),
            new Column('pi_designation', 'PI designation', example: 'Scientist E', width: 18),
            new Column('location', 'Location', example: 'ICMR-NIHR, Bhubaneswar', width: 24),
            new Column('saturday_off', 'Saturday off', type: 'bool', help: 'Yes = Saturdays are weekly offs. Blank = Yes.', example: 'Yes', width: 12),
        ];
    }

    public function lists(): array
    {
        return ['contract_no' => GemContract::query()->orderBy('contract_no')->pluck('contract_no')->all(), 'saturday_off' => ['Yes', 'No']];
    }

    public function prepare(): void
    {
        parent::prepare();
        $this->contracts = GemContract::query()->get()->keyBy(fn ($c) => strtoupper($c->contract_no));
        $this->projects = Project::query()->get()->keyBy(fn ($p) => $p->gem_contract_id.'|'.strtoupper($p->code));
    }

    protected function check(array $v, int $row): RowResult
    {
        $no = strtoupper((string) $v['contract_no']);
        $code = strtoupper((string) $v['code']);
        $errors = $this->validate($v, ['code' => ['nullable', 'alpha_num', 'max:12'], 'name' => ['nullable', 'max:255'], 'title' => ['nullable', 'max:2000']]);
        $contract = $this->contracts[$no] ?? null;
        if ($no && ! $contract) {
            $errors[] = "GeM contract {$no} is not in the app. Upload the GeM contracts first.";
        }
        $data = ['gem_contract_id' => $contract?->id, 'code' => $code ?: null, 'name' => $v['name'], 'title' => $v['title'], 'pi_name' => $v['pi_name'],
            'pi_designation' => $v['pi_designation'], 'location' => $v['location'], 'saturday_off' => $v['saturday_off']];
        $existing = $contract ? ($this->projects[$contract->id.'|'.$code] ?? null) : null;

        return $this->result($row, "{$no}|{$code}", "{$code} · ".($v['name'] ?? '')." · {$no}", $data, $existing, $errors);
    }

    protected function newModel(): Model
    {
        return new Project(['saturday_off' => true]);
    }

    public function export(): iterable
    {
        foreach (Project::query()->with('contract')->get()->sortBy(fn ($p) => $p->contract?->contract_no.'|'.$p->code) as $p) {
            yield ['contract_no' => $p->contract?->contract_no, 'code' => $p->code, 'name' => $p->name, 'title' => $p->title, 'pi_name' => $p->pi_name,
                'pi_designation' => $p->pi_designation, 'location' => $p->location, 'saturday_off' => self::yesNo($p->saturday_off ?? true)];
        }
    }
}
