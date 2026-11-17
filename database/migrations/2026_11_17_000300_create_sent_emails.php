<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ADDITIVE ONLY: a record of every "E-mail to client" (who sent what to whom, and whether Gmail accepted it). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_emails', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 20);                                                      // sub_bill | tax_invoice
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('tax_invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->string('document_no');                                                   // kept if the bill is later deleted
            $t->string('from_address')->nullable();
            $t->text('to_addresses');
            $t->text('cc_addresses')->nullable();
            $t->string('subject');
            $t->json('attachments');                                                     // [{name, signed}]
            $t->string('status', 10);                                                    // sent | failed
            $t->string('error', 500)->nullable();
            $t->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['agency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_emails');
    }
};
