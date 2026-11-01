<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tax invoice combines the month's sub-bills (one per project) for one client of one agency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('number', 40);                    // e.g. ICMR/TI/01/2026
            $table->date('month');                           // first day of the month
            $table->date('invoice_date');
            $table->string('place_of_supply')->default('Odisha');
            $table->decimal('taxable', 14, 2);
            $table->decimal('cgst', 14, 2)->default(0);
            $table->decimal('sgst', 14, 2)->default(0);
            $table->decimal('igst', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('rounded_total', 14, 2);
            $table->string('status', 12)->default('draft');  // draft | submitted | paid
            $table->date('paid_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['agency_id', 'number']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('tax_invoice_id')->nullable()->after('attendance_sheet_id')->constrained()->nullOnDelete();
        });

        Schema::table('gem_contracts', function (Blueprint $table) {
            $table->string('sac_code', 10)->default('998514')->after('billing_type');   // HSN/SAC printed on bills
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('logo_path');
            $table->string('bank_account_no', 30)->nullable()->after('bank_name');
            $table->string('bank_ifsc', 11)->nullable()->after('bank_account_no');
            $table->string('bank_branch')->nullable()->after('bank_ifsc');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn(['bank_name', 'bank_account_no', 'bank_ifsc', 'bank_branch']));
        Schema::table('gem_contracts', fn (Blueprint $t) => $t->dropColumn('sac_code'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropConstrainedForeignId('tax_invoice_id'));
        Schema::dropIfExists('tax_invoices');
    }
};
