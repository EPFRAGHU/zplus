<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Agency\ScopedToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Project In-charge (PI) master, per agency. One PI can be in charge of several projects, under different work orders.
 * Projects, certificates, bills and the PI login read the PI from here.
 */
#[Fillable(['agency_id', 'name', 'designation', 'mobile', 'email', 'user_id', 'status'])]
class ProjectIncharge extends Model
{
    use LogsActivity, ScopedToAgency;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return $this->name.($this->designation ? ', '.$this->designation : '');
    }
}
