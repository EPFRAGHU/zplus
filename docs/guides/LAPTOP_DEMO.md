# Live data on the laptop + October billing demo

A copy of the live database on your Windows laptop, then a demo that fills October with full attendance for every
work order, makes one sub-bill per project (`SW/001/26-27(HTA)` …) and one tax invoice per work order that clubs
them (same post across projects = one row).

**The live site is never touched by the demo.** `php artisan demo:full-month` refuses to run when `APP_ENV=production`
or when `APP_URL` is the live address. The copy lives only on your laptop: keep the `.sql` file **outside** the
`zplus` folder so it can never be committed, and never send it anywhere.

---

## 1. Take a copy of the live database (VPS)

Open the VPS terminal (Hostinger browser terminal, or `ssh root@<your VPS IP>` from PowerShell) and run:

```bash
DB=$(docker ps --format '{{.Names}}' | grep '^db-ppc5ypfm2aogcq4hfie2kwhv' | head -1)
docker exec "$DB" sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --routines --triggers zplus' \
  | sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g' > ~/zplus-laptop-copy.sql
tail -1 ~/zplus-laptop-copy.sql
```

The last line must say **`-- Dump completed`**. (The `sed` part removes the live database user from the safety
triggers so they also work on the laptop.)

## 2. Download it to the laptop

In **PowerShell** on the laptop (Windows has `scp` built in):

```powershell
mkdir C:\zplus-backups -Force
scp root@<your VPS IP>:~/zplus-laptop-copy.sql C:\zplus-backups\
```

Then delete it from the VPS (it holds staff data): in the VPS terminal `rm ~/zplus-laptop-copy.sql`.

## 3. Load it into a separate laptop database

Use a **new** database so your normal laptop data stays as it is. In **Command Prompt** (or the MySQL console of
XAMPP / Laragon):

```bat
mysql -u root -p -e "CREATE DATABASE zplus_live_copy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p zplus_live_copy -e "source C:/zplus-backups/zplus-laptop-copy.sql"
```

## 4. Point the laptop app at the copy

Edit `.env` in the laptop's `zplus` folder (keep a copy of the old `.env` first, e.g. `.env.mine`):

```ini
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_KEY=        # paste the live site's APP_KEY from Coolify → Environment Variables (never in chat or e-mail)
DB_CONNECTION=mysql
DB_DATABASE=zplus_live_copy
MAIL_MAILER=log
AGENCY_MAIL_SWH_USERNAME=
AGENCY_MAIL_SWH_PASSWORD=
AGENCY_MAIL_ZPS_USERNAME=
AGENCY_MAIL_ZPS_PASSWORD=
```

- The **same APP_KEY** as live is needed, or bank account and Aadhaar numbers cannot be opened.
- `MAIL_MAILER=log` and the blank agency mail settings mean **no e-mail can go to ICMR** from the laptop.

Then:

```bat
git pull
php artisan migrate
php artisan optimize:clear
```

## 5. Run the October demo

```bat
php artisan demo:full-month 2026-10
```

It shows which database it will use, then asks you to type **DEMO**. It then, for every work order:

1. gives every active employee **full attendance** for October (Sundays / Saturdays / holidays as on a normal sheet;
   someone who joined in the middle of the month is paid from the joining date);
2. locks the month and makes a **sub-bill per project** (`SW/001/26-27(HTA)`, `SW/001/26-27(NTM)` …);
3. makes **one tax invoice per work order** with all its sub-bills (`SW/001/26-27`), the same post across projects
   merged into one row;
4. marks the approvals as done, labelled **"(demo)"**, so every PDF opens straight away.

At the end it prints a table: work order, project, staff, bill number, amount, result. Projects whose October is
already locked or billed are left exactly as they are; projects with no staff are skipped.

Options: `--contract=GEMC-511687731718030` (one work order only), `--user=you@example.com` (who the bills are made by).

## 6. Look at the result

```bat
php artisan serve
```

Open http://127.0.0.1:8000 and sign in with your **live** e-mail and password (the copy has the same logins).
Open **Sub-bills** and **Tax Invoices** → October 2026, or a work order page → October.

## 7. Start again / go back to normal

- **Start the demo again:** repeat step 3 with a fresh database (drop `zplus_live_copy` and load the file again).
- **Back to your normal laptop data:** put your old `.env` back (`.env.mine`).
- When done, delete `C:\zplus-backups\zplus-laptop-copy.sql` if you no longer need it.
