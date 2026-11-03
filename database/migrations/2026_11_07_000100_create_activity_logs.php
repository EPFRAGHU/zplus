<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Activity log of every change, view of sensitive data, download and sign-in; readable only by the Super Admin. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();       // kept even if the user is removed later
            $table->string('user_role', 20)->nullable();
            $table->string('action', 20);                  // created | updated | deleted | status | viewed | downloaded | signed_in | ...
            $table->string('section', 40)->nullable();     // Employee, Sub-bill, Attendance ...
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->json('changes')->nullable();           // field => [old, new]
            $table->string('note', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('role');
        });
        // The owner's account: the first Admin becomes the Super Admin.
        $first = DB::table('users')->where('role', 'admin')->orderBy('id')->value('id');
        if ($first) {
            DB::table('users')->where('id', $first)->update(['is_super_admin' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_super_admin'));
        Schema::dropIfExists('activity_logs');
    }
};
