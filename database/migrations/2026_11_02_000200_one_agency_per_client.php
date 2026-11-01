<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each client record now belongs to exactly one agency. A client served by both agencies
 * is entered twice (once per agency). Bill numbers run in a separate series per agency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('agency_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->dropUnique(['short_code']);
        });

        // Convert the earlier many-to-many mapping: first agency stays on the record,
        // every further agency gets its own copy of the client and takes its contracts along.
        foreach (DB::table('clients')->get() as $client) {
            $agencies = DB::table('agency_client')->where('client_id', $client->id)->orderBy('agency_id')->pluck('agency_id')->all();
            $agencies = array_values(array_unique(array_merge($agencies,
                DB::table('gem_contracts')->where('client_id', $client->id)->distinct()->orderBy('agency_id')->pluck('agency_id')->all())));
            if (! $agencies) {
                continue;
            }
            DB::table('clients')->where('id', $client->id)->update(['agency_id' => array_shift($agencies)]);
            foreach ($agencies as $agencyId) {
                $copy = (array) $client;
                unset($copy['id']);
                $copy['agency_id'] = $agencyId;
                $newId = DB::table('clients')->insertGetId($copy);
                DB::table('gem_contracts')->where('client_id', $client->id)->where('agency_id', $agencyId)->update(['client_id' => $newId]);
            }
        }
        Schema::dropIfExists('agency_client');

        Schema::table('clients', function (Blueprint $table) {
            $table->unique(['agency_id', 'short_code']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('agency_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });
        DB::table('invoices')->orderBy('id')->each(function ($inv) {
            DB::table('invoices')->where('id', $inv->id)
                ->update(['agency_id' => DB::table('gem_contracts')->where('id', $inv->gem_contract_id)->value('agency_id')]);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['invoice_no']);
            $table->unique(['agency_id', 'invoice_no']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['agency_id', 'invoice_no']);
            $table->unique('invoice_no');
            $table->dropConstrainedForeignId('agency_id');
        });
        Schema::create('agency_client', function (Blueprint $table) {
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->primary(['agency_id', 'client_id']);
        });
        DB::table('clients')->whereNotNull('agency_id')->get(['id', 'agency_id'])
            ->each(fn ($c) => DB::table('agency_client')->insert(['agency_id' => $c->agency_id, 'client_id' => $c->id]));

        // Merge the per-agency copies of a client back into one record so bill codes are unique again.
        foreach (DB::table('clients')->select('short_code')->groupBy('short_code')->havingRaw('count(*) > 1')->pluck('short_code') as $code) {
            $ids = DB::table('clients')->where('short_code', $code)->orderBy('id')->pluck('id')->all();
            $keep = array_shift($ids);
            foreach ($ids as $dup) {
                DB::table('gem_contracts')->where('client_id', $dup)->update(['client_id' => $keep]);
                foreach (DB::table('agency_client')->where('client_id', $dup)->pluck('agency_id') as $agencyId) {
                    DB::table('agency_client')->insertOrIgnore(['agency_id' => $agencyId, 'client_id' => $keep]);
                }
                DB::table('agency_client')->where('client_id', $dup)->delete();
                DB::table('clients')->where('id', $dup)->delete();
            }
        }
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['agency_id', 'short_code']);
            $table->dropConstrainedForeignId('agency_id');
            $table->unique('short_code');
        });
    }
};
