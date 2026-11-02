<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PI portal: PI logins linked to projects, PI-submitted attendance with the PDF made at submission,
 * a record of every change the agency makes after submission, and a log of sign-ins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('role');
            $table->boolean('must_change_password')->default(false)->after('active');
            $table->string('phone', 15)->nullable()->after('email');
            $table->timestamp('last_login_at')->nullable();
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'project_id']);
        });

        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->boolean('success');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('attendance_sheets', function (Blueprint $table) {
            // status: draft | submitted (by the PI) | locked (approved by the agency)
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->string('submitted_pdf_path')->nullable()->after('submitted_by');   // the certificate exactly as submitted
        });

        Schema::create('attendance_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_name');
            $table->unsignedTinyInteger('day');
            $table->string('old_code', 4);
            $table->string('new_code', 4);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('attendance_sheet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_changes');
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn(['submitted_at', 'submitted_pdf_path']);
        });
        Schema::dropIfExists('login_events');
        Schema::dropIfExists('project_user');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['active', 'must_change_password', 'phone', 'last_login_at']));
    }
};
