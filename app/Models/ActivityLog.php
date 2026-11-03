<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** One entry in the activity log. Entries are never changed or deleted by the app. */
#[Fillable(['user_id', 'user_name', 'user_role', 'action', 'section', 'subject_type', 'subject_id', 'subject_label', 'changes', 'note', 'ip', 'user_agent'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'created' => ['Added', 'success'], 'updated' => ['Edited', 'info'], 'deleted' => ['Deleted', 'danger'], 'status' => ['Status', 'primary'],
        'viewed' => ['Viewed', 'secondary'], 'downloaded' => ['Downloaded', 'secondary'], 'imported' => ['Data upload', 'warning'],
        'signed_in' => ['Signed in', 'light border'], 'sign_in_failed' => ['Sign-in failed', 'danger'], 'signed_out' => ['Signed out', 'light border'],
        'password' => ['Password', 'warning'],
    ];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Activity log entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Activity log entries cannot be deleted.'));
    }
}
