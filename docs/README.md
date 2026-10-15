# Shataj School & College — Multi-Tenant SaaS

Laravel 12 · MySQL 8 · Bootstrap 5 · Alpine.js · Chart.js · Sanctum · spatie/laravel-permission

One application serves the institution website and central administration on the main domain and a separate portal for every branch on its own subdomain (`branch1.schooldomain.com` … `branchN`). Branch data is isolated at row level (`institution_id` + `branch_id`) with a fail-closed global scope, host-bound sessions and tenant-bound API tokens.

| Doc | Contents |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Architecture, directory layout, schema & ERD, tenancy strategy, roadmap, security |
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Windows/Laragon setup, Linux production, DNS, SSL, Nginx/Apache, queues, env vars |
| [docs/API.md](docs/API.md) | REST API v1 |
| [docs/PROGRESS.md](docs/PROGRESS.md) | Progress checklist and file manifest |
| [docs/DECISIONS.md](docs/DECISIONS.md) | Architecture decision log |
| [docs/OPERATIONS.md](docs/OPERATIONS.md) | Running the system: cron, queue, backups & restore drills, monitoring, 2FA, subscriptions |
| [docs/HANDOVER.md](docs/HANDOVER.md) | Handover: modules, first-day checklist, scheduled jobs, known limits |

Quick start (Laragon):

```bat
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```
Then open `http://localhost:8000/super-admin` and `http://branch1.localhost:8000`.

admin.branch1@demo.test দিয়ে লগইন করলে সাইডবারে "হাজিরা" আর "স্টাফ ও HR" মেনু পাবেন: শিক্ষার্থী হাজিরা, শিক্ষক-কর্মচারী হাজিরা, স্ক্যান স্টেশন, সংশোধন, মাসিক শিট, সতর্কতা, নিয়ম ও ডিভাইস।
classteacher.branch1@demo.test শিক্ষক পোর্টাল থেকে শুধু Six-A শাখার হাজিরা নিতে পারবেন। এখান থেকে ছুটির আবেদনও করা যায়।
ড্যাশবোর্ডে আজ উপস্থিত/অনুপস্থিত শিক্ষার্থী ও শিক্ষক, হাজিরার শতাংশ আর দৈনিক উপস্থিতির চার্ট যোগ হয়েছে।