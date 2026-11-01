<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('emp_code', 20)->unique();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->date('dob')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('mobile', 15)->nullable();
            $table->string('whatsapp', 15)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('qualification')->nullable();
            $table->date('joining_date')->nullable();
            $table->date('exit_date')->nullable();
            $table->string('status', 12)->default('active');     // active | notice | inactive
            $table->decimal('rate_override', 12, 2)->nullable();  // when salary differs from the post rate
            // Bank and payout
            $table->string('bank_name')->nullable();
            $table->string('account_holder')->nullable();
            $table->text('account_number')->nullable();           // encrypted
            $table->string('account_last4', 4)->nullable();        // for display and search
            $table->string('ifsc', 11)->nullable();
            $table->string('upi_id')->nullable();
            $table->timestamp('bank_verified_at')->nullable();
            $table->string('cashfree_beneficiary_id')->nullable();
            // KYC and statutory
            $table->string('pan', 10)->nullable();
            $table->text('aadhaar')->nullable();                   // encrypted
            $table->string('aadhaar_last4', 4)->nullable();
            $table->string('uan', 12)->nullable();
            $table->string('esic_ip', 17)->nullable();
            $table->boolean('pf_applicable')->default(false);
            $table->boolean('esic_applicable')->default(false);
            $table->timestamps();
            $table->index(['status', 'post_id']);
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);       // aadhaar, pan, photo, bank_proof, police_verification, appointment_signed ...
            $table->string('path');           // private disk
            $table->string('original_name');
            $table->date('expires_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employees');
    }
};
