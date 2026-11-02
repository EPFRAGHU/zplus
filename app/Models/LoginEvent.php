<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'email', 'success', 'ip', 'user_agent'])]
class LoginEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['success' => 'boolean', 'created_at' => 'datetime'];
    }
}
