# Memory

Project memory for Claude Code. Read this first; details are in `memory/`.

## Me
Owner/operator of the manpower business behind **M/S Z Plus Surakhya Seva** and **SWACHHA**, building this app to replace manual GeM billing done in Excel/Word.

## Business
| Who | What |
|-----|------|
| **ZPS** / Z Plus | M/S Z Plus Surakhya Seva, manpower & security agency, GSTIN 21AAAFZ5965B1ZD |
| **SWH** / SWACHHA | Manpower agency "Since-1972", GSTIN 21AADAS8034Q1Z6 (printed letterhead has a typo: 21AAADAS…) |
| **ICMR** / NIHR / RMRCBB | ICMR-NIHR (RMRCBB), Bhubaneswar: the client both agencies supply staff to |
| **PI** | Project In-charge of a project (NOT "Principal Investigator"); signs the monthly attendance certificate |
→ More: memory/context/business.md, memory/glossary.md

## Terms
| Term | Meaning |
|------|---------|
| **GeM** | Government e-Marketplace; each contract = GeM bid + contract no. |
| **Project** | A PI's programme under a contract, e.g. **MPH** (MPH Academic Programme) |
| **Post** | Sanctioned designation + rate under a project |
| **Work order** | UI name for GeM contract (`gem_contracts`), Online/Offline; page shows projects → sub-bills → tax invoice |
| **Sub-bill** | Monthly bill per project (`SW/001/26-27(HTA)`; older: `ICMR/MPH/01/2026`), model `Invoice` |
| **Tax invoice** | Combines a **work order's** sub-bills for a month (`SW/001/26-27`, same serial; `App\Services\Billing\BillNumbers`). PDF in the agencies' own format (text header, posts of every project, EPF 12/EDLI 0.5/admin 0.5, ESI); figures worked out once on the combined sub-total (`TaxInvoiceBuilder::totals/particulars`) |
| **Project In-charge** (menu; was PI Allotment) | `project_incharges` master (agency-scoped) linked to projects; page shows PI → projects → employees → sub-bills; Employee IDs SW-1001 / ZP-1001 |
| **Service charge** | 3.85% **includes GST**: charged as (total × 3.85%) ÷ 1.18 = 3.26% on the total excluding GST (v2.4.0, flag on work order + stored per bill; older bills: plain 3.85%); then GST 18% (CGST 9 + SGST 9, or IGST) |
| **EPF / ESIC / TDS switches** | Per employee (`pf_applicable`, `esic_applicable`, `tds_applicable` – TDS on by default). On → bill adds employer EPF 13% (wages capped ₹15,000) / ESIC 3.25%; salary sheet deducts 12% / 0.75% |
| **Salary payout sheet** | Bank transfer sheet per locked month; net = earned − EPF − ESIC (for staff switched on) − **TDS 2%** for staff with TDS on (`Statutory::TDS_PERCENT`, rounded to the rupee, v2.9.0; switch v2.9.2); same net in bank letters and Employee Management |
| **PI portal** | `/pi`, role `pi` linked to projects (`project_user`). Draft → Submitted (PDF stored) → operator corrections recorded in `attendance_changes` → Approved/locked → sub-bill auto-made |
| **Activity log** | `/activity`, Super Admin only (`users.is_super_admin`, gate `view-activity`). Models with trait `LogsActivity` log add/edit/delete/status; `LogAccess` middleware logs views/downloads; `App\Support\Activity\Activity::log()` for anything else. Secrets masked. Entries immutable |
| **Agency logins** | `users.agency_id` (null = both agencies). Trait `ScopedToAgency` (global `AgencyScope`) on Agency, Client, GemContract, Project, Post, Employee, EmployeeDocument, AttendanceSheet, Invoice, TaxInvoice, ProjectIncharge; `GuardAgencyInput` middleware 404s other-agency ids in forms; `AgencyContext::unscoped()` for cross-agency uniqueness checks. New agency-owned models/inputs must be added to both. Validate ids with `new ExistsInMyAgency(Model::class)`, never `exists:`; DB triggers (migration `harden_agency_isolation`) refuse cross-agency links. Every new write route must be added to `tests/Feature/CrossAgencyWriteTest.php` |
| **Data Upload** | Setup → Data Upload: Excel templates per master, upload → check → save (`app/Services/MasterImport`) |
| **Letterhead layout** | `classic` (ZPS, centred + footer) or `banner` (SWH, logo/registrations/pink address box) |
→ Full list: memory/glossary.md

