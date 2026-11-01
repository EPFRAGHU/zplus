<?php

use App\Models\Agency;
use App\Support\KnownLetterheads;
use Illuminate\Database\Migrations\Migration;

/**
 * The previous migration switched SWACHHA to its banner letterhead only when its letterhead fields were empty and
 * its code was SWH. Switch every SWACHHA record (by code or name) now, filling only fields that are still empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Agency::query()->where('code', 'SWH')->orWhere('name', 'like', '%SWACHHA%')->get()
            ->each(function (Agency $agency) {
                KnownLetterheads::applySwachha($agency);
                $agency->forceFill(['letterhead_style' => 'banner'])->save();
            });
    }

    public function down(): void
    {
        // Keeps the layout; it can be changed under Agencies → Edit.
    }
};
