<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TDS 2% on/off per employee (owner, 17 Nov 2026). Additive: everyone starts ON, as TDS was deducted from all. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', fn (Blueprint $t) => $t->boolean('tds_applicable')->default(true)->after('esic_applicable'));
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('tds_applicable'));
    }
};