## Projects
| Name | What |
|------|------|
| **ZPlus Manpower app** | This repo. Laravel 13 app: masters, employees, attendance, sub-bills, tax invoices, letters. Branch `claude/manpower-app-foundation` |
→ Details, status and open questions: memory/projects/zplus-manpower-app.md

## Preferences (how to work on this)
- **PRODUCTION-GRADE APP, LIVE WITH REAL DATA (owner, 17 Nov 2026): be careful with every code change.**
  - Every push to the branch auto-deploys to https://zplus.epf-dashboard.online and briefly restarts the database: push only finished, tested work; never while the owner is backing up or working on bills; memory-only pushes too
  - Database changes ADDITIVE ONLY (new tables/columns, safe defaults); never drop, rename or rewrite existing data; old bills keep their figures unless the owner asks
  - Before anything that changes or deletes real records (recalculate, reset, renumber, delete), tell the owner to take a backup first and check it ends with "-- Dump completed"
  - Destructive actions: Admin only, refuse paid/locked data, confirm, record in the Activity log
  - Read the code before changing it; keep changes small; run the full `php artisan test`; add tests for the change and for cross-agency safety; render and look at PDFs
  - Say plainly when a change alters amounts, numbers or behaviour; when unsure what the owner means, ask before building
- **Review every change against the owner's checklist before calling it done** and report in its format (Verdict · Critical/High/Medium/Low findings with fixes · fixed code · what to test). Don't invent problems; say plainly if unsafe; say when a fix changes behaviour; ask if config is missing → memory/context/review-checklist.md
- Agency and client are shown on every page and PDF; lists are grouped into dark **agency blocks** with light-blue **client blocks** (`<x-client-block>`)
- One agency per client record: a client served by both agencies is added twice
- Each agency's PDFs use its own letterhead, EXCEPT sub-bills and tax invoices: owner's own text-header formats (SWACHHA samples, 16 Nov 2026) for both agencies; numbers (EPF, ESIC, CL&RA) read from the agency's Registrations text (`App\Support\AgencyDetails`)
- Never commit real staff data (names, PAN, Aadhaar, bank numbers) or the real workbooks; agency bank details are entered in the app, not in code
- API keys go in `.env` by the user, never in chat or code
- Bill money is EXACT (brick/math `App\Support\Exact`, like Excel): no rounding of any figure, shown to 2 decimals; only the grand total is rounded ("Rounding off"). Salary payout sheet / bank letter: whole rupees per person (owner, 16 Nov 2026)
- Run `php artisan test` before committing; render PDFs and look at them when changing PDF views
- After pushing, tell the user: `git pull`, `php artisan migrate`, `php artisan serve`
- **Every deployed change gets a new version**: add an entry at the top of `config/releases.php` (MAJOR milestone / MINOR feature / PATCH fix, with date and notes). It shows in the sidebar and on /releases
- The owner often **discusses ideas first** ("just asking", "don't change anything"): explain and preview only; build or change code only when explicitly told to proceed
- Shared computers: signed-in responses `no-store` (`NoStoreWhenSignedIn`), sign-out sends `Clear-Site-Data`, no remember-me, `AuthenticateSession` ends other sessions on password change
- No pull request unless asked
- Production: Hostinger VPS with Coolify (Docker Compose), https://zplus.epf-dashboard.online – see docs/DEPLOY_COOLIFY.md
