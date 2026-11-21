<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Owner, 22 Nov 2026: short project names and client / letter codes up to 20 characters. Only widens columns; no data changes. */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (tests, local copies) does not enforce string lengths, and changing a column there rebuilds the table and
        // drops the agency-isolation triggers; the live MySQL database is the one that needs the wider columns.
        if (! in_array(\Illuminate\Support\Facades\DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        Schema::table('projects', fn (Blueprint $t) => $t->string('code', 20)->change());
        Schema::table('clients', function (Blueprint $t) {
            $t->string('short_code', 20)->change();
            $t->string('letter_code', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Narrowing could cut existing codes, so the wider columns are kept.
    }
};
