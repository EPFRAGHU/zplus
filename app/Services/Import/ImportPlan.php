<?php

namespace App\Services\Import;

final class ImportPlan
{
    /** @var array<string, array> contract_no => contract with projects and posts */
    public array $contracts = [];

    /** @var array<int, array> staff rows from PI sheets */
    public array $staff = [];

    /** @var array<int, array{level:string,title:string,details:array}> */
    public array $issues = [];

    public function issue(string $level, string $title, array $details): void
    {
        if ($details !== []) {
            $this->issues[] = ['level' => $level, 'title' => $title, 'details' => $details];
        }
    }

    /** @return iterable<array> every post across contracts */
    public function posts(): iterable
    {
        foreach ($this->contracts as $c) {
            foreach ($c['projects'] as $p) {
                yield from $p['posts'];
            }
        }
    }

    public function summary(): array
    {
        $projects = array_sum(array_map(fn ($c) => count($c['projects']), $this->contracts));
        $posts = iterator_to_array($this->posts(), false);

        return [
            'contracts' => count($this->contracts),
            'projects' => $projects,
            'posts' => count($posts),
            'sanctioned' => array_sum(array_column($posts, 'qty')),
            'staff' => count($this->staff),
            'must_fix' => array_sum(array_map(fn ($i) => $i['level'] === 'bad' ? count($i['details']) : 0, $this->issues)),
        ];
    }
}
