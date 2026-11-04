<?php

namespace App\Support\Uploads;

use Illuminate\Support\Facades\Storage;

/**
 * Uploaded Excel files hold staff data. They are deleted after saving, but a file that was checked
 * and never saved would stay on the server for ever. Remove any older than a day.
 */
class StaleImports
{
    public static function purge(string $directory, int $maxAgeSeconds = 86400): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files($directory) as $file) {
            if ($disk->lastModified($file) < time() - $maxAgeSeconds) {
                $disk->delete($file);
            }
        }
    }
}
