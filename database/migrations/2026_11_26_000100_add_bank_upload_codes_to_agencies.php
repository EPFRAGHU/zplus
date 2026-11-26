<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADDITIVE ONLY (owner, 26 Nov 2026): the agency's codes for the bank's bulk salary upload sheet (SBI corporate
 * internet banking): corporate ID, corporate short name and address code. The debit account is the agency's
 * existing bank account number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $t) {
            $t->string('bank_corp_id', 20)->nullable()->after('bank_branch');      // e.g. 286782
            $t->string('bank_corp_name', 40)->nullable()->after('bank_corp_id');   // e.g. ZPSS
            $t->string('bank_address_code', 40)->nullable()->after('bank_corp_name');   // e.g. BBSR
        });
    }

    public function down(): void
    {
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn(['bank_corp_id', 'bank_corp_name', 'bank_address_code']));
    }
};
