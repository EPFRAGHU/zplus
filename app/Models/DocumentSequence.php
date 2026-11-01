<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['key', 'last_number'])]
class DocumentSequence extends Model
{
    /** Next number in a series, safe under concurrent requests. */
    public static function next(string $key): int
    {
        return DB::transaction(function () use ($key) {
            $seq = static::query()->where('key', $key)->lockForUpdate()->first()
                ?? static::query()->create(['key' => $key, 'last_number' => 0]);
            $seq->increment('last_number');

            return $seq->last_number;
        });
    }
}
