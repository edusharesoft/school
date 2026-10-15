# Installation & Deployment Guide

> After installing, follow the checklist in [HANDOVER.md](HANDOVER.md) §3 and the runbook in [OPERATIONS.md](OPERATIONS.md) (backups, monitoring, two-factor).

## 1. Requirements

| Component | Version | Notes |
|---|---|---|
| PHP | **8.2 or newer** (8.3 recommended) | Extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `bcmath`, `intl`, `gd` (later: PDF/ID cards), `redis` (optional) |
| Composer | 2.x | |
| MySQL | 8.0+ (or MariaDB 10.6+) | `utf8mb4_unicode_ci` |
| Node.js | 20+ with npm | Only needed to rebuild CSS/JS (a production build is already included in `public/build`) |
| Redis | 6+ | Optional locally; recommended in production for cache + queues |

---

## 2. Local development on Windows (Laragon) — `*.localhost` subdomains

Branches are served as `http://branch1.localhost:8000`, `http://branch2.localhost:8000`, … using `php artisan serve`.
Chrome, Edge and Firefox resolve every `*.localhost` name to `127.0.0.1` automatically, so **no hosts-file edits are needed**.

1. **Check PHP.** Open *Laragon → Terminal* and run `php -v`. If it is below 8.2, download PHP 8.3 (NTS x64) from windows.php.net, unzip into `C:\laragon\bin\php\`, then *Laragon → PHP → Version* to switch.
   In *Laragon → PHP → Extensions* make sure `pdo_mysql`, `fileinfo`, `intl`, `mbstring`, `openssl`, `gd` are ticked.
2. **Start MySQL** from Laragon (*Start All*).
3. **Install dependencies** (in `D:\laragon\www\school`):
   ```bat
   cd /d D:\laragon\www\school
   composer install
   copy .env.example .env
   php artisan key:generate
   ```
4. **Create the database** (Laragon → Database, or):
   ```bat
   mysql -u root -e "CREATE DATABASE school_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```
   The defaults in `.env` (`DB_USERNAME=root`, empty password) match Laragon.
5. **Migrate & seed** (roles, permissions, Super Admin, demo institution with 10 branches):
   ```bat
   php artisan migrate --seed
   php artisan storage:link
   ```
6. **Run the app** — two terminals:
   ```bat
   php artisan serve --host=127.0.0.1 --port=8000
   php artisan queue:work
   ```
7. **Open:**

   | URL | What |
   |---|---|
   | `http://localhost:8000` | Public website (branch directory) |
   | `http://localhost:8000/super-admin` | Central administration |
   | `http://branch1.localhost:8000` … `http://branch10.localhost:8000` | Branch portals |

   > Always use `localhost`, not `127.0.0.1`, for the central area: sessions are host-only, and the canonical central host is the first entry of `TENANCY_CENTRAL_DOMAINS`.

8. **Rebuild assets** (only if you change SCSS/JS): `npm install` then `npm run build` (or `npm run dev` for hot reload).

### Demo accounts (local/staging only — password `password`)

| Account | Where to sign in | Role |
|---|---|---|
| `superadmin@schooldomain.com` / `ChangeMe!2026` | `localhost:8000/login` | Platform Super Admin (from `.env` `SEED_SUPER_ADMIN_*`) |
| `admin@demo.test` | `localhost:8000/login` or any branch | Institution Administrator |
| `auditor@demo.test` | `localhost:8000/login` | Auditor (read-only finance/audit) |
| `admin.branch1@demo.test` | `branch1.localhost:8000/login` | Branch Administrator (branch 1 only) |
| `principal.branchN@demo.test`, `accounts.branchN@demo.test` | branch N | Principal / Accountant |
| `teacher.branchN@demo.test`, `classteacher.branchN@demo.test` | branch N | Teacher portal |
| `student.branchN@demo.test`, `parent.branchN@demo.test` | branch N | Student / Parent portal |
| `hr@demo.test` | branch 1 or 2 | HR Manager with access to two branches (try *Switch branch*) |

Try the isolation yourself: sign in as `admin.branch1@demo.test` on `branch2.localhost:8000` — it is refused.

### Upgrading an existing local install to a new part

```bat
git pull            (or copy the new files)
composer install
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=DemoAcademicSeeder      (demo data for the new part, local only)
php artisan optimize:clear
```

### Running the tests

```bat
php artisan test
```
Tests use SQLite in memory (`pdo_sqlite` extension required — enabled in Laragon by default). Subdomain requests are simulated (`http://branch1.localhost/...`), so no web server is needed.

### Using Laragon's Apache instead of `artisan serve` (optional)

Laragon auto-creates `school.test`. For wildcard branches under it, set `TENANCY_BASE_DOMAIN=school.test`, `TENANCY_CENTRAL_DOMAINS=school.test`, `TENANCY_URL_PORT=`, add `ServerAlias *.school.test` to `C:\laragon\etc\apache2\sites-enabled\auto.school.test.conf`, and add one hosts line per branch (`127.0.0.1 branch1.school.test`) because Windows hosts files do not support wildcards.

