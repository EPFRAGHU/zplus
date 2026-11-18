<?php

namespace App\Console\Commands;

use App\Support\Release;
use Illuminate\Console\Command;

/** CHANGELOG.md is generated from config/releases.php so the file and the in-app release notes never disagree. */
class WriteChangelog extends Command
{
    protected $signature = 'releases:changelog';

    protected $description = 'Write CHANGELOG.md from config/releases.php';

    public function handle(): int
    {
        $lines = ['# Changelog', '', 'Versions follow MAJOR.MINOR.PATCH. Dates are Indian Standard Time.', ''];
        foreach (Release::all() as $release) {
            $lines[] = sprintf('## %s (%s): %s', $release['version'], $release['date']->format('Y-m-d'), $release['title']);
            $lines[] = '';
            foreach ($release['notes'] as $note) {
                $lines[] = '- '.$note;
            }
            $lines[] = '';
        }
        file_put_contents(base_path('CHANGELOG.md'), implode("\n", $lines));
        $this->info('CHANGELOG.md written ('.count(Release::all()).' versions).');

        return self::SUCCESS;
    }
}
