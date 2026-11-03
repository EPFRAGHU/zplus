<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Agency\ScopedToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'type', 'path', 'original_name', 'expires_on'])]
class EmployeeDocument extends Model
{
    use LogsActivity, ScopedToAgency;

    protected function casts(): array
    {
        return ['expires_on' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