---

## 3. Production deployment (Ubuntu 22.04/24.04, Nginx, PHP-FPM 8.3)

### 3.1 DNS

| Type | Name | Value |
|---|---|---|
| A | `schooldomain.com` | server IPv4 |
| A | `*.schooldomain.com` | server IPv4 (wildcard — every branch, current and future) |
| AAAA | same two | server IPv6 (optional) |
| CNAME | `campus.other-domain.edu.bd` | `schooldomain.com` (only for branch custom domains) |

### 3.2 Server packages

```bash
sudo apt update && sudo apt install -y nginx mysql-server redis-server supervisor unzip git \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath \
  php8.3-intl php8.3-gd php8.3-redis php8.3-sqlite3
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```

### 3.3 Database

```sql
CREATE DATABASE school_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'school'@'localhost' IDENTIFIED BY '<strong-password>';
GRANT ALL PRIVILEGES ON school_saas.* TO 'school'@'localhost';
FLUSH PRIVILEGES;
```

### 3.4 Application

```bash
sudo mkdir -p /var/www/school && sudo chown $USER:www-data /var/www/school
git clone <your-repo> /var/www/school && cd /var/www/school
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# edit .env — see section 4
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan school:create-super-admin --email=you@schooldomain.com
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```
Do **not** run the full `db:seed` in production — the demo seeder is skipped automatically when `APP_ENV=production`, but only the two commands above are needed.

### 3.5 Nginx + wildcard SSL

Config: `deploy/nginx/school.conf` (copy to `/etc/nginx/sites-available/`, symlink into `sites-enabled`, `nginx -t && systemctl reload nginx`).

Wildcard certificates require the DNS-01 challenge:
```bash
sudo apt install -y certbot python3-certbot-dns-cloudflare   # or the plugin for your DNS provider
sudo certbot certonly --dns-cloudflare --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  -d schooldomain.com -d '*.schooldomain.com'
```
Renewal is automatic (`systemctl list-timers | grep certbot`). Branch custom domains need their own certificate (`certbot --nginx -d campus.other-domain.edu.bd`) and a `server_name` entry.

Apache alternative: `deploy/apache/school.conf`.

### 3.6 Queue workers & scheduler

```bash
sudo cp deploy/supervisor/school-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
# Scheduler (crontab -e -u www-data)
* * * * * cd /var/www/school && php artisan schedule:run >> /dev/null 2>&1
```

### 3.7 Deploying updates

`deploy/scripts/deploy.sh` — maintenance mode, pull, composer, migrate, sync permissions, cache, restart workers.

### 3.8 Logs

`LOG_STACK=daily` keeps 14 days in `storage/logs`. Ship to a central system (e.g. Loki / CloudWatch) in larger setups. Failed jobs: `php artisan queue:failed`, retry with `php artisan queue:retry all`.

### 3.9 Backups (full guide in Part 7)

`deploy/scripts/backup.sh` — nightly `mysqldump --single-transaction` + `storage/app` tarball, gzip + GPG-encrypted, kept outside the web root (`/var/backups/school`), 14 daily / 8 weekly retention. Test a restore monthly on a staging server.

---

### 3.10 SMS, e-mail and WhatsApp (Part 5-1)

Messages leave the server only through the **queue worker** (`php artisan queue:work`) and the **scheduler** (`schedule:run` every minute: scheduled notices and messages, retries of stuck messages, daily overdue-fee reminders). Without them messages stay "queued".

Configure per branch under **Communication → SMS, e-mail & WhatsApp settings** (secrets are stored encrypted):

- **SMS** — **Shataj SMS (our own gateway, recommended)**: API key from the Shataj SMS panel, server `https://sms.shataj.com`, optional device ID (`1`, or `1|0` for SIM slot 1 of device 1; empty = primary device). The phone sends the SMS shortly after the API accepts it; the scheduler fetches the final status (delivered / failed) every minute. Never put the API key in code or `.env` of another project — enter it on the settings page (stored encrypted). Other providers: SSL Wireless (API token + SID), BulkSMSBD (API key + sender ID) or Alpha SMS / sms.net.bd (API key, optional sender ID). Get an approved sender ID / masking from the provider (BTRC rules apply) and whitelist the server's public IP address where the provider requires it. "Log only" writes to `storage/logs` and sends nothing — use it for testing.
- **E-mail** — the application mailer from `.env` (`MAIL_*`), or the branch's own SMTP server.
- **WhatsApp** — WhatsApp Business Platform (Cloud API): phone number ID, a permanent access token, the app secret and a webhook verify token of your Meta app. In the Meta app set the callback URL to `https://{branch-host}/webhooks/whatsapp` and subscribe to the `messages` field. Only templates approved in WhatsApp Manager can start conversations; enter each template name under **Communication → Message templates**. Get the recipients' consent before messaging them on WhatsApp.
- Use **Send a test message** on the settings page after saving; the provider's answer is shown.

