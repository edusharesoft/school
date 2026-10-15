# Shataj School & College SaaS — Architecture (Part 0)

Multi-tenant School & College Management Platform for an institution with 10 branches (extensible to more branches and more institutions). This document covers items A–G of the master prompt §45 and is the reference for every later part. Decisions here are **binding**; any later change must be recorded in `docs/DECISIONS.md` with a migration strategy.

---

## A. Technical architecture

| Layer | Choice | Notes |
|---|---|---|
| Language | PHP **8.2+** (8.3 recommended) | Laravel 12 minimum is 8.2 |
| Framework | **Laravel 12** | Slim skeleton (`bootstrap/app.php`), Eloquent, Queues, Scheduler |
| Database | **MySQL 8.0** (`utf8mb4_unicode_ci`) | Shared database, row-level tenancy; SQLite in-memory for tests |
| Auth (web) | Laravel session guard, custom login controller | Login by email / phone / username, throttled |
| Auth (API) | **Laravel Sanctum 4** personal access tokens | Tokens carry a `tenant:<key>` ability — a token issued on branch 1 is refused on branch 2 |
| RBAC | **spatie/laravel-permission 6** | 21 seeded system roles, custom roles, granular `module.action` permissions |
| UI | Blade + **Bootstrap 5.3** + Alpine.js + **Chart.js 4** + Bootstrap Icons, built with **Vite** | Bangla font: *Hind Siliguri* / *Noto Sans Bengali* |
| Tables | Server-side paginated Blade tables with search/filter/sort (DataTables-equivalent, no jQuery) | Keeps queries scoped & paginated in SQL |
| Cache / Queue | Redis in production, `database` driver fallback (works on Laragon without Redis) | |
| Files | Laravel Filesystem, `local` (private, `storage/app/private`) + `public` disks, tenant-prefixed paths | Downloads through authorised controllers / signed URLs |
| PDF (Part 6+) | `mpdf/mpdf` (best Bangla Unicode shaping) | Chosen over dompdf because dompdf cannot shape Bangla conjuncts |
| Excel/CSV (Part 3+) | `openspout/openspout` (streaming, low memory) | |
| Tests | PHPUnit 11 (Laravel test runner) | Feature tests per module, factories + seeders |

### Logical architecture

```
                 ┌────────────────────────────── HTTP request ───────────────────────────────┐
                 │ Host: localhost | schooldomain.com | branch3.schooldomain.com | custom.edu │
                 └───────────────────────────────────┬───────────────────────────────────────┘
                                                     ▼
                                   ResolveTenant middleware (web + api)
                     central host ─────────────┐     │     ┌────────── branch host / custom domain
                                               ▼     ▼     ▼
                                     TenantContext (request-scoped singleton)
                               mode = central (institution|platform)  or  branch (branch_id)
                                                     │
          ┌──────────────────────────────┬───────────┴──────────────┬───────────────────────────┐
          ▼                              ▼                          ▼                           ▼
  Central routes (/super-admin)   Branch routes (/dashboard…)   Portal routes              API /api/v1
  Super Admin, Inst. Admin,       Branch staff (policies +      (/teacher /student         (Sanctum, same
  Auditor                          permissions)                  /parent)                   context rules)
          │                              │                          │                           │
          └───────────── Controllers → FormRequests → Policies → Services → Models ─────────────┘
                                                     │
                            BelongsToBranch global scope (fail-closed) + creating guard
                                                     │
                                           MySQL (shared schema)
```

### Module map (domain folders under `app/`)

| Module | Part | Main namespace |
|---|---|---|
| Tenancy (institutions, branches, domains, context) | 2 | `App\Tenancy`, `App\Services\Tenancy` |
| Identity & RBAC (users, roles, permissions, branch access, handoff) | 2 | `App\Models\User`, `App\Http\Controllers\Auth` |
| Audit & Settings | 2 | `App\Services\Audit`, `App\Services\Settings` |
| Dashboards | 2 (core), grows each part | `App\Services\Dashboard` |
| HR foundation — departments (reference tenant module) | 2 | `App\Models\Department` |
| Academic structure, admission, SIS, staff, attendance, timetable, exams | 3 | `App\Models\Academic\*`, … |
| Fees, invoices, payments, accounting, expenses, payroll | 4 | `App\Models\Finance\*` |
| Notifications, SMS/Email/WhatsApp, gateways, Zoom/Meet, LMS | 5 | `App\Integrations\*` |
| Library, transport, hostel, inventory, CMS, certificates, reports | 6 | |
| SaaS subscriptions, backup, performance, monitoring | 7 | |

