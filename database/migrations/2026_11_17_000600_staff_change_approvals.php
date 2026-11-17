<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADDITIVE ONLY (owner approval, release 2): staff and pay changes wait for the owner. A held change is stored here
 * (encrypted: it can hold bank account numbers) and applied to the employee / post only when approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $t) {
            $t->foreignId('employee_id')->nullable()->after('tax_invoice_id')->constrained()->nullOnDelete();
            $t->foreignId('post_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $t->text('changes')->nullable()->after('details');      // encrypted JSON: field => [old, new]
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $t) {
            $t->dropConstrainedForeignId('employee_id');
            $t->dropConstrainedForeignId('post_id');
            $t->dropColumn('changes');
        });
    }
};
