# Glossary

## Organisations
| Term | Meaning |
|------|---------|
| ZPS, Z Plus, ZPLUS | M/S Z Plus Surakhya Seva (agency code ZPS) |
| SWH, SWACHHA | SWACHHA (agency code SWH) |
| ICMR, NIHR, RMRCBB | ICMR-NIHR (RMRCBB), Bhubaneswar, client (short code ICMR, letter code NIHR) |
| GOI | Government of India |

## Billing and compliance
| Term | Meaning |
|------|---------|
| GeM | Government e-Marketplace (contracts `GEMC-…`, bids `GEM/2026/B/…`) |
| SAC / HSN | Service code on the tax invoice; default 998514 |
| GSTIN | 15-char GST number; last char is a mod-36 check character |
| CGST/SGST/IGST | Intra-state 9% + 9%, or inter-state 18% |
| EPF / ESIC | Employer contributions 13% / 3.25%, added for daily-rate posts (MTS, DEO) |
| HRA | House rent allowance: zero in their sheets, not implemented |
| Sub-bill | Per-project monthly "BILL" PDF; numbered CLIENT/PROJECT/NN/YEAR |
| Tax invoice / TI | Client-level invoice combining sub-bills; numbered CLIENT/TI/NN/YEAR (editable) |
| Covering letter | Letter to ICMR submitting a sub-bill |
| Appointment letter | Per employee, numbered like SWH/NIHR/MPH/01/26 |
| Attendance certificate | "Monthly Attendance and Working Certificate", signed by the PI |
| Locked month | Attendance sheet locked after signed scan upload; bills only from locked months |

## Attendance codes
P present, CL casual leave, EL earned leave, RH restricted holiday, CH compensatory, T tour/training, H holiday, SAT/SUN weekly off, HD half day, A absent (unpaid), L leave without pay.

## Posts and projects
| Term | Meaning |
|------|---------|
| MPH | MPH Academic Programme (PI Dr. S. Kanungo) – the October 2026 example bill |
| APM / APA | Academic Programme Manager (₹55,000) / Assistant (₹31,000) |
| MTS, DEO | Multi-tasking staff, data entry operator: daily-rate posts |

## Source files the user shared (not in repo)
| File | What |
|------|------|
| GEM_PROJECT_DETAILS.xlsx | Master workbook: agency sheets + PI sheets; imported via Excel import |
| OCT-2026.xlsx | Sub-bills per project |
| ICMR_TAX_INVOICE.xlsx | Tax invoice combining sub-bills |
| ICMR.docx | Covering letter |
| Apponiment_Letter.docx | SWACHHA appointment letter; source of SWACHHA letterhead |
| REQUEST_LETTER_FOR_EXTENSION.pdf | Source of Z Plus letterhead |
| attendance PDF | PI attendance certificate format |

## Deployment and app terms
| Term | Meaning |
|------|---------|
| Coolify | Self-hosted deploy panel on the Hostinger VPS (port 8000); deploys the app from GitHub |
| Traefik / coolify-proxy | Coolify's proxy holding ports 80/443, issues Let's Encrypt certificates |
| Redeploy | Coolify button that rebuilds and restarts the app from the latest code |
| zplus.epf-dashboard.online | Live address of the app |
| annexure | Owner's other app on the same VPS (EPFO Annexure-A extractor, FastAPI) |
| Release notes | `/releases` page; version history in `config/releases.php` |
| Salary payout sheet | Bank transfer sheet per locked month (net pay after EPF/ESIC) |
| EPF / ESIC switch | Per-employee on/off controlling deductions and employer contributions on bills |
| Super Admin | raghu.epf@gmail.com: the owner's account; only one that sees the Activity log; move with `php artisan users:super-admin <email>` |
| Agency login | A login linked to one agency (ZPS or SWH) sees only that agency's data; "Both agencies" logins only by the Super Admin |
| Activity log | Setup → Activity log: every add/edit/delete/status, sensitive view/download and sign-in |
| Data Upload | Setup → Data Upload: Excel templates for masters |
