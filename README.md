# ZPlus Manpower

Web application for two manpower agencies, M/S Z Plus Surakhya Seva and SWACHHA, that supply staff to government clients under GeM (Government e-Marketplace) contracts. It replaces monthly billing done in Excel and Word: staff records, attendance certified by each project in-charge, sub-bills and tax invoices in the agencies' formats, salary payout sheets, bank letters and owner approval of all of them.

Laravel 13 (PHP 8.3), Bootstrap 5, MySQL 8 in production and SQLite for local development. PDFs are produced with DomPDF and Excel files with PhpSpreadsheet.

## Features

| Area | Summary |
|---|---|
| Masters | Agencies, clients, work orders (GeM contracts), projects, sanctioned posts and rates. Posts can be read from a GeM contract PDF. |
| Employees | Personal, post, bank and KYC details with document uploads. Bank account and Aadhaar numbers are encrypted at rest; PAN is masked for roles that do not need it. Per-employee EPF, ESIC and TDS switches. |
| Attendance | One sheet per project and month. Project in-charges mark and submit it in their own portal; operator corrections are recorded; the signed certificate is uploaded before the month is locked. |
| Billing | A sub-bill per project and month (`SW/001/26-27(HTA)`) and a tax invoice per work order and month (`SW/001/26-27`). Amounts are exact decimals; only the grand total is rounded. Service charge 3.85% including GST, then CGST + SGST or IGST at 18%. |
| Payroll | Salary payout sheet and SBI/NEFT bank letter per agency and month: earned wages less employee EPF 12%, ESIC 0.75% and TDS 2%, in whole rupees. |
| Approvals | Maker-checker: attendance, sub-bills, tax invoices, salary payouts, new staff and changes to post, rate, bank and statutory switches wait for a Super Admin, who can never approve their own submission. Makers see what is waiting and what was returned. |
| Monthly papers | Salary proof, EPF and ESIC challans, logbook and feedback per work order and month; one ZIP bill pack for submission on GeM. |
| E-mail | Sends bill PDFs (the signed copy if uploaded) to the client from the agency's Gmail account. |
| Activity log | Every add, edit, delete, status change, sensitive view, download and sign-in, with old and new values. Entries cannot be changed. |
| Data upload | Excel templates per master; each row is checked and shown as New, Update, No change or Error before saving. |

## Access model

Every agency-owned record is filtered by agency on the server (`App\Support\Agency\AgencyScope`), form input is checked against the user's agency (`GuardAgencyInput`, `ExistsInMyAgency`) and database triggers refuse cross-agency links. `tests/Feature/CrossAgencyWriteTest.php` attempts every write route against the other agency.

| Login | Can |
|---|---|
| Maker (Accounts or Operator, one agency) | Enter and send data for their own agency only |
| Super Admin (both agencies) | Approve, return and delete unused records. Chooses SWACHHA, Z Plus or both after signing in |
| Developer (one Super Admin) | Additionally: users, making Super Admins, activity log, data upload, GeM import |
| Project in-charge (PI) | PI portal only: attendance and sub-bills of their own projects |

## Requirements

- PHP 8.3 with `pdo_mysql` or `pdo_sqlite`, `mbstring`, `gd`, `zip`, `intl`, `bcmath`
- Composer 2
- MySQL 8 for production

## Environment variables

Copy `.env.example` to `.env`. The values that matter:

| Variable | Purpose |
|---|---|
| `APP_ENV`, `APP_DEBUG` | `production` and `false` on the server |
| `APP_URL` | Public address; the app answers only for this host |
| `APP_KEY` | Encryption key. Generate once and never change it: encrypted bank and Aadhaar numbers cannot be read without it |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database |
| `SESSION_SECURE_COOKIE` | `true` behind HTTPS |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | First admin, used once by `php artisan db:seed` |
| `AGENCY_MAIL_SWH_USERNAME`, `AGENCY_MAIL_SWH_PASSWORD`, `AGENCY_MAIL_ZPS_USERNAME`, `AGENCY_MAIL_ZPS_PASSWORD` | Gmail address and app password per agency for e-mailing bills. Leave blank to switch e-mail off |

Secrets are set only in the server environment (Coolify), never in the repository.

## Run locally

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
ADMIN_EMAIL=you@example.com ADMIN_PASSWORD=choose-a-password php artisan db:seed
php artisan serve
```

Open http://127.0.0.1:8000.

## Tests

```bash
php artisan test
```

## Deploy

Production runs on a VPS with Coolify (Docker Compose); every push to the deployment branch is built and deployed. See [docs/DEPLOY_COOLIFY.md](docs/DEPLOY_COOLIFY.md), or [docs/DEPLOY_VPS.md](docs/DEPLOY_VPS.md) for a plain Ubuntu server.

- Database changes are additive only (new tables and columns with safe defaults).
- Take a database backup before any release that changes or recalculates records.
- Health checks: `/up` (application) and `/health` (application and database).

## Versions

Every deployed change gets a version in `config/releases.php`, shown in the sidebar and at `/releases`. `CHANGELOG.md` is generated from it:

```bash
php artisan releases:changelog
```
