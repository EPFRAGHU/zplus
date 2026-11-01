<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A client can work with more than one agency (ICMR-NIHR has contracts with both SWACHHA and Z Plus).
        Schema::create('agency_client', function (Blueprint $table) {
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->primary(['agency_id', 'client_id']);
        });

        // Map existing clients to the agencies they already have contracts with.
        $pairs = DB::table('gem_contracts')->select('agency_id', 'client_id')->distinct()->get()
            ->map(fn ($r) => ['agency_id' => $r->agency_id, 'client_id' => $r->client_id])->all();
        if ($pairs) {
            DB::table('agency_client')->insertOrIgnore($pairs);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_client');
    }
};
