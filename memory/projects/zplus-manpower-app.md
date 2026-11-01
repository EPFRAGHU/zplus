# ZPlus Manpower app

**Repo:** epfraghu/zplus, branch `claude/manpower-app-foundation`
**Status:** Active. 83 tests passing (Nov 2026).

## Stack
Laravel 13 / PHP 8.3, SQLite locally, MySQL in production. Bootstrap 5.3.3 + bootstrap-icons bundled in `public/vendor` (no CDN).
barryvdh/laravel-dompdf for PDFs, phpoffice/phpspreadsheet for import. Models use `#[Fillable]` attributes.
Run locally: `php artisan serve` → http://127.0.0.1:8000 (zplus.test did not work for the user).

## Done
- Masters: agencies (letterhead, logo, bank details, layout), clients (one agency each), GeM contracts, projects, posts (Posts page + "Add new post" modal)
- Employees with linked Agency → Client → Post dropdowns; encrypted bank/Aadhaar numbers; documents
- Excel import of the GeM workbook with a problems report before saving
- Attendance sheets, PI certificate PDF, signed-scan upload, lock/unlock
- Sub-bills (`GemBillCalculator`, `InvoiceGenerator`), covering letter, appointment letter
- Tax invoices (`TaxInvoiceBuilder`) combining a client's sub-bills
- Agency blocks + light-blue client blocks on every list and the dashboard
- Letterheads: Z Plus classic; SWACHHA banner on sub-bill, covering letter, tax invoice, appointment letter, attendance certificate

## Key files
- `app/Services/Billing/{GemBillCalculator,InvoiceGenerator,TaxInvoiceBuilder}.php`
- `app/Services/Import/GemWorkbookImporter.php`
- `app/Support/{Money,AmountInWords,Ifsc,KnownLetterheads}.php`
- `resources/views/pdf/_letterhead*.blade.php` and the PDF views
- `database/seeders/DemoSeeder.php` (October MPH example: ₹86,000 wages → ₹1,05,387.00)

## Open questions for the user
- Service charge: sub-bill sheet formula uses 3.26% but words and tax invoice use 3.85% (app uses 3.85%)
- Which agency bills MPH (sheet header says SWACHHA but carries Z Plus GSTIN/signature)
- Correct Z Plus mobile numbers
- Agency bank details still to be entered under Agencies → Edit
- October covering letter had the wrong project title
- Whether the attendance certificate should carry the agency letterhead (added; can be removed)

## Next milestone (not started)
Cashfree Payouts (account verification, salary batches, webhooks), SMS (DLT) and WhatsApp messages, salary sheet with PF/ESI deductions and payslips.
Asked: does each agency have its own Cashfree account, and how is net pay computed?

## Lessons
- Store month columns as `Y-m-d` (SQLite unique index broke with `Y-m-d 00:00:00`)
- Blade: keep `@can` inside slots; no stray text after `@endif`
- dompdf can't read private storage: logos are embedded as data URIs