---

## B. Project directory structure

```
school/
├── app/
│   ├── Console/Commands/        tenancy:run, school:create-super-admin, …
│   ├── Enums/                   UserType, BranchStatus, TenantMode …
│   ├── Exceptions/              TenantContextMissing, CrossTenantViolation
│   ├── Http/
│   │   ├── Controllers/{Auth,Central,Branch,Portal,Api/V1,Public}
│   │   ├── Middleware/          ResolveTenant, RequireBranch, RequireCentral, EnsureTenantAccess, SetLocale, EnsurePortal
│   │   ├── Requests/{Auth,Central,Branch}
│   │   └── Resources/           API resources
│   ├── Jobs/Middleware/         WithTenantContext (queued jobs restore branch context)
│   ├── Listeners/               Auth audit listeners
│   ├── Models/{Concerns,Scopes} BelongsToBranch, BelongsToInstitution, Auditable, BranchScope
│   ├── Policies/
│   ├── Providers/AppServiceProvider.php
│   ├── Services/{Tenancy,Dashboard,Audit,Settings}
│   ├── Support/                 helpers.php, ApiResponse
│   ├── Tenancy/                 TenantContext, TenantResolver
│   └── View/Components/
├── bootstrap/app.php            middleware aliases, route files, exception rendering
├── config/                      app, database, tenancy, school (roles & permissions catalogue)
├── database/{migrations,factories,seeders}
├── deploy/{nginx,apache,scripts}
├── docs/                        ARCHITECTURE, INSTALLATION, PROGRESS, API, DECISIONS
├── lang/{en,bn}.json
├── resources/{js,scss,views}
├── routes/{web.php,central.php,branch.php,portal.php,api.php,console.php}
└── tests/{Feature,Unit}
```

---

## C. Database schema & entity relationships

Conventions (all tables): `id BIGINT UNSIGNED PK`, `created_at/updated_at`, InnoDB, `utf8mb4_unicode_ci`.
**Tenant-owned tables carry both `institution_id` and `branch_id`** (FK, indexed, leading column of composite indexes). Soft deletes on master data; **financial and academic ledgers are never soft-deleted or updated in place** (reversal/adjustment rows instead).

### Core (Part 2 — delivered)

| Table | Key columns | Constraints / indexes |
|---|---|---|
| `institutions` | name, name_bn, slug, code, email, phone, address, logo_path, primary_color, timezone, currency, locale, status, settings JSON | UQ slug, UQ code, soft deletes |
| `branches` | institution_id, name, name_bn, code, subdomain, email, phone, address, principal_name, logo_path, primary_color, timezone, currency, locale, status, established_on, settings JSON | FK institution; UQ subdomain (global); UQ (institution_id, code); idx (institution_id, status); soft deletes |
| `domains` | institution_id, branch_id NULL, domain, type (central/branch/custom), is_primary, verified_at | UQ domain; FK cascade |
| `users` | institution_id NULL (NULL = platform owner), default_branch_id NULL, name, email, username, phone, user_type, status, locale, password, last_login_at/ip, remember_token | UQ email, UQ username, UQ phone; idx (institution_id, user_type); soft deletes |
| `branch_user` | branch_id, user_id, is_default, granted_by | UQ (branch_id, user_id) — explicit branch access list |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | spatie schema | UQ (name, guard) |
| `personal_access_tokens` | Sanctum | |
| `audit_logs` | institution_id, branch_id NULL, user_id NULL, event, auditable_type/id, old_values, new_values, ip_address, user_agent, url, created_at | idx (branch_id, created_at), (auditable_type, auditable_id), (event, created_at); **append-only** |
| `system_settings` | institution_id NULL, branch_id NULL, scope_key, group, key, value JSON | UQ (scope_key, group, key) |
| `departments` | institution_id, branch_id, name, name_bn, code, description, is_active | UQ (branch_id, code); soft deletes — *reference implementation of a tenant-owned module* |
| `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens` | Laravel standard | |

