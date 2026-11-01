<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['type', 'number', 'documentable_type', 'documentable_id', 'created_by'])]
class GeneratedDocument extends Model
{
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
