<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADDITIVE ONLY (owner, 26 Nov 2026): a record of each database backup, reported by the backup script on the server
 * (primary) and by the copy kept elsewhere, e.g. the owner's laptop (secondary). Shown to the developer on the dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $t) {
            $t->id();
            $t->string('location', 12);                 // primary | secondary
            $t->string('server', 80);                   // e.g. srv1962767, RAGHU-DELL
            $t->string('status', 8);                    // ok | failed
            $t->string('file', 190)->nullable();        // backup file name (no path)
            $t->unsignedBigInteger('size_bytes')->nullable();
            $t->string('note', 255)->nullable();
            $t->timestamp('finished_at');
            $t->timestamps();
            $t->index(['location', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
