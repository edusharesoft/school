# Handover guide (Part 8)

For the owner / system administrator who takes over the platform. Read this first, then `docs/OPERATIONS.md` (daily running)
and `docs/INSTALLATION.md` (servers).

## 1. What the system is

One Laravel 12 application that serves
* the public institution website and the **central administration** (`/super-admin`) on the main domain, and
* a separate portal for every **branch** on its own subdomain (`branch1.example.com` …).

Branch data is separated per row (`institution_id` + `branch_id`) by a fail-closed scope; sessions and API tokens are bound
to the host they were issued on. Details: `docs/ARCHITECTURE.md`.

## 2. Modules (all built and covered by automated tests)

| Area | What it does |
|---|---|
| Tenancy & access | institutions, branches, domains, 22 system roles + custom roles, permission catalogue, audit log, branch switching |
| Academic & students | sessions, classes, sections, subjects, admissions (campaigns, merit list), student profiles, guardians, promotion, transfer, import / export |
| Attendance & HR | student and staff attendance (manual, scan station, devices API), corrections, leave, employees, payroll |
| Exams & results | exams, marks entry, grading, result processing, publishing, mark sheets, admit cards, seat plans |
| Finance | fee heads / structures, invoices, collection, discounts, late fees, refunds, online payments (Bangla QR, SSLCOMMERZ, bKash, Nagad, shurjoPay, aamarPay), double-entry accounting, budgets, bank reconciliation, year closing |
| Communication & learning | notices, SMS / e-mail / WhatsApp hub, in-app inbox, homework, materials, quizzes, online classes (Zoom / Meet) |
| Operations | library, transport, hostel, inventory & purchases, asset register & depreciation |
| Documents & reports | certificates (student + staff), ID cards, PDF engine (mPDF), reports centre (31 reports → screen / Excel / CSV / PDF, background exports) |
| Portals & API | teacher / student / parent portals, REST API v1 (Sanctum) |
| SaaS & operations | plans, trials, payments, limits, read-only on expiry; two-factor sign-in; backups with restore drills; health monitor; slow-query log; performance audit |

The checklist with every feature and the file manifest of each part is in `docs/PROGRESS.md`.

## 3. First-day checklist (new server)

1. Server per `docs/INSTALLATION.md` (PHP 8.2+, MySQL 8, Nginx / Apache, Redis recommended, Supervisor, cron).
2. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, database, `TENANCY_BASE_DOMAIN`, `TENANCY_CENTRAL_DOMAINS`,
   mail, `QUEUE_CONNECTION=redis|database`, and the Part 7 values: `SECURITY_2FA_REQUIRED_ROLES`, `BACKUP_PASSPHRASE`,
   `HEALTH_TOKEN`, `MONITOR_ALERT_EMAIL`.
3. `composer install --no-dev -o`, `php artisan key:generate` (**keep a copy of `APP_KEY` outside the server — encrypted
   data cannot be read without it**), `php artisan migrate --force`, `php artisan db:seed --class=RolesAndPermissionsSeeder`,
   `php artisan school:create-super-admin`, `php artisan storage:link`, `php artisan optimize`.
4. Cron every minute: `php artisan schedule:run`. Supervisor: `deploy/supervisor/school-worker.conf`.
5. PDF: `composer require mpdf/mpdf mpdf/qrcode` (the QR code on certificates and ID-card backs needs the second package; without it the documents print the verification code as text); for the best Bangla look put `NotoSansBengali-Regular.ttf` and `-Bold.ttf` into `storage/fonts`.
6. Sign in as Super Admin → **Account security** → switch on two-factor and save the recovery codes.
7. *Subscriptions → Plans*: create the plans (the first active plan with trial days is given to self-registered institutions).
8. *Backups* → **Back up now**, then **Test restore**. Copy the backup folder off the server (see `OPERATIONS.md` §2).
9. Point an uptime monitor at `/health/status?token=…`.
10. Log in once to every portal type (teacher, student, parent) with demo or real accounts and print one certificate / report
    PDF to check fonts and layout (see "Not verified" below).

