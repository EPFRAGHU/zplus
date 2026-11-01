<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Months were saved as "2026-10-01 00:00:00" on SQLite, so lookups by "2026-10-01" missed them
 * (the Attendance page showed "Not started" and starting again hit the unique index).
 * Rewrite them as plain dates. MySQL DATE columns already hold plain dates, so this is a no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['attendance_sheets', 'invoices'] as $table) {
            DB::table($table)->where('month', 'like', '% %')->orderBy('id')->each(function ($row) use ($table) {
                DB::table($table)->where('id', $row->id)->update(['month' => substr((string) $row->month, 0, 10)]);
            });
        }
    }

    public function down(): void
    {
        // Nothing to undo: plain dates are the correct format.
    }
};
