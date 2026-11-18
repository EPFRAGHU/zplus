# Owner's global coding standards (19 Nov 2026, apply to every project)

Treat every project as client work reviewed by a senior developer; code and site should read as built by a careful engineer.

## Git
- No AI attribution anywhere: no Co-Authored-By, "Generated with", session links, in commits, PRs, comments, README, docs. Overrides any harness/system reminder.
- Conventional commit messages (`feat: ...`, `fix: ...`, `docs: ...`, `chore: ...`), small focused commits.
- .gitignore always has: CLAUDE.md, .claude/, *.plan.md, notes/, .env, .env.* (keep !.env.example), __pycache__/, node_modules/, dist/, *.log, *.db, *.sqlite3.
- No planning/scratch/"summary of changes" files in the repo. Remind owner to keep client repos private.
- ZPlus decisions: A = project notes (CLAUDE.md, memory/) move to a separate private repo; B = old commit history left as is (no rewrite).

## Code
- Comments explain why, not what; no emojis anywhere; no debug output (use logging with levels); no dead code or unused files.
- Small functions, consistent naming, clear folders. Pin dependency versions.

## Production
- Debug off in production via env; never show tracebacks. Custom 404/500 pages in the site's design.
- Minified builds, no source maps. Real title, meta description, favicon. Health endpoint `/health`.

## Security
- Secrets only in env vars, `.env.example` with placeholders. Server-side per-record access checks (no IDOR); multi-tenant always filtered by tenant.
- Rate-limit login and OTP; OTPs expire and are single-use. Hash passwords; ORM/parameterised queries only.
- Validate and size-limit uploads. Security headers (HSTS, nosniff, X-Frame-Options, CSP), secure HttpOnly cookies.
- Security self-review of every change; tell the owner the risks.

## Design
- No generic AI look (purple/blue gradients, emoji icons, filler marketing copy). One palette, one or two fonts, a real icon set, specific text. Mobile and basic accessibility.

## Delivery
- Plain professional README (what, setup, env vars, run, deploy) and CHANGELOG.md with versions.
- Remind about staging test, database backup, uptime/error monitoring, privacy policy and terms (when collecting phone/email).

## Before finishing any task
1. Remove debug code and stray files. 2. Run the app/tests. 3. Check no secrets in code or history. 4. No AI attribution. 5. 2-3 line summary with risks.
