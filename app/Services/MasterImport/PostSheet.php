<?php

namespace App\Services\MasterImport;

use App\Models\GemContract;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class PostSheet extends MasterSheet
{
    private Collection $projects;

    private Collection $posts;

    public function type(): string
    {
        return 'posts';
    }

    public function title(): string
    {
        return 'Posts';
    }

    public function description(): string
    {
        return 'Sanctioned posts and rates under each project. Needs the projects first.';
    }

    public function columns(): array
    {
        return [
            new Column('contract_no', 'GeM contract no.', true, 'text', null, 'Must already be in the app.', 'GEMC-511687731718030', 26, asText: true, identifies: true),
            new Column('project_code', 'Project code', true, help: 'The project\'s code under that contract', example: 'MPH', width: 12, identifies: true),
            new Column('designation', 'Designation', true, example: 'Academic Programme Manager', width: 30, identifies: true),
            new Column('sanctioned_qty', 'Sanctioned posts', true, 'number', help: 'Number of people sanctioned for this post', example: '1', width: 14),
            new Column('rate', 'Rate (₹)', true, 'number', help: 'Per month (monthly contracts) or per day (daily contracts)', example: '55000', width: 12),
        ];
    }

    public function lists(): array
    {
        return ['contract_no' => GemContract::query()->orderBy('contract_no')->pluck('contract_no')->all()];
    }

    public function prepare(): void
    {
        parent::prepare();
        $this->projects = Project::query()->with('contract')->get()->keyBy(fn ($p) => strtoupper((string) $p->contract?->contract_no).'|'.strtoupper($p->code));
        $this->posts = Post::query()->get()->keyBy(fn ($p) => $p->project_id.'|'.mb_strtolower(trim($p->designation)));
    }

    protected function check(array $v, int $row): RowResult
    {
        $no = strtoupper((string) $v['contract_no']);
        $code = strtoupper((string) $v['project_code']);
        $errors = $this->validate($v, ['designation' => ['nullable', 'max:255'], 'sanctioned_qty' => ['nullable', 'integer', 'min:0', 'max:999'],
            'rate' => ['nullable', 'numeric', 'min:0', 'decimal:0,2']]);
        $project = $this->projects["{$no}|{$code}"] ?? null;
        if ($no && $code && ! $project) {
            $errors[] = "Project {$code} under contract {$no} is not in the app. Upload the projects first.";
        }
        $designation = $v['designation'] ? trim($v['designation']) : null;
        $data = ['project_id' => $project?->id, 'designation' => $designation,
            'sanctioned_qty' => $v['sanctioned_qty'] !== null ? (int) $v['sanctioned_qty'] : null, 'rate' => $v['rate']];
        $existing = $project ? ($this->posts[$project->id.'|'.mb_strtolower((string) $designation)] ?? null) : null;

        return $this->result($row, "{$no}|{$code}|".mb_strtolower((string) $designation), "{$designation} · {$code} · ₹".($v['rate'] ?? ''), $data, $existing, $errors);
    }

    protected function newModel(): Model
    {
        return new Post;
    }

    public function export(): iterable
    {
        foreach (Post::query()->with('project.contract')->get()->sortBy(fn ($p) => $p->project?->contract?->contract_no.'|'.$p->project?->code.'|'.$p->designation) as $p) {
            yield ['contract_no' => $p->project?->contract?->contract_no, 'project_code' => $p->project?->code, 'designation' => $p->designation,
                'sanctioned_qty' => $p->sanctioned_qty, 'rate' => (float) $p->rate];
        }
    }
}
