<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['project_id', 'month', 'holidays', 'status', 'certificate_path'])]
class AttendanceSheet extends Model
{
    protected function casts(): array
    {
        return ['month' => 'immutable_date', 'locked_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AttendanceEntry::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    /** @return int[] */
    public function holidayDays(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->holidays))));
    }
}
