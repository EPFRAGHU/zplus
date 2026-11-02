# Running ZPlus Manpower on a VPS with a subdomain

> **Your Hostinger VPS runs Coolify. Use [DEPLOY_COOLIFY.md](DEPLOY_COOLIFY.md) instead.** This guide is for a plain server without Coolify or Docker.

Written for **Ubuntu 22.04 or 24.04** with root or sudo access. Replace these everywhere:

| Placeholder | Example |
|---|---|
| `billing.example.com` | the subdomain you choose, e.g. `billing.zplussurakhyaseva.com` |
| `YOUR_VPS_IP` | your VPS's public IP address |
| `choose-a-strong-password` | a new password for the database user |

## 1. Point the subdomain at the VPS

Wherever your domain's DNS is managed (GoDaddy, Hostinger, Cloudflare, BigRock, …), add one record:

| Type | Name / Host | Value | TTL |
|---|---|---|---|
| A | `billing` | `YOUR_VPS_IP` | Auto / 3600 |

On Cloudflare, set it to **DNS only** (grey cloud) until HTTPS is working.
Check it from your laptop: `ping billing.example.com` should show your VPS IP (can take 5–30 minutes).

## 2. Install the software (once)

SSH into the VPS (`ssh root@YOUR_VPS_IP`) and run:

```bash
sudo apt update && sudo apt -y upgrade
sudo apt -y install software-properties-common unzip git nginx mysql-server certbot python3-certbot-nginx
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt -y install php8.3-fpm php8.3-cli php8.3-mysql php8.3-sqlite3 php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw --force enable
```

## 3. Create the database (once)

```bash
sudo mysql
```
```sql
CREATE DATABASE zplus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'zplus'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON zplus.* TO 'zplus'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 4. Get the code (once)

The repository is private, so the VPS needs read access. Easiest is a **deploy key**:

```bash
sudo mkdir -p /var/www && sudo chown $USER /var/www
ssh-keygen -t ed25519 -f ~/.ssh/zplus_deploy -N "" -C "zplus-vps"
cat ~/.ssh/zplus_deploy.pub
```
Copy the line it prints. On GitHub: **epfraghu/zplus → Settings → Deploy keys → Add deploy key**, paste it, leave "Allow write access" **unticked**, save. Then:

```bash
printf "Host github.com\n  IdentityFile ~/.ssh/zplus_deploy\n" >> ~/.ssh/config
git clone -b claude/manpower-app-foundation git@github.com:epfraghu/zplus.git /var/www/zplus
cd /var/www/zplus
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

## 5. Settings in `.env` (once)

`nano .env` and set:

```dotenv
APP_NAME="ZPlus Manpower"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://billing.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zplus
DB_USERNAME=zplus
DB_PASSWORD=choose-a-strong-password

SESSION_SECURE_COOKIE=true
```

**Keep a copy of `APP_KEY` somewhere safe and never change it.** Bank account and Aadhaar numbers are encrypted with it and cannot be read without it.

Then:

```bash
php artisan migrate --force
ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='choose-your-login-password' php artisan db:seed --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

Do **not** run the DemoSeeder on the live server; it adds sample staff.

## 6. Web server and HTTPS (once)

```bash
sudo cp deploy/nginx-zplus.conf /etc/nginx/sites-available/zplus
sudo sed -i 's/billing.example.com/YOUR-SUBDOMAIN-HERE/g' /etc/nginx/sites-available/zplus
sudo ln -s /etc/nginx/sites-available/zplus /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d YOUR-SUBDOMAIN-HERE
```

Certbot asks for an e-mail and renews the certificate automatically. Open **https://your-subdomain** and sign in.

## 7. Updating later

After changes are pushed to GitHub:

```bash
cd /var/www/zplus && bash deploy/update.sh
```

It puts the site in maintenance mode for a few seconds, pulls the code, runs migrations and clears caches.

## 8. Backups (set this up)

Two things hold your data: the **MySQL database** and **`storage/app/private`** (signed attendance scans, documents, logos). Plus `.env` for the `APP_KEY`.

```bash
sudo mkdir -p /var/backups/zplus
sudo tee /etc/cron.daily/zplus-backup >/dev/null <<'SH'
#!/bin/sh
d=$(date +%F)
mysqldump --single-transaction zplus | gzip > /var/backups/zplus/db-$d.sql.gz
tar czf /var/backups/zplus/files-$d.tgz -C /var/www/zplus storage/app/private .env
find /var/backups/zplus -mtime +30 -delete
SH
sudo chmod +x /etc/cron.daily/zplus-backup
```

Copy the backups off the VPS regularly (download them, or sync to Google Drive). A backup that lives only on the same server is lost with it.

## Moving your laptop data to the VPS (optional)

Your laptop uses SQLite; the VPS uses MySQL, so the file can't be copied directly. If you've only entered test data, start fresh on the VPS and use **Import** to load the GeM workbook there. If you've entered real data you want to keep, ask and a transfer command can be added.

## If something goes wrong

| Symptom | Check |
|---|---|
| Browser can't reach the site | `ping` the subdomain shows the VPS IP? `sudo ufw status` allows Nginx Full? |
| 502 Bad Gateway | `sudo systemctl status php8.3-fpm` |
| 500 error | `tail -50 /var/www/zplus/storage/logs/laravel.log` |
| "Permission denied" in the log | `sudo chown -R www-data:www-data storage bootstrap/cache` |
| Changed `.env` but nothing happens | `php artisan config:cache` |
