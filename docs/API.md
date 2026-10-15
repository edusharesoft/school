# REST API v1

Base URL = the tenant host: `https://branch3.schooldomain.com/api/v1` for a branch, `https://schooldomain.com/api/v1` for central.
The tenant is **always** taken from the host; there is no `branch_id` parameter to tamper with.

## Conventions

- Auth: `Authorization: Bearer <token>` (Laravel Sanctum). Tokens are bound to the host they were issued on (`tenant:<key>` ability) and expire after 30 days.
- `Accept: application/json` on every request.
- Envelope:
  ```json
  { "success": true, "message": "optional", "data": { }, "meta": { "current_page": 1, "per_page": 25, "total": 10, "last_page": 1 } }
  ```
  Errors: `{ "success": false, "message": "...", "errors": { "field": ["..."] } }` with 401 / 403 / 404 / 422 / 429 / 500.
- Pagination: `?page=2&per_page=50` (max 100). Rate limits: login 5/min per identifier+IP; API 120/min per user.

## Endpoints (Part 2)

| Method | Path | Permission | Notes |
|---|---|---|---|
| POST | `/auth/login` | — | Body `{login, password, device_name?}`; `login` = e-mail, phone or username. Returns `token`, `expires_at`, `tenant`, `user` |
| GET | `/auth/me` | authenticated | Includes roles and the full permission list |
| POST | `/auth/logout` | authenticated | Revokes the current token |
| GET | `/branches` | authenticated | Central host: branches visible to the user. Branch host: current branch only. Filter `?status=active` |
| GET | `/branches/{id}` | `branches.view` or current branch | |
| GET | `/departments` | `departments.view` | Branch host only. `?q=`, `?active=1` |
| POST | `/departments` | `departments.create` | `{name, name_bn?, code, description?, is_active?}` |
| GET | `/departments/{id}` | `departments.view` | 404 for another branch's id |
| PUT/PATCH | `/departments/{id}` | `departments.update` | |
| DELETE | `/departments/{id}` | `departments.delete` | Soft delete (archive) |
| GET | `/students` | `students.view` | Branch host. `?q=` (name/ID/birth-reg/mobile), `?status=`, `?class_id=`, `?section_id=` (current session). Includes `current_enrollment` |
| GET | `/students/{id}` | `students.view` | Adds guardians (name, phone, relation). Medical notes, guardian NID and document paths are never returned |

## Endpoints (Part 3B-1 — staff & attendance)

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/teachers` | `employees.view` | Active teachers of the branch. `?q=`, `?status=` |
| GET | `/employees` | `employees.view` | Teachers + staff. `?type=teacher\|staff`, `?q=`, `?status=` |
| GET | `/employees/{id}` | `employees.view` (or own record) | Salary, NID, addresses and documents are never returned |
| GET | `/attendance/students?section_id=&date=&subject_id=` | class teacher of the section, or `student-attendance.view` (back-office) | Register rows: `student_id, student_code, name, roll_no, status, remarks`; `meta.statuses` lists valid statuses |
| POST | `/attendance/students` | `student-attendance.mark` + class teacher of the section (teachers) | `{section_id, date: Y-m-d, subject_id?, entries: {student_id: {status, remarks?}}}` — same rules as the web register (today editable, limited back-dating, no future dates / weekly holidays) |
| POST | `/attendance/punches` | **device key** (`X-Device-Key`), no user token | Body `{punches: [{code, time}]}` (max 500, Asia/Dhaka local time). `code` = employee or student ID. Returns counts `applied / duplicate / unknown / ignored` and per-punch results. The key belongs to one branch; a key used on another branch's host is rejected (401). Re-sent punches are ignored (idempotent). Rate limit 60/min per device |
| GET | `/admissions` | `admissions.view` | Admission rounds of the branch (Part 3B-2) |
| GET | `/admissions/{id}/applications` | `admissions.view` | `?status=&q=&per_page=`; ordered by merit. Documents, photos and submitting IP are never returned |

**Public (no token, branch host, rate-limited):** `GET /admission` (open rounds), `POST /admission/{id}/apply` (online form), `GET /admission/status?application_no=&phone=`, `GET /admission/{id}/result` (published merit list — application numbers only), `GET /certificates/verify?code=`.

## Endpoints (Part 3C — examinations & results)

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/exams` | `examinations.view` | Exams of the branch with status (`draft … published`), dates and paper count |
| GET | `/exams/{id}/results` | `results.view` | Processed results (any status). `?class_id=&section_id=`; `meta.exam_status` tells whether they are published |
| GET | `/my/results` | student or parent login | Published results only: the student's own, or every linked child of a parent, with per-subject grades |

**Public (branch host, rate-limited):** `GET /results` (form), `POST /results` `{examination_id, student_code, date_of_birth}` → the mark sheet of a **published** exam (POST keeps the date of birth out of URLs and logs).

