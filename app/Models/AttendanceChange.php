<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A code the agency changed after the PI submitted the month. */
#[Fillable(['attendance_sheet_id', 'employee_id', 'employee_name', 'day', 'old_code', 'new_code', 'user_id'])]
class AttendanceChange extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
