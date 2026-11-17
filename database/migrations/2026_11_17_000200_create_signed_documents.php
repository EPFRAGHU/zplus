<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DSC-signed copies uploaded back into the app (owner, 17 Nov 2026): a sub-bill, a tax invoice, or an agency's
 * bank letter for a month. ADDITIVE: a new table only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signed_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 20);                                         // sub_bill | tax_invoice | bank_letter
            $t->foreignId('invoice_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('tax_invoice_id')->nullable()->constrained()->cascadeOnDelete();
            $t->date('month')->nullable();                                  // bank letter: the month
            $t->string('path');
            $t->string('original_name');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['agency_id', 'kind', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signed_documents');
    }
};
