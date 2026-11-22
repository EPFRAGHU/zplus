<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ADDITIVE ONLY (owner, 22 Nov 2026): payments received from the client. One receipt per bank credit / cheque
 * (UTR, cheque, dates, account credited, payment advice) split over the sub-bills it pays, with the client's
 * deductions per sub-bill (income-tax TDS 2%, GST TDS 1% CGST + 1% SGST or 2% IGST, other) and the net received.
 * Existing tables and figures are not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->foreignId('client_id')->constrained()->restrictOnDelete();
            $t->foreignId('gem_contract_id')->constrained()->restrictOnDelete();
            $t->string('mode', 10);                              // neft | rtgs | cheque | dd | other
            $t->string('utr_no', 40)->nullable();                // NEFT / RTGS UTR
            $t->date('transfer_date')->nullable();               // NEFT / RTGS date
            $t->string('cheque_no', 20)->nullable();             // cheque / DD number
            $t->date('cheque_date')->nullable();
            $t->string('cheque_bank', 120)->nullable();          // bank the cheque is drawn on
            $t->date('credit_date');                             // date credited in our bank (bank statement)
            $t->string('bank_account', 120)->nullable();         // our account credited
            $t->decimal('amount', 15, 2);                        // net amount credited
            $t->text('remarks')->nullable();
            $t->string('advice_path')->nullable();               // client's payment advice (PDF / image)
            $t->string('advice_name')->nullable();
            $t->string('status', 12)->default('pending');        // pending | approved | returned
            $t->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->string('return_note', 500)->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'credit_date']);
            $t->index(['gem_contract_id', 'status']);
        });

        Schema::create('payment_receipt_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->foreignId('payment_receipt_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained()->restrictOnDelete();   // the sub-bill paid
            $t->decimal('bill_amount', 15, 2);                   // sub-bill total (rounded) when entered
            $t->decimal('taxable', 15, 2);                       // sub-bill value before GST when entered
            $t->decimal('tds_it', 15, 2)->default(0);            // income-tax TDS (194C)
            $t->decimal('tds_cgst', 15, 2)->default(0);          // GST TDS (section 51)
            $t->decimal('tds_sgst', 15, 2)->default(0);
            $t->decimal('tds_igst', 15, 2)->default(0);
            $t->decimal('other_deduction', 15, 2)->default(0);
            $t->string('other_reason', 200)->nullable();
            $t->decimal('received', 15, 2);                      // net received for this sub-bill
            $t->boolean('form16a_seen')->default(false);         // TDS seen in Form 16A / 26AS
            $t->boolean('gstr7a_accepted')->default(false);      // GST TDS accepted (GSTR-7A)
            $t->timestamps();
            $t->unique(['payment_receipt_id', 'invoice_id']);
            $t->index('invoice_id');
        });

        // Safety net like the other agency-owned tables: a receipt and its lines stay inside one agency.
        $rules = [
            'payment_receipts' => 'NEW.agency_id <> (SELECT g.agency_id FROM gem_contracts g WHERE g.id = NEW.gem_contract_id)',
            'payment_receipt_lines' => 'NEW.agency_id <> (SELECT r.agency_id FROM payment_receipts r WHERE r.id = NEW.payment_receipt_id) OR NEW.agency_id <> COALESCE((SELECT i.agency_id FROM invoices i WHERE i.id = NEW.invoice_id), NEW.agency_id)',
        ];
        $driver = DB::connection()->getDriverName();
        foreach ($rules as $table => $wrong) {
            foreach (['insert', 'update'] as $event) {
                $name = "{$table}_agency_{$event}";
                try {
                    if ($driver === 'mysql' || $driver === 'mariadb') {
                        DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
                        DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($event)." ON {$table} FOR EACH ROW
                            BEGIN IF {$wrong} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A payment must stay inside one agency'; END IF; END");
                    } elseif ($driver === 'sqlite') {
                        DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
                        DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($event)." ON {$table} FOR EACH ROW
                            WHEN {$wrong} BEGIN SELECT RAISE(ABORT, 'A payment must stay inside one agency'); END");
                    }
                } catch (\Throwable $e) {
                    Log::warning("Could not create trigger {$name}: ".$e->getMessage());   // the app-level checks still apply
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['payment_receipts', 'payment_receipt_lines'] as $table) {
            foreach (['insert', 'update'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_agency_{$event}");
            }
        }
        Schema::dropIfExists('payment_receipt_lines');
        Schema::dropIfExists('payment_receipts');
    }
};
