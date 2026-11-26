# Database backups and the dashboard backup card

The app does not back itself up: a script on the server makes the backup every night (the **primary**), and a copy is
kept off the server, on the owner's laptop (the **secondary**). Each script then tells the app what it did, and the
developer login sees it on the dashboard: green = backed up in the last 26 hours, amber = older, red = failed or none.
**Setup → Backups** shows the history.

Never paste passwords or the backup files anywhere else; the files hold real staff and bank data.

## 1. Primary: nightly backup on the server

In the Hostinger VPS terminal (SSH as root). The container names change on every deploy, so the scripts find them.

### a) Helper that reports to the app

```sh
cat > /root/zplus-record.sh <<'EOF'
#!/bin/sh
# Usage: zplus-record.sh <primary|secondary> <server name> <ok|failed> [file] [size in bytes] [note]
APP=$(docker ps --format '{{.Names}}' | grep '^app-ppc5ypfm2aogcq4hfie2kwhv' | head -1)
docker exec "$APP" php artisan backups:record --location="$1" --server="$2" --status="$3" --file="$4" --size="$5" --note="$6"
EOF
chmod 700 /root/zplus-record.sh
```

### b) The nightly backup

```sh
mkdir -p /root/zplus-backups && chmod 700 /root/zplus-backups
cat > /root/zplus-backup.sh <<'EOF'
#!/bin/sh
# Daily backup of the ZPlus database and uploaded files; keeps 14 days; reports to the app.
D=$(date +%F); H=$(hostname); OUT=/root/zplus-backups
DB=$(docker ps --format '{{.Names}}' | grep '^db-ppc5ypfm2aogcq4hfie2kwhv' | head -1)
APP=$(docker ps --format '{{.Names}}' | grep '^app-ppc5ypfm2aogcq4hfie2kwhv' | head -1)
docker exec "$DB" sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --routines --triggers --set-gtid-purged=OFF "$MYSQL_DATABASE"' > $OUT/db-$D.sql
if tail -1 $OUT/db-$D.sql | grep -q "Dump completed"; then
  gzip -f $OUT/db-$D.sql
  docker cp "$APP":/var/www/html/storage/app $OUT/files-$D && tar -czf $OUT/files-$D.tar.gz -C $OUT files-$D && rm -rf $OUT/files-$D
  /root/zplus-record.sh primary "$H" ok "db-$D.sql.gz" "$(stat -c %s $OUT/db-$D.sql.gz)"
else
  /root/zplus-record.sh primary "$H" failed "db-$D.sql" "" "dump did not finish"
fi
find $OUT -name "*-20*" -mtime +14 -delete
EOF
chmod 700 /root/zplus-backup.sh
```

### c) Run once now, then every night at 2:00 AM IST

```sh
/root/zplus-backup.sh
ls -lh /root/zplus-backups
(crontab -l 2>/dev/null; echo "30 20 * * * /root/zplus-backup.sh") | crontab -
crontab -l
```

The dashboard card now shows **Primary: Backed up** in green with the server name and time.

## 2. Secondary: copy to the laptop

Once, set up a key so the laptop can copy without typing the server password (PowerShell):

```powershell
ssh-keygen -t ed25519            # press Enter for the questions
type $env:USERPROFILE\.ssh\id_ed25519.pub | ssh root@YOUR_VPS_IP "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys"
```

Then save this as `C:\zplus-backups\pull-backup.ps1`:

```powershell
# Copies the newest server backup to this laptop and reports it as the secondary backup.
$vps = "root@YOUR_VPS_IP"
$dest = "C:\zplus-backups\server"
New-Item -ItemType Directory -Force -Path $dest | Out-Null
$file = (ssh $vps "ls -t /root/zplus-backups/db-*.sql.gz | head -1").Trim()
$name = Split-Path $file -Leaf
scp "${vps}:$file" "$dest\$name"
if (Test-Path "$dest\$name") {
  $size = (Get-Item "$dest\$name").Length
  ssh $vps "/root/zplus-record.sh secondary $env:COMPUTERNAME ok $name $size"
} else {
  ssh $vps "/root/zplus-record.sh secondary $env:COMPUTERNAME failed $name '' 'copy to laptop failed'"
}
```

Run it with `powershell -ExecutionPolicy Bypass -File C:\zplus-backups\pull-backup.ps1`. To run it every day, add it in
**Task Scheduler** (Create Basic Task → Daily → Start a program: `powershell`, arguments
`-ExecutionPolicy Bypass -File C:\zplus-backups\pull-backup.ps1`). The card then shows **Secondary: Backed up** with the
laptop's name.

## 3. Check a backup can be restored (monthly)

Unzip the newest `db-….sql.gz` on the laptop and load it into a test database, as in the laptop copy steps: a backup is
only proven once it has been restored.
