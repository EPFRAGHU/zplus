# Owner's code review checklist (standing rules)

Given by the owner on 04 Nov 2026. Applies to **this app and all their other apps** (EPFO tools and employer apps:
Python Flask/FastAPI/Streamlit, React/Vite, PostgreSQL/SQLite, Docker on Coolify behind HTTPS).
They handle UAN, member names, wages, bank details, Aadhaar-linked info and payment records:
**security and correctness matter more than speed.**

## Standing rules
1. Review every piece of code (theirs or mine) against this checklist **before calling it done**. Never hand over unchecked code.
2. Never silently change behaviour: if a fix changes how the app works, say so clearly.
3. Simple, proven solutions; well-known libraries; no custom crypto or custom auth.
4. If something needed to judge safety is missing (config, env vars, how a route is called), **ask, don't assume**.
5. Be direct: if code is unsafe, say so plainly.

## Checklist
**Authentication**
- Passwords: argon2 or bcrypt (never plain, MD5, SHA1, unsalted SHA256)
- OTPs: `secrets` module, 6+ digits, expire in 5-10 min, single-use, stored hashed, max ~5 attempts then lock
- Rate limits on login, OTP send/verify, password reset – per IP **and** per account
- No user enumeration (same message for wrong user / wrong password)
- Session cookies HttpOnly, Secure, SameSite Lax/Strict; regenerate on login; logout invalidates server-side
- JWT (if used): short expiry, secret from env, algorithm pinned, safe refresh, not in localStorage
- Admin routes protected on the server, not just hidden in the UI

**Authorization / multi-tenant**
- Every read/write filtered by the user's establishment/tenant; test for IDOR (change an id in URL or body)
- Role checks on the backend for every sensitive action

**Input and data**
- Parameterised SQL / ORM only; no f-strings or concatenation in SQL
- Validate all input (types, length, UAN, PAN, IFSC, dates, amounts) – Pydantic / WTForms / explicit checks
- Uploads (Excel/CSV/PDF): extension + content type, size limit, never trust filenames, store outside web root, malformed files don't crash
- Escape output (no XSS; no `dangerouslySetInnerHTML` with user data)
- CSRF on all state-changing forms

**Secrets and config**
- No hardcoded passwords, API keys, DB URLs, SMTP/SMS/payment keys – env vars only
- DEBUG off in production; no stack traces to users
- CORS limited to real domains, never `*`
- Security headers: HSTS, X-Content-Type-Options, X-Frame-Options or CSP

**Robustness**
- Specific error handling, no bare `except:`; friendly message to user, detail in logs
- Logs never contain passwords, OTPs, tokens, or full bank/Aadhaar numbers
- DB: pooled/closed connections, transactions for multi-step writes, no N+1, indexes on WHERE/JOIN columns, pagination
- Money, interest, damages: Decimal (or integer paise), never float; explicit rounding rules
- External calls (SMS, email, payment gateway, EPFO portals): timeouts, safe retries, clear failure handling; webhooks verify signatures and are idempotent
- Dates/timezones consistent (Asia/Kolkata)

**Deployment**
- Dockerfile: slim pinned base image, non-root, health check
- Dependencies pinned (requirements.txt / package.json); flag outdated or vulnerable ones
- Certificates / CA bundles (certifi) up to date
- Database backups for anything with real data

## Output format for every review
1. **Verdict** (one line): Safe to deploy / Fix before deploy / Unsafe
2. **Findings** by severity – 🔴 Critical (security hole, data leak, data loss) · 🟠 High (likely prod bug/crash) · 🟡 Medium (robustness, performance) · 🔵 Low (style). Each: file/line, what's wrong, why it matters, exact fix
3. **Fixed code** – only the changed parts (full file if asked)
4. **What I should test** – short manual checklist

If the code is fine, say so briefly. **Don't invent problems.**

## In this Laravel app (ZPlus) these map to
bcrypt via `'password' => 'hashed'` cast · `throttle:6,1` + per-email `RateLimiter` in `AuthController` · session cookie config + `NoStoreWhenSignedIn`/`AuthenticateSession` ·
`ScopedToAgency`/`AgencyScope`, `GuardAgencyInput`, `ExistsInMyAgency`, DB triggers, `CrossAgencyWriteTest` · Gates in `AppServiceProvider` ·
Blade `{{ }}` (any `{!! !!}` must be static or `e()`) · `@csrf` · `SecurityHeaders` middleware · `App\Support\Money` (integer paise) ·
`Activity::log` masks secrets · `docker-compose.yaml` healthcheck, `APP_DEBUG=false`. No OTP/JWT/CORS/external calls yet – check them when added (SMS, Cashfree).
Last full review: v2.3.2 (04 Nov 2026) – no Critical/High; images still unpinned (Low, offered).
