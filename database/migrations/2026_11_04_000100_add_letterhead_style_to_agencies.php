<?php

use App\Models\Agency;
use App\Support\KnownLetterheads;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            // classic: centred block with footer (Z Plus); banner: logo, name, registrations column and an address box (SWACHHA)
            $table->string('letterhead_style', 20)->default('classic')->after('logo_path');
        });

        // SWACHHA's details from its printed letterhead, only where fields are still empty.
        if ($swh = Agency::query()->where('code', 'SWH')->first()) {
            KnownLetterheads::applySwachha($swh);
        }
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn('letterhead_style');
        });
    }
};
