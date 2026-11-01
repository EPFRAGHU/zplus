<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->date('month');                                   // first day of the month
            $table->string('holidays')->nullable();                  // "2,19,20"
            $table->string('status', 12)->default('draft');          // draft | locked
            $table->string('certificate_path')->nullable();          // signed scan from the PI
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'month']);
        });

        Schema::create('attendance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->json('codes');                                   // {"1":"P","2":"H",...}
            $table->decimal('paid_days', 4, 1);
            $table->timestamps();
            $table->unique(['attendance_sheet_id', 'employee_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 40)->unique();              // ICMR/MPH/01/2026
            $table->foreignId('gem_contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_sheet_id')->constrained()->restrictOnDelete();
            $table->date('month');
            $table->date('invoice_date');
            $table->string('billing_type', 10);
            $table->decimal('service_charge_percent', 6, 4);
            $table->decimal('gst_percent', 6, 4);
            $table->decimal('wages', 14, 2);
            $table->decimal('epf', 14, 2)->default(0);
            $table->decimal('esic', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('service_charge', 14, 2);
            $table->decimal('taxable', 14, 2);
            $table->decimal('cgst', 14, 2)->default(0);
            $table->decimal('sgst', 14, 2)->default(0);
            $table->decimal('igst', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('rounded_total', 14, 2);
            $table->string('status', 12)->default('draft');          // draft | approved | submitted | paid
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('paid_on')->nullable();
            $table->timestamps();
            $table->unique('attendance_sheet_id');                   // one bill per locked sheet
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_name');
            $table->string('designation');
            $table->decimal('rate', 12, 2);
            $table->string('rate_type', 10);
            $table->unsignedTinyInteger('days_in_month')->nullable();
            $table->decimal('paid_days', 4, 1);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                         // e.g. invoice:ICMR:MPH:2026
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);                              // appointment_letter | covering_letter | invoice
            $table->string('number', 60)->nullable();
            $table->morphs('documentable');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('attendance_entries');
        Schema::dropIfExists('attendance_sheets');
    }
};
