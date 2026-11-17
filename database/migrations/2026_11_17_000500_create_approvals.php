<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADDITIVE ONLY: maker–checker approvals (owner, 17 Nov 2026). Each "Send for approval" adds a row; the owner (Super
 * Admin) approves or returns it with a reason. Rows are never rewritten except their own status (history kept).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20);                                                  // attendance | sub_bill | tax_invoice | payout
            $t->foreignId('attendance_sheet_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('tax_invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->date('month');                                                       // the wage / bill month
            $t->string('label');                                                     // what it was, kept if the record is deleted later
            $t->string('fingerprint', 64);                                           // figures when sent: a later change needs a new approval
            $t->json('details')->nullable();                                         // payout: one fingerprint per project sheet
            $t->string('status', 12);                                                // pending | approved | returned | superseded
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at');
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->text('reason')->nullable();                                          // why it was returned
            $t->timestamps();
            $t->index(['type', 'status']);
            $t->index(['agency_id', 'type', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