### 3.11 Online payment gateways (Part 5-2)

Configure per branch under **Fees & accounts → Online payment settings**. Start every gateway in **sandbox** mode with the provider's test credentials, make a test payment from a parent login (Fees page → Pay online), then switch to live credentials.

- The **scheduler** must run (`payments:reconcile` every ten minutes settles payments whose payer never came back and expires abandoned checkouts).
- The site must be reachable over **HTTPS** at the branch host; the gateways redirect payers to `https://{branch-host}/online-payments/{gateway}/return/…`, which is generated automatically for each payment.
- **Bangla QR (no gateway account needed)** — ask the school's bank or mobile-wallet provider for a merchant (static) *Bangla QR* for the account that should receive fees. Scan it with a phone, copy the text it contains (it starts with `000201`) and paste it under **Online payments → Gateway settings → Bangla QR**; switch it on. The system checks the text (CRC, account tags, taka) and for every bill draws a QR with the exact amount and the payment reference. A parent pays with any bank / bKash / Nagad / Rocket / Upay app, then sends the TrxID on the QR page; the accounts office accepts it under **Online payments** (find the TrxID in the account statement), or uploads the statement CSV under **Match a bank statement** and matching rows are accepted automatically. Money arrives directly in the school's account — no checkout fee from us; fees (if any) are the bank's.
- **ShatajPay (default)** — register at https://pay.shataj.com/register and enter the app username, password, app key, app secret and gateway ID. In the ShatajPay panel set the merchant app's callback URL to `https://{branch-host}/gateway/payment/callback` (shown on the settings page). Until ShatajPay offers a verification API, payments it reports as complete appear under **Online payments → Review**; check each one in the ShatajPay panel and press **Accept and post receipt**. Refunds are made from the ShatajPay panel.
- **SSLCOMMERZ** — Store ID and store password. In the merchant panel set the IPN URL shown on the settings page (`https://{branch-host}/online-payments/sslcommerz/ipn`). Refunds work through the API (enable the refund API for the store with SSLCOMMERZ).
- **bKash** — Tokenized Checkout credentials (username, password, app key, app secret). bKash usually whitelists the server's public IP for live access. Refunds work through the API.
- **Nagad** — merchant ID, merchant account number, the server's public IP (registered with Nagad), the merchant private key and Nagad's public key (base64, as Nagad provides them). The API base URLs are editable because Nagad sends them with the merchant agreement. Refund from the Nagad merchant panel, then record the reference.
- **shurjoPay** — API username, password and order prefix. Refund from the merchant panel.
- **aamarPay** — Store ID and signature key. Refund from the merchant panel.
- Choose the **deposit account** per gateway (chart of accounts) so settlements can be reconciled against the bank statement.

## 4. Environment variables

| Variable | Example (production) | Purpose |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | Never enable debug in production |
| `APP_URL` | `https://schooldomain.com` | Canonical central URL |
| `APP_TIMEZONE` | `Asia/Dhaka` | Bangladesh Standard Time |
| `APP_CURRENCY` | `BDT` | Default currency |
| `APP_SUPPORTED_LOCALES` | `en,bn` | Language switcher options |
| `TENANCY_BASE_DOMAIN` | `schooldomain.com` | Branch hosts are `<subdomain>.<base>` |
| `TENANCY_CENTRAL_DOMAINS` | `schooldomain.com` | Hosts that serve website + central admin (first = canonical) |
| `TENANCY_CENTRAL_PATH` | `super-admin` | Central admin URL prefix |
| `TENANCY_URL_SCHEME` | `https` | Scheme for cross-host links |
| `TENANCY_URL_PORT` | *(empty)* | Port for cross-host links (8000 locally) |
| `TENANCY_RESOLVER_CACHE_TTL` | `600` | Host→branch cache seconds |
| `TENANCY_HANDOFF_TTL` | `60` | Branch-switch token lifetime (seconds) |
| `DB_*` | | MySQL connection; `DB_TIMEZONE=+06:00` |
| `SESSION_DRIVER` | `redis` or `database` | |
| `SESSION_DOMAIN` | *(empty)* | **Keep empty** — host-only cookies isolate branches |
| `SESSION_SECURE_COOKIE` | `true` | HTTPS-only cookies |
| `CACHE_STORE` / `QUEUE_CONNECTION` | `redis` / `redis` | |
| `REDIS_*` | | |
| `MAIL_*` | | SMTP for password reset & notifications |
| `SEED_SUPER_ADMIN_EMAIL` / `_PASSWORD` | | Only used by the seeder |
| `SEED_DEMO_BRANCHES` | `10` | Demo seeder only |

Payment gateway, SMS, WhatsApp, Zoom and Google variables are added with their modules (Part 5) and documented there.
