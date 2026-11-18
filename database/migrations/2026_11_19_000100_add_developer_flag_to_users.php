<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADDITIVE ONLY (owner, 19 Nov 2026): two Super Admins approve and delete for both agencies, but only the app's
 * developer (the owner) keeps Users, making Super Admins, the Activity log and Data Upload / GeM import.
 * The Super Admin that exists when this runs is the developer; Super Admins made later are not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_developer')->default(false)->after('is_super_admin'));
        DB::table('users')->where('is_super_admin', true)->update(['is_developer' => true]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_developer'));
    }
};