## Endpoints (Part 4 — fees)

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/fees/invoices` | `invoices.view` | `?student_id=&status=unpaid\|partial\|paid\|cancelled\|overdue&period=YYYY-MM&per_page=` |
| GET | `/payments` | `payments.view` | Receipts. `?from=&to=&student_id=&per_page=`; `advance_left` = unused advance credit of the receipt |
| GET | `/my/fees` | student or parent login | Due, overdue and advance per student (a parent gets every linked child) with invoices and receipts |

### Device example

```bash
curl -s -X POST https://branch1.schooldomain.com/api/v1/attendance/punches \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -H 'X-Device-Key: dev_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' \
  -d '{"punches":[{"code":"BR01-E0003","time":"2026-09-28 07:52:10"},{"code":"BR01-260014","time":"2026-09-28 07:55:02"}]}'
```

Keys are issued under **Attendance → Rules & devices** (shown once; only a SHA-256 hash is stored; rotate or disable at any time).

## Endpoints (Part 5-1 — notices & notifications)

| Method | Path | Who | Notes |
|---|---|---|---|
| GET | `/notices?category=` | any signed-in user | live notices of the user's audience (students, guardians, staff, teachers, classes), emergency and pinned first |
| GET | `/notices/{id}` | audience member | full text + attachment URL; records the read receipt; 404 outside the audience |
| GET | `/notifications?unread=1&per_page=` | any signed-in user | the user's in-app notifications (paginated) |
| POST | `/notifications/{id}/read` | owner | mark one notification read |

Webhook (not under `/api`): `GET|POST https://{branch-host}/webhooks/whatsapp` — WhatsApp Cloud API subscription check (`hub.verify_token`) and status updates (`X-Hub-Signature-256` HMAC with the app secret; invalid signature → 403).

## Endpoints (Part 5-2 — online fee payment, student / parent tokens)

| Method | Path | Notes |
|---|---|---|
| GET | `/my/online-payments/gateways` | enabled gateways of the branch: `[{key, name}]` |
| POST | `/my/online-payments` | `{student_id, gateway, invoice_ids[]}` (own child / own record, open invoices) → 201 `{tran_id, amount, redirect_url}`; open `redirect_url` in a browser |
| GET | `/my/online-payments/{tran_id}` | `{status, amount, paid_amount, gateway_txn_id, receipt_no, …}`; while the attempt is open the gateway is asked again |

Statuses: `initiated`, `pending`, `paid`, `failed`, `cancelled`, `expired`, `review` (reported by the gateway but held for the accounts office), `refunded`. After paying, the browser lands on `https://{branch-host}/online-payments/done/{tran_id}` (public, reference and status only); the app should poll the status endpoint.

Gateway callbacks (not under `/api`, no session, no CSRF): `GET /gateway/payment/callback?status=Complete|Failed&orderId=` (ShatajPay), `GET|POST /online-payments/{gateway}/return/{tran_id}/{success|fail|cancel}` and `POST /online-payments/sslcommerz/ipn`. Nothing they carry is trusted: each attempt is verified with the gateway's own API before a receipt is posted.

## Endpoints (Part 5-3 — learning, student / parent tokens, read-only)

A student login gets its own record, a guardian login gets every linked child (`data` is a list, one entry per student). Staff tokens get 404.

| Method | Path | Notes |
|---|---|---|
| GET | `/my/assignments` | homework / assignments / projects with the child's own submission (state, marks, feedback) |
| GET | `/my/materials` | shared class materials (documents, videos, links) grouped per student |
| GET | `/my/online-classes` | upcoming and recent classes; `join_url` and `passcode` are only returned from 15 minutes before the start until the class ends, recordings once available |
| GET | `/my/quizzes` | published quizzes: title, subject, window, time limit, attempts used, best result. Questions and answers are never returned (quizzes are taken on the web portal) |
| GET | `/my/learning` | progress summary: hand-ins, missing, average marks, quiz average, class attendance |
| GET | `/my/library` | per student: loans (title, accession no., due date, overdue flag, fine so far), reservations (queue / ready until), fines, and the borrowing limits. Read-only — reserving a book happens in the portal |
| GET | `/my/facilities` | per student: school transport (route, stop with pick-up / drop times, morning / afternoon start, vehicle, driver and helper name + phone, optional https `tracking_url`, monthly fee) and hostel (hostel, room, bed, warden, meal plan, monthly fee, last 7 roll-call marks). `transport` / `hostel` are `null` when the student has no seat / bed. Read-only |

## Example

```bash
curl -s -X POST https://branch1.schooldomain.com/api/v1/auth/login \
  -H 'Accept: application/json' -d login=admin.branch1@demo.test -d password=password -d device_name=mobile

curl -s https://branch1.schooldomain.com/api/v1/departments -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN"
```

Planned for later parts: `/reports` (Part 6).
