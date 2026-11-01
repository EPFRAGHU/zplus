<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('agency_id')->nullable()->after('emp_code')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->after('agency_id')->constrained()->restrictOnDelete();
            $table->index(['agency_id', 'client_id']);
        });

        // Fill in from the post each employee already holds.
        $rows = DB::table('employees')
            ->join('posts', 'posts.id', '=', 'employees.post_id')
            ->join('projects', 'projects.id', '=', 'posts.project_id')
            ->join('gem_contracts', 'gem_contracts.id', '=', 'projects.gem_contract_id')
            ->get(['employees.id', 'gem_contracts.agency_id', 'gem_contracts.client_id']);
        foreach ($rows as $r) {
            DB::table('employees')->where('id', $r->id)->update(['agency_id' => $r->agency_id, 'client_id' => $r->client_id]);
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['agency_id', 'client_id']);
            $table->dropConstrainedForeignId('client_id');
            $table->dropConstrainedForeignId('agency_id');
        });
    }
};