### Planned tables (Parts 3–7) — tenant columns on all

- **Academic:** `academic_years` (institution) · `academic_sessions` (branch, year, is_current) · `terms` · `school_classes` · `sections` (class, capacity, class_teacher_id) · `groups` · `subjects` · `class_subjects` (combination, optional flag, full/pass marks) · `rooms` · `timetable_periods` · `timetables` (UQ branch+session+day+period+section; UQ teacher+day+period; UQ room+day+period) · `syllabi` · `lesson_plans` · `homeworks` · `holidays` · `academic_events`.
- **Students:** `students` (permanent record, UQ branch+student_code) · `student_guardians` (pivot to `guardians`, relation, is_primary) · `student_enrollments` (UQ student+academic_session → prevents duplicate enrollment; section, roll UQ section+session+roll, status) · `student_documents` · `student_health_records` (restricted) · `discipline_records` · `admission_forms` · `admission_applications` (UQ branch+application_no) · `admission_tests` · `admission_test_results` · `student_transfers` · `certificates_issued`.
- **Staff/HR:** `employees` (UQ institution+employee_code, user_id) · `teachers` view via `employees.is_teacher` · `designations` · `staff_branch_assignments` · `employee_documents` · `leave_types` · `leave_applications` · `duty_rosters` · `employee_evaluations` · `employment_histories`.
- **Attendance:** `student_attendances` (UQ enrollment+date+period_id) · `staff_attendances` (UQ employee+date) · `attendance_correction_requests` · `attendance_devices` · `attendance_device_logs`.
- **Exams:** `grading_scales` + `grading_scale_items` · `exam_types` · `examinations` · `examination_subjects` (components: theory/practical/CA with max & pass marks) · `exam_rooms`, `seat_plans` · `marks` (UQ exam_subject+enrollment+component) · `results` + `result_subjects` · `result_publications` (approval workflow) · `mark_correction_requests`.
- **Finance:** `fee_categories` · `fee_structures` (branch+session+class) · `student_fee_assignments` · `fee_discounts` / `scholarships` · `invoices` (UQ branch+invoice_no; UQ student+fee_period+structure to stop duplicates) · `invoice_items` · `payments` · `payment_allocations` · `payment_transactions` (gateway, UQ gateway+gateway_txn_id — idempotency) · `refunds` · `accounting_accounts` (chart of accounts, per branch) · `journal_entries` (immutable once posted) · `journal_entry_lines` (debit/credit) · `financial_years` · `expenses` · `bank_accounts` · `bank_reconciliations` · `budgets` · `salary_structures` · `payrolls` · `payroll_items`.
- **Comms & LMS:** `notification_templates` (bn/en) · `notifications` (Laravel) · `notification_logs` · `notification_preferences` (opt-out) · `provider_configs` (encrypted credentials per branch) · `notices` + `notice_reads` · `online_classes` · `online_class_integrations` (encrypted OAuth tokens) · `assignments` · `assignment_submissions` · `learning_materials` · `quizzes`.
- **Operations:** `library_books`, `library_book_copies`, `library_transactions` · `vehicles`, `drivers`, `transport_routes`, `transport_stops`, `transport_allocations` · `hostels`, `hostel_rooms`, `hostel_beds`, `hostel_allocations` · `inventory_items`, `suppliers`, `purchase_orders`, `stock_transactions`, `assets` · `documents` · CMS `pages`, `posts`, `galleries`.
- **SaaS:** `plans`, `plan_features`, `subscriptions`, `subscription_invoices`, `subscription_payments`, `usage_snapshots` (platform level, **separate** from school fee ledgers).

### Relationships (core)

```
institutions 1─* branches 1─* domains
institutions 1─* users *─* branches (branch_user)          users *─* roles *─* permissions
branches 1─* departments   branches 1─* audit_logs   (institution|branch) 1─* system_settings
branches 1─* academic_sessions 1─* student_enrollments *─1 students *─* guardians
student_enrollments 1─* student_attendances · marks · invoices 1─* invoice_items
invoices *─* payments (payment_allocations) · payments 1─* payment_transactions
journal_entries 1─* journal_entry_lines *─1 accounting_accounts
```

