<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Database-level safety net for agency isolation: triggers refuse any row that links records of two different
 * agencies (even if the application had a bug). Also ends all "keep me signed in" cookies, which are removed.
 */
return new class extends Migration
{
    /** table => [trigger suffix, condition that is true when the row is wrong] */
    private function rules(): array
    {
        return [
            'gem_contracts' => ['agency', 'NEW.agency_id <> (SELECT c.agency_id FROM clients c WHERE c.id = NEW.client_id)',
                'A GeM contract must belong to the same agency as its client'],
            'invoices' => ['agency', 'NEW.agency_id IS NOT NULL AND NEW.agency_id <> (SELECT g.agency_id FROM gem_contracts g WHERE g.id = NEW.gem_contract_id)',
                'A sub-bill must belong to the same agency as its GeM contract'],
            'tax_invoices' => ['agency', 'NEW.agency_id <> (SELECT c.agency_id FROM clients c WHERE c.id = NEW.client_id)',
                'A tax invoice must belong to the same agency as its client'],
            'employees' => ['agency', 'NEW.client_id IS NOT NULL AND NEW.agency_id IS NOT NULL AND NEW.agency_id <> (SELECT c.agency_id FROM clients c WHERE c.id = NEW.client_id)',
                'An employee must belong to the same agency as their client'],
        ];
    }

    public function up(): void
    {
        DB::table('users')->update(['remember_token' => null]);   // old "keep me signed in" cookies stop working

        $driver = DB::connection()->getDriverName();
        foreach ($this->rules() as $table => [$suffix, $wrong, $message]) {
            foreach (['insert', 'update'] as $event) {
                $name = "{$table}_{$suffix}_{$event}";
                try {
                    if ($driver === 'mysql' || $driver === 'mariadb') {
                        DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
                        DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($event)." ON {$table} FOR EACH ROW
                            BEGIN IF {$wrong} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END");
                    } elseif ($driver === 'sqlite') {
                        DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
                        DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($event)." ON {$table} FOR EACH ROW
                            WHEN {$wrong} BEGIN SELECT RAISE(ABORT, '{$message}'); END");
                    }
                } catch (\Throwable $e) {
                    // e.g. MySQL with binary logging and no trigger permission: the app-level checks still apply.
                    Log::warning("Could not create trigger {$name}: ".$e->getMessage());
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->rules() as $table => [$suffix]) {
            foreach (['insert', 'update'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_{$suffix}_{$event}");
            }
        }
    }
};
