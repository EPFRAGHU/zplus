<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('supervisor')->after('email'); // admin | accounts | supervisor
        });

        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique();            // SWH, ZPS: used in letter numbers
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('signatory_name')->nullable();
            $table->string('signatory_title')->nullable();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_code', 12)->unique();       // ICMR: used in bill numbers
            $table->string('letter_code', 12)->nullable();     // NIHR: used in appointment letter numbers
            $table->string('addressee')->nullable();           // "The Administrative Officer"
            $table->text('billing_address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('district')->nullable();
            $table->string('state_code', 2)->default('21');    // Odisha
            $table->string('gstin', 15)->nullable();
            $table->unsignedSmallInteger('payment_term_days')->default(30);
            $table->string('status', 12)->default('active');
            $table->timestamps();
        });

        Schema::create('gem_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('bid_no', 40);                      // GEM/2026/B/7972720
            $table->string('contract_no', 40)->unique();       // GEMC-511687731718030
            $table->date('contract_date')->nullable();
            $table->string('billing_type', 10)->default('monthly'); // monthly | daily
            $table->decimal('service_charge_percent', 6, 4)->default(3.85);
            $table->decimal('gst_percent', 6, 4)->default(18);
            $table->decimal('epf_percent', 6, 4)->default(13);
            $table->decimal('esic_percent', 6, 4)->default(3.25);
            $table->boolean('inter_state')->default(false);
            $table->string('status', 12)->default('active');
            $table->timestamps();
            $table->index('bid_no');
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gem_contract_id')->constrained()->cascadeOnDelete();
            $table->string('name');                             // MPH
            $table->string('code', 12);                         // MPH: used in bill / letter numbers
            $table->text('title')->nullable();                  // full title for the covering letter
            $table->string('pi_name')->nullable();
            $table->string('pi_designation')->nullable();
            $table->string('location')->nullable();
            $table->boolean('saturday_off')->default(true);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('designation');
            $table->unsignedSmallInteger('sanctioned_qty')->default(1);
            $table->decimal('rate', 12, 2);                     // per month, or per day on daily contracts
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('gem_contracts');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('agencies');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
    }
};
