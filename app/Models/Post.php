<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'designation', 'sanctioned_qty', 'rate'])]
class Post extends Model
{
    protected function casts(): array
    {
        return ['rate' => 'decimal:2'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function activeEmployees(): HasMany
    {
        return $this->employees()->whereIn('status', ['active', 'notice']);
    }

    public function label(): string
    {
        return $this->project->name.' · '.$this->designation.' · ₹'.number_format((float) $this->rate);
    }
}
