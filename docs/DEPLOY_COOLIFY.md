# Running ZPlus Manpower on the Hostinger VPS with Coolify

The VPS (`srv1962767`, Ubuntu 24.04, Mumbai) runs **Coolify**. Its proxy (Traefik, container `coolify-proxy`) owns
ports 80 and 443 and handles every domain and HTTPS certificate. So the app is added **in Coolify**, like the other
apps on the server. Do not install nginx sites or certbot for it on the host.

Subdomain used below: **zplus.epf-dashboard.online**.

## 1. DNS (Hostinger hPanel)

hPanel → Domains → epf-dashboard.online → DNS records → add:

| Type | Name | Points to | TTL |
|---|---|---|---|
| A | `zplus` | the VPS IP (same as the `annexure` record) | 14400 |

## 2. Let Coolify read the private GitHub repo (once)

Coolify dashboard (the one you use for the other apps) → **Sources** → **+ Add** → **GitHub App** → follow the steps,
and when GitHub asks which repositories, choose **epfraghu/zplus**.

(Alternative: Coolify → Keys & Tokens → Private Keys → create a key, then add its public key on GitHub under
epfraghu/zplus → Settings → Deploy keys, read-only. Then pick "Private Repository (with Deploy Key)" in step 3.)

## 3. Create the app

1. Coolify → **Projects** → pick or create a project (e.g. "ZPlus") → **+ New** → **Resource**.
2. Choose **Private Repository (with GitHub App)** and select **epfraghu/zplus**.
3. Branch: **`claude/manpower-app-foundation`**.
4. Build Pack: **Docker Compose**. Docker Compose location: **`/docker-compose.yaml`**. Continue.
5. Coolify shows two services, **app** and **db**. Open **Domains → Add Domain**: Service **app**, Protocol **https**,
   Domain **zplus.epf-dashboard.online**, Port **80**, Path empty. Leave **db** without a domain (it stays private).
   Do **not** keep a `www.zplus…` domain unless it has its own DNS record: without one, Let's Encrypt fails for it.

## 4. Environment variables

In the resource → **Environment Variables**:

| Name | Value |
|---|---|
| `APP_KEY` | a new key: on your laptop in `C:\Users\Dell\zplus` run `php artisan key:generate --show` and paste the whole output (starts with `base64:`) |
| `APP_URL` | `https://zplus.epf-dashboard.online` |
| `SERVICE_PASSWORD_ZPLUSDB`, `SERVICE_PASSWORD_ZPLUSROOT` | Coolify fills these with random passwords. Leave them. |

**Save a copy of `APP_KEY` somewhere safe and never change it.** Bank account and Aadhaar numbers are encrypted
with it; with a different key they cannot be read.

## 5. Deploy

Click **Deploy**. The first build takes a few minutes. When it finishes, both containers show **Running (healthy)**.
On every start the app waits for MySQL, applies database updates and caches its settings.

## 6. Create your admin login (once)

Coolify → the resource → **Terminal** → choose the **app** container → run (with your email and a password):

```bash
ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='your-login-password' php artisan db:seed --force
```

Open **https://zplus.epf-dashboard.online** and sign in. Do not run the DemoSeeder here; it adds sample staff.

## Updating

When new code is pushed to the branch, open the resource in Coolify and click **Redeploy**
(or turn on **Auto Deploy** under the resource's settings so it redeploys on every push).

## Backups

Data lives in two Docker volumes: the MySQL data and `storage/app` (signed scans, documents, logos).

- Coolify → the resource → **Backups** (or the db service → Backups): schedule a daily MySQL backup.
  Coolify can send it to S3-compatible storage; otherwise download backups regularly.
- Uploaded files: from the server, `docker ps | grep zplus` to find the app container, then
  `docker cp <app-container>:/var/www/html/storage/app ./zplus-files-$(date +%F)` and copy that folder off the server.

## Troubleshooting

| Symptom | Check |
|---|---|
| Deploy fails at "apt-get" or "composer" | Redeploy once (temporary network issue); otherwise copy the build log here |
| App container keeps restarting, log says APP_KEY is empty | Set `APP_KEY` in Environment Variables and redeploy |
| "Database not reachable" | Check the db container is running; don't change the `SERVICE_PASSWORD_*` values after the first deploy |
| Browser warns "self-signed certificate" | No domain set yet, or a domain without DNS (e.g. `www.zplus…`) is listed. Check `docker logs coolify-proxy --since 15m 2>&1 \| grep -i acme` |
| "Bad Gateway" | The domain's port must be **80** |
| Site shows "no available server" or 404 from Traefik | The **app** service domain isn't set to `https://zplus.epf-dashboard.online`, or DNS isn't pointing at the VPS yet |
| 500 error | Coolify → app → **Logs** |
