# ZPlus Manpower

Manpower and client management for **M/S Z Plus Surakhya Seva** and **SWACHHA**: GeM contracts with ICMR-NIHR, employees, PI attendance certificates, monthly GeM bills, covering letters and appointment letters.

Built with Laravel 13 (PHP 8.3), Bootstrap 5 and MySQL (SQLite for local use).

## What it does today

| Area | What you can do |
|---|---|
| **Masters** | Agencies (SWACHHA, Z Plus), clients (ICMR-NIHR), GeM contracts → projects (with PI) → sanctioned posts and rates. Vacancies show per post. |
| **Employees** | Basic, work, bank and KYC details. Bank account and Aadhaar numbers are stored encrypted and never sent back to the browser. Document uploads with expiry dates. |
| **Excel import** | Upload the *GEM PROJECT DETAILS* workbook. It reads the agency sheets (contracts, posts, candidates) and the PI sheets (salary, bank details), matches staff to posts, and lists problems such as missing or invalid IFSC codes, salary mismatches and names spelt differently between sheets, before saving anything. |
| **Attendance** | One sheet per project per month with the PI codes (P, CL, EL, RH, CH, T, H, SAT, SUN, HD, A, L). Prints the *Monthly Attendance and Working Certificate* with bank details. Upload the signed scan, then lock the month. |
| **Bills** | Made only from a locked month. Numbered `ICMR/MPH/01/2026`. PDF bill and covering letter with the amount in words. Status: Draft → Approved → Submitted → Paid. |
| **Salary payouts** | Bank transfer sheet from a locked month on the agency letterhead: earned salary, employee EPF 12% (on wages up to ₹15,000) and ESIC 0.75% for staff with them switched on, net pay and bank details. Admin and Accounts only. |
| **Letters** | Appointment letter in the SWACHHA format, numbered once per employee (`SWH/NIHR/MPH/01/26`). |

### How a bill is calculated

Confirmed rule: **service charge is levied on the total excluding GST; GST is then charged on the total plus service charge.**

- Monthly posts: amount = rate × paid days ÷ days in month
- Daily-rate posts (MTS, DEO): amount = rate × paid days
- EPF and ESIC are switched on or off per employee (Employees list or employee form). For staff with them on, the bill adds the employer's EPF (13% of wages up to ₹15,000) and ESIC (3.25%) before the service charge, and the salary sheet deducts the employee's 12% and 0.75%
- GST is CGST + SGST, or IGST when the contract is marked inter-state

All money is calculated in whole paise, never in floating point. The October 2026 MPH bill (₹86,000 of wages) comes to **₹1,05,387.00**, the same as the bill that was submitted. `tests/Unit/Billing/GemBillCalculatorTest.php` checks this.

### Roles

| Role | Can |
|---|---|
| Admin | Everything, including Excel import and unlocking a locked month |
| Accounts | Masters, bills, approve bills, see bank details |
| Supervisor | Attendance, and view everything else |

## Run it on your computer

Needs PHP 8.3+ with the `sqlite3`, `mbstring`, `gd`, `zip` and `intl` extensions, and Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
ADMIN_EMAIL=you@example.com ADMIN_PASSWORD=choose-a-password php artisan db:seed
php artisan db:seed --class=DemoSeeder   # optional: the October MPH example
php artisan serve
```

Open http://127.0.0.1:8000 and sign in.

## Deploy to a server

Step-by-step guide for an Ubuntu VPS with a subdomain and HTTPS: [docs/DEPLOY_VPS.md](docs/DEPLOY_VPS.md). Summary:


1. Create a MySQL 8 database. In `.env` set `DB_CONNECTION=mysql` with the host, database, user and password, and set `APP_ENV=production`, `APP_DEBUG=false` and `APP_URL`.
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan key:generate` (first time only). **Keep `APP_KEY` safe and never change it**: the encrypted bank and Aadhaar numbers cannot be read without it.
4. `php artisan migrate --force`, then `php artisan db:seed --force` to create the first admin.
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
6. Point the web server at `public/`. Uploaded certificates and documents are saved under `storage/app/private`, which must be writable and backed up.

## Tests

```bash
php artisan test
```

## Roadmap

- **Milestone 2: payments and messages**
  - Cashfree Payouts: bank account verification, salary batches from the locked month, transfer status by webhook
  - SMS (DLT templates) and WhatsApp Business: salary-credited, payout-failed and bill-sent messages, falling back to SMS
- Payslips per employee
- Client payment tracking and reminders
- User management screen (users are created by the seeder for now)
