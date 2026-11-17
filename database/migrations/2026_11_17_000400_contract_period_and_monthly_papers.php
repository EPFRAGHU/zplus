<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADDITIVE ONLY (owner, 17 Nov 2026, after reading the 2026-27 GeM contracts):
 * - work order service period (start / end date) and total contract value, for expiry warnings
 * - monthly papers per work order (EPF / ESIC challans, salary payment proof, logbook, service feedback) for the
 *   GeM bill pack and the dashboard's 7th / 15th reminders
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gem_contracts', function (Blueprint $t) {
            $t->date('start_date')->nullable()->after('contract_date');
            $t->date('end_date')->nullable()->after('start_date');
            $t->decimal('contract_value', 15, 2)->nullable()->after('end_date');
        });

        Schema::create('monthly_papers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('gem_contract_id')->constrained()->cascadeOnDelete();
            $t->date('month');                                   // wage month (first day)
            $t->string('kind', 30);                              // epf_challan | esi_challan | salary_proof | logbook | feedback | other
            $t->string('path');
            $t->string('original_name');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['gem_contract_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_papers');
        Schema::table('gem_contracts', fn (Blueprint $t) => $t->dropColumn(['start_date', 'end_date', 'contract_value']));
    }
};
