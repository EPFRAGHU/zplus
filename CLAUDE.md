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
| **PI** | Principal Investigator of a project; signs the monthly attendance certificate |
→ More: memory/context/business.md, memory/glossary.md

## Terms
| Term | Meaning |
|------|---------|
| **GeM** | Government e-Marketplace; each contract = GeM bid + contract no. |
| **Project** | A PI's programme under a contract, e.g. **MPH** (MPH Academic Programme) |
| **Post** | Sanctioned designation + rate under a project |
| **Sub-bill** | Monthly bill per project (`ICMR/MPH/01/2026`), model `Invoice` |
| **Tax invoice** | Combines a client's sub-bills for a month (`ICMR/TI/01/2026`) |
| **Service charge** | 3.85% on the total **excluding GST**; then GST 18% (CGST 9 + SGST 9, or IGST) |
| **Letterhead layout** | `classic` (ZPS, centred + footer) or `banner` (SWH, logo/registrations/pink address box) |
→ Full list: memory/glossary.md

## Projects
| Name | What |
|------|------|
| **ZPlus Manpower app** | This repo. Laravel 13 app: masters, employees, attendance, sub-bills, tax invoices, letters. Branch `claude/manpower-app-foundation` |
→ Details, status and open questions: memory/projects/zplus-manpower-app.md

## Preferences (how to work on this)
- Agency and client are shown on every page and PDF; lists are grouped into dark **agency blocks** with light-blue **client blocks** (`<x-client-block>`)
- One agency per client record: a client served by both agencies is added twice
- Each agency's PDFs use its own letterhead
- Never commit real staff data (names, PAN, Aadhaar, bank numbers) or the real workbooks; agency bank details are entered in the app, not in code
- API keys go in `.env` by the user, never in chat or code
- Money is integer paise; each person's amount rounds to whole rupees
- Run `php artisan test` before committing; render PDFs and look at them when changing PDF views
- After pushing, tell the user: `git pull`, `php artisan migrate`, `php artisan serve`
- No pull request unless asked
