<?php

namespace App\Support\Activity;

/** Add to a model to log every add, edit and delete automatically (who, when, old → new). */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($m) => Activity::log('created', $m, Activity::snapshot($m)));
        static::updated(function ($m) {
            $changes = Activity::diff($m);
            if ($changes) {
                $onlyStatus = array_diff(array_keys($changes), ['status', 'approved_by', 'approved_at', 'paid_on', 'locked_at', 'locked_by', 'submitted_at', 'submitted_by', 'submitted_pdf_path']) === [];
                Activity::log(array_key_exists('status', $changes) && $onlyStatus ? 'status' : 'updated', $m, $changes);
            }
        });
        static::deleted(fn ($m) => Activity::log('deleted', $m, Activity::snapshot($m)));
    }
}
