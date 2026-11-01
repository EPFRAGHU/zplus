<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['project_id', 'month', 'holidays', 'status', 'certificate_path'])]
class AttendanceSheet extends Model
{
    /**
     * Always store the month as "Y-m-d" (first of the month). A plain date cast saves
     * "Y-m-d 00:00:00", which on SQLite no longer matches lookups by "Y-m-d".
     */
    protected function month(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : CarbonImmutable::parse($value)->startOfDay(),
            set: fn ($value) => $value === null ? null : CarbonImmutable::parse($value)->startOfMonth()->toDateString(),
        );
    }

    protected function casts(): array
    {
        return ['locked_at' => 'datetime'];
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