### Deletion & archival policy

| Data | Policy |
|---|---|
| Institutions, branches, users, master data | Soft delete; branch deactivation (`status=inactive`) preferred over delete |
| Students | Never hard-deleted; status `withdrawn/transferred/graduated` |
| Attendance, marks | Edit only through correction workflow; old value kept in `audit_logs` |
| Invoices, payments, journal entries | Immutable after posting; corrections by reversal / refund / adjustment entries |
| Audit logs | Append-only; archived after 7 years (configurable) |
| Tenant export | `tenancy:export {branch}` (Part 7) produces per-branch SQL/CSV bundle |

---

## D. Multi-tenant & subdomain strategy

1. **Hierarchy:** *Platform → Institution (tenant) → Branch*. A single deployment serves many institutions; each has many branches.
2. **Resolution (`App\Tenancy\TenantResolver`, cached 10 min, busted on branch/domain save):**
   - Host (without port) in `tenancy.central_domains` → **central** context (`/super-admin`, public website).
   - Host exactly matches a row in `domains` → that branch (custom domains, e.g. `dhanmondi.shataj.edu.bd`).
   - Host ends with `.{tenancy.base_domain}` → sub-label = `branches.subdomain`.
   - Anything else → 404. Reserved labels (`www`, `api`, `admin`, `mail`) never map to branches; `www` redirects to the apex.
   - Branch `status=inactive` → 503 "branch unavailable" page; `suspended` institution → 402-style page.
3. **Context (`App\Tenancy\TenantContext`, request singleton, helper `tenant()`):** modes `none`, `branch`, `central`. `tenant()->runForBranch($branch, fn)` / `runCentral($institution, fn)` save & restore state — used by jobs, commands, seeders and tests.
4. **Isolation enforcement (defence in depth):**
   - `BelongsToBranch` trait → global `BranchScope`: branch mode ⇒ `WHERE branch_id = ?`; central mode ⇒ `WHERE institution_id = ?` (or no filter for platform owner); **mode `none` ⇒ `WHERE 1 = 0` (fail-closed)** so a forgotten context in a job or command returns nothing instead of everything.
   - `creating` hook fills `institution_id/branch_id` from context and **throws `CrossTenantViolation`** if a payload tries to write another branch's id.
   - Route-model binding runs through the scope ⇒ changing an ID in the URL returns **404**.
   - `EnsureTenantAccess` middleware re-validates on every request that the user may use the current branch (`branch_user` or `branches.access-all` in the same institution) and that the session/token was issued for this tenant (`session('tenant_key')`, Sanctum ability `tenant:<key>`).
   - Policies check permissions **and** ownership; menus are cosmetic only.
   - Session cookies are **host-only** (`SESSION_DOMAIN` empty) ⇒ a session from branch1 is not sent to branch2.
5. **Branch switching:** authorised users open `/switch-branch`; the app issues a one-time, 60-second **handoff token** (cache) inside a signed URL on the target host, which logs them in there and is then destroyed. Every handoff is audit-logged.
6. **Queues / scheduler / commands:** jobs use the `TenantAware` trait (stores `branch_id`) + `WithTenantContext` job middleware; commands iterate branches with `tenancy:run {command} --branch=*`. Scheduled jobs are idempotent via unique constraints (e.g. invoice per student per period).
7. **Files:** stored at `institutions/{id}/branches/{id}/{module}/…` on the private disk; downloads go through controllers that re-apply policies.
8. **Adding a branch** = insert row via the Super Admin UI (`BranchService::create`) — no code change. DNS wildcard already covers the new subdomain.
9. **Moving to database-per-tenant later:** (a) every tenant table already carries `branch_id`, so `mysqldump --where="branch_id=N"` exports a branch; (b) add `database` column to `branches`; (c) in `ResolveTenant`, after resolving the branch, call `DB::purge('tenant')` and set `database.connections.tenant.database` to the branch database; (d) set `$connection = 'tenant'` on tenant models (one line in `BelongsToBranch`); (e) central tables (institutions, branches, domains, users, roles, plans) stay on the `central` connection. Scopes remain as a second line of defence.