## 4. Scheduled jobs (all started by the one cron line)

| When | Command | Purpose |
|---|---|---|
| every minute | heartbeat; `notifications:dispatch` | scheduler proof; scheduled messages |
| every 10 min | `monitor:check`; `payments:reconcile` | health + alert mail; re-verify unfinished online payments |
| 02:10 / 02:20 | `queue:prune-failed`, `sanctum:prune-expired`; **`backup:run`** | housekeeping; nightly backup |
| 02:30 / 03:10 | `fees:late-fees`; `reports:housekeeping` | late fees; delete old export files |
| 06:00–07:45 daily/monthly | `fees:generate-invoices`, `services:generate-fees`, `library:housekeeping`, `assets:housekeeping` | invoices and reminders |
| 10:05 | `notifications:fee-reminders` | overdue-fee reminders (off unless enabled per branch) |
| 14:30 / 19:00 | `attendance:absence-alerts` | absence messages to guardians |
| Sunday 04:00 | **`backup:verify --drill`** | weekly restore drill |

## 5. Roles to know

`super-admin` (platform owner: all central screens, subscriptions, backups), `institution-admin` (one institution, all
branches), `branch-admin` (one branch), then the working roles (principal, accountant, teacher, librarian …). Portal users
(teacher, student, parent) never reach the back office. Custom roles can be made in *Central → Roles & permissions*.

## 6. Security summary

* Passwords hashed (bcrypt), login throttled, sessions host-bound, CSRF everywhere (payment webhooks verified by signature).
* Optional / mandatory two-factor (TOTP + recovery codes); lost devices reset by an administrator, audited.
* Row-level tenant isolation proven by `TenantIsolationTest` and a "branch isolation" test in every module.
* Audit log of changes and sign-ins; gateway secrets encrypted; uploads on the private disk, served only through
  permission-checked routes; backups can be AES-encrypted.
* Expired / suspended institutions become read-only, never deleted.

## 7. Known limits and things not verified

* **PDF output** (certificates, ID cards, reports, mark sheets, admit cards) was tested with a recorder in place of mPDF —
  the HTML, data and routes are tested, but the real rendering (Bangla shaping, barcode / QR sizes, page breaks) must be
  checked once on the production machine and font files added if a face looks wrong.
* **Online payment gateways, Zoom / Google Meet, SMS gateways** are tested with faked HTTP; each needs a live sandbox
  transaction with the real merchant credentials before go-live.
* The two-factor set-up page shows the key and a link rather than a QR image (no QR library is bundled); the authenticator
  apps accept the typed key.
* Restore drills run into a temporary SQLite database by default (proves the data and the migrations round-trip);
  do one real disaster-recovery rehearsal on a spare MySQL server before you depend on it.
* Photo cropping for ID cards is not built (photos are used as uploaded).
* Subscription payments are recorded manually by the platform owner; there is no online checkout for the SaaS fee itself.

## 8. Tests and quality gate

`php artisan test` — the whole suite (385 tests, about 13 minutes; it uses an in-memory SQLite database and fakes every
external service). Run it before every deployment (`deploy/scripts/deploy.sh` does the rest). New permissions need
`php artisan db:seed --class=RolesAndPermissionsSeeder` after deployment (the deploy script already runs it).

## 9. Where to look when something is wrong

| Symptom | Look at |
|---|---|
| Messages / exports "queued" forever | queue worker not running → Supervisor; *System health → Queue* |
| Nothing scheduled happens | cron missing → *System health → Scheduler* |
| Users can only view, cannot save | subscription expired / suspended → *Subscriptions* |
| A user lost their phone | *Central → Users → Reset two-factor* |
| Disk filling up | backups folder and `storage/app/private/exports`; `BACKUP_KEEP_DAYS`; copy off-server |
| Slow screen | `storage/logs` "Slow query" lines, `php artisan performance:audit` |
| 500 error | `storage/logs/laravel-*.log` |
