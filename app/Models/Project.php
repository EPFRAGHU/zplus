<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Agency\ScopedToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['gem_contract_id', 'name', 'code', 'title', 'pi_name', 'pi_designation', 'location', 'saturday_off'])]
class Project extends Model
{
    use LogsActivity, ScopedToAgency;

    /** PI logins for this project. */
    public function pis(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    protected function casts(): array
    {
        return ['saturday_off' => 'boolean'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(GemContract::class, 'gem_contract_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function employees(): HasManyThrough
    {
        return $this->hasManyThrough(Employee::class, Post::class);
    }

    public function attendanceSheets(): HasMany
    {
        return $this->hasMany(AttendanceSheet::class);
    }

    public function label(): string
    {
        return $this->name.($this->pi_name ? ' · '.$this->pi_name : '');
    }
}
