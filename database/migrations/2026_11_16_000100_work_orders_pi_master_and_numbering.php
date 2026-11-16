<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Owner's November changes. ADDITIVE ONLY: new tables and new columns; no existing row is changed or deleted
 * (apart from filling the new columns' starting values), and existing bills keep their amounts and numbers.
 *
 * - Work orders: online (GeM) / offline; service charge "includes GST" (charged as amount x % / 1.18)
 * - Agency number prefix (SW, ZP) for SW/001/26-27 style bill and tax invoice numbers and SW-1001 employee IDs
 * - Tax invoice per work order (gem_contract_id)
 * - Project In-charge master (PI Allotment), linked to projects
 * - Bill and letter codes up to 16 characters (MySQL column widened; SQLite has no length limit)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gem_contracts', function (Blueprint $t) {
            $t->string('contract_type', 10)->default('online')->after('client_id');            // online (GeM) | offline
            $t->boolean('service_charge_includes_gst')->default(true)->after('service_charge_percent');
        });

        Schema::table('agencies', fn (Blueprint $t) => $t->string('number_prefix', 6)->nullable()->after('code'));
        foreach (DB::table('agencies')->get(['id', 'code', 'name']) as $a) {
            $prefix = match (true) {
                $a->code === 'SWH' || stripos($a->name, 'SWACHHA') !== false => 'SW',
                $a->code === 'ZPS' || stripos($a->name, 'Z Plus') !== false || stripos($a->name, 'ZPLUS') !== false => 'ZP',
                default => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $a->code), 0, 2)),
            };
            DB::table('agencies')->where('id', $a->id)->update(['number_prefix' => $prefix]);
        }

        // Bills made before this change keep the old calculation; the PDF wording follows this flag.
        Schema::table('invoices', fn (Blueprint $t) => $t->boolean('service_charge_includes_gst')->default(false)->after('service_charge_percent'));

        Schema::table('tax_invoices', fn (Blueprint $t) => $t->foreignId('gem_contract_id')->nullable()->after('client_id')->constrained()->nullOnDelete());

        Schema::create('project_incharges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('designation')->nullable();
            $t->string('mobile', 20)->nullable();
            $t->string('email')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();     // their PI portal login, if any
            $t->string('status', 10)->default('active');
            $t->timestamps();
            $t->index(['agency_id', 'name']);
        });
        Schema::table('projects', fn (Blueprint $t) => $t->foreignId('project_incharge_id')->nullable()->after('gem_contract_id')->constrained('project_incharges')->nullOnDelete());

        // Existing projects: one master record per distinct PI name per agency, linked to its projects.
        $projects = DB::table('projects')->join('gem_contracts', 'gem_contracts.id', '=', 'projects.gem_contract_id')
            ->whereNotNull('projects.pi_name')->where('projects.pi_name', '<>', '')
            ->get(['projects.id', 'projects.pi_name', 'projects.pi_designation', 'gem_contracts.agency_id']);
        $made = [];
        foreach ($projects as $p) {
            $key = $p->agency_id.'|'.mb_strtolower(trim($p->pi_name));
            $made[$key] ??= DB::table('project_incharges')->insertGetId(['agency_id' => $p->agency_id, 'name' => trim($p->pi_name),
                'designation' => $p->pi_designation, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('projects')->where('id', $p->id)->update(['project_incharge_id' => $made[$key]]);
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            Schema::table('clients', function (Blueprint $t) {
                $t->string('short_code', 16)->change();
                $t->string('letter_code', 16)->nullable()->change();
            });
        }

        $this->triggers($driver, true);
    }

    public function down(): void
    {
        $this->triggers(DB::connection()->getDriverName(), false);
        Schema::table('projects', fn (Blueprint $t) => $t->dropConstrainedForeignId('project_incharge_id'));
        Schema::dropIfExists('project_incharges');
        Schema::table('tax_invoices', fn (Blueprint $t) => $t->dropConstrainedForeignId('gem_contract_id'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('service_charge_includes_gst'));
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn('number_prefix'));
        Schema::table('gem_contracts', fn (Blueprint $t) => $t->dropColumn(['contract_type', 'service_charge_includes_gst']));
    }

    /** Same safety net as harden_agency_isolation, for the new links. */
    private function triggers(string $driver, bool $create): void
    {
        // SQLite rebuilds a table when a foreign key column is added, which drops its triggers: the earlier
        // tax-invoice rule from harden_agency_isolation is therefore (re)created here too. MySQL keeps it anyway.
        $rules = [
            ['tax_invoices', 'agency', 'NEW.agency_id <> (SELECT c.agency_id FROM clients c WHERE c.id = NEW.client_id)',
                'A tax invoice must belong to the same agency as its client'],
            ['tax_invoices', 'workorder', 'NEW.gem_contract_id IS NOT NULL AND NEW.agency_id <> (SELECT g.agency_id FROM gem_contracts g WHERE g.id = NEW.gem_contract_id)',
                'A tax invoice must belong to the same agency as its work order'],
            ['projects', 'incharge', 'NEW.project_incharge_id IS NOT NULL AND (SELECT i.agency_id FROM project_incharges i WHERE i.id = NEW.project_incharge_id) <> (SELECT g.agency_id FROM gem_contracts g WHERE g.id = NEW.gem_contract_id)',
                'A project in-charge must belong to the same agency as the project'],
        ];
        foreach ($rules as [$table, $suffix, $wrong, $message]) {
            foreach (['insert', 'update'] as $event) {
                $name = "{$table}_{$suffix}_{$event}";
                try {
                    if (! $create && $suffix === 'agency') {
                        continue;   // belongs to harden_agency_isolation: keep it on rollback
                    }
                    DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
                    if (! $create) {
                        continue;
                    }
                    if ($driver === 'mysql' || $driver === 'mariadb') {
                        DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($event)." ON {$table} FOR EACH ROW
                            BEGIN IF {$wrong} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END");
                    } elseif ($driver === 'sqlite') {
                        DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($event)." ON {$table} FOR EACH ROW
                            WHEN {$wrong} BEGIN SELECT RAISE(ABORT, '{$message}'); END");
                    }
                } catch (\Throwable $e) {
                    Log::warning("Could not create trigger {$name}: ".$e->getMessage());
                }
            }
        }
    }
};