---

## E. Implementation roadmap (numbered parts)

| Part | Scope | Status |
|---|---|---|
| **0** | Architecture (this document) | ✅ |
| **1** | Laravel 12 skeleton, config, Vite/Bootstrap assets, docs | ✅ |
| **2** | Tenancy core, auth, RBAC (21 roles), branch & institution management, users & roles UI, handoff/switching, audit log, settings service, departments reference module, central + branch dashboards (Chart.js), API v1 auth/branches/departments, seeders (10 branches), tests | ✅ |
| 3 | Academic structure, admission & enrollment, SIS + import/export, staff/HR, attendance (manual/bulk/QR), timetable with conflict detection, examinations & grading engine (+tests) | ⏭ next |
| 4 | Fees, invoices (scheduled), payments, double-entry accounting, expenses, payroll, financial reports | |
| 5 | Notification hub (SMS/Email/WhatsApp adapters), payment gateways (SSLCOMMERZ, aamarPay, shurjoPay, bKash, Nagad), Zoom, Google Meet, LMS | |
| 6 | Library, transport, hostel, inventory/assets, public website CMS, certificates/ID cards (mPDF), reports & queued exports | |
| 7 | SaaS subscriptions, backups, performance & caching, monitoring, security hardening | |
| 8 | Final verification, full test run, deployment guide completion | |

---

## F. Security strategy

- **AuthN:** bcrypt (cost 12), login throttle 5/min per identifier+IP, password reset (Part 2 route stubs use Laravel broker), optional TOTP 2FA (Part 7), session regeneration on login, idle timeout 120 min.
- **AuthZ:** permissions `module.action`; `Gate::before` grants *super-admin* only; policies for every model; `permission:` middleware on route groups; API tokens scoped to tenant.
- **Tenant isolation:** section D.4; automated tests prove cross-branch access returns 404/403.
- **Input:** FormRequests on every write; Eloquent/bindings only (no raw interpolated SQL); Blade `{{ }}` escaping; uploaded files validated by MIME + size, stored privately with random names.
- **Secrets:** `.env` only; gateway/SMS credentials per branch stored with Laravel `encrypted` casts; never logged (audit logger masks `password`, `token`, `secret`, `*_key`).
- **Transport:** HTTPS + HSTS in production, `SESSION_SECURE_COOKIE=true`, `SameSite=lax`.
- **Webhooks (Part 5):** server-to-server validation with the gateway, signature/hash checks, idempotency via unique `(gateway, gateway_txn_id)`.
- **Audit:** model changes, logins/failed logins/logouts, handoffs, role changes.
- **Privacy (Bangladesh):** child data minimisation, restricted health info, consent/opt-out for messaging. *Institution must verify:* Bangladesh **Personal Data Protection** legislation status and obligations, Bangladesh Bank rules for payment aggregators/MFS, BTRC rules for bulk SMS masking, and education board record-retention rules.

---

## G. Environment & deployment setup (summary — full guide in `docs/INSTALLATION.md`)

- **Local (Windows/Laragon):** PHP 8.2+, MySQL 8, Node 20+, Composer 2. Branches at `http://branch1.localhost:8000` via `php artisan serve` (browsers resolve `*.localhost` to 127.0.0.1).
- **Production (Linux):** Nginx + PHP-FPM 8.3, MySQL 8, Redis, Supervisor (queue workers), cron (`schedule:run`), Certbot **wildcard** certificate via DNS-01.
- **DNS:** `A schooldomain.com → server`, `A *.schooldomain.com → server` (wildcard), optional CNAMEs for custom branch domains.
- **Key env vars:** `TENANCY_BASE_DOMAIN`, `TENANCY_CENTRAL_DOMAINS`, `TENANCY_CENTRAL_PATH`, `APP_TIMEZONE=Asia/Dhaka`, `APP_CURRENCY=BDT`, `DB_*`, `REDIS_*`, `QUEUE_CONNECTION`, `SESSION_DOMAIN` (keep empty).
