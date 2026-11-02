<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EPF and ESIC are now switched on or off per employee. Bills add the employer share and the salary sheet deducts the
 * employee share only for staff with them switched on. Each bill line keeps its own EPF and ESIC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->decimal('epf', 14, 2)->default(0)->after('amount');
            $table->decimal('esic', 14, 2)->default(0)->after('epf');
        });

        // Daily-rate contracts used to add EPF and ESIC for everyone: keep that by switching both on for their staff.
        $dailyPostIds = DB::table('posts')->join('projects', 'projects.id', '=', 'posts.project_id')
            ->join('gem_contracts', 'gem_contracts.id', '=', 'projects.gem_contract_id')
            ->where('gem_contracts.billing_type', 'daily')->pluck('posts.id');
        DB::table('employees')->whereIn('post_id', $dailyPostIds)->update(['pf_applicable' => true, 'esic_applicable' => true]);
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['epf', 'esic']);
        });
    }
};
