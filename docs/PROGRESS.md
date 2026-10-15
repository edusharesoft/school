# Progress checklist & file manifest

_Last updated: 2026-10-08 — Part 6d (certificates, ID cards & PDF)._

## Checklist

### Part 0 — Architecture ✅
- [x] Technical architecture, directory structure, schema/ERD, tenancy strategy, roadmap, security, deployment summary (`docs/ARCHITECTURE.md`)

### Part 1 — Project foundation ✅
- [x] Laravel 12 skeleton (slim `bootstrap/app.php`), Composer + npm manifests, `.env.example`, PHPUnit config
- [x] Config: `app` (Asia/Dhaka, BDT, en/bn), `database` (MySQL +06:00), `tenancy`, `school` (21 roles + permission catalogue)
- [x] Bootstrap 5 / Alpine / Chart.js design system via Vite, production build in `public/build`
- [x] English + Bangla (`lang/bn.json`, Hind Siliguri font), language switcher, dark mode

### Part 2 — Core platform ✅
- [x] Institutions, branches, domains (migrations, models, services, UI)
- [x] Host-based tenant resolution (central / custom domain / wildcard subdomain), cached, reserved names
- [x] `TenantContext` + fail-closed `BranchScope` + `BelongsToBranch` write guard + immutable tenant keys
- [x] Auth: login by e-mail/phone/username, throttling, password reset, tenant-bound sessions, auth audit
- [x] RBAC: 21 system roles, 171 permissions, custom roles UI (Super Admin), policies, `permission:` middleware
- [x] Branch access (`branch_user`), institution-wide access, secure branch switching (single-use handoff tokens)
- [x] Central user management (roles + branch assignment, escalation & cross-tenant guards)
- [x] Append-only audit log (model events, logins, role changes, handoffs) + viewer
- [x] Hierarchical settings service (branch → institution → platform, encrypted secrets)
- [x] Central dashboard (cards, 4 Chart.js charts, filters, drill-down, CSV export, loading/empty/error states, caching)
- [x] Branch dashboard; teacher/student/parent portal shells
- [x] Departments — reference tenant-owned module (web CRUD + API)
- [x] REST API v1: auth/login, me, logout, branches, departments (consistent JSON envelope)
- [x] Queue/scheduler tenancy: `TenantAware` jobs + `WithTenantContext`, `tenancy:run`, `school:create-super-admin`
- [x] Seeders: roles/permissions (idempotent), Super Admin, demo institution with 10 branches & role accounts
- [x] Tests: tenant resolution, isolation, authentication, authorization, central management, switching, API, dashboard, unit
- [x] Deployment: Nginx/Apache wildcard vhosts, Supervisor, deploy & encrypted backup scripts, installation guide
- [x] Test run on Laragon: 71/72 passed; the remaining failure (host guard vs. `auth` middleware order) fixed in `bootstrap/app.php`

### Part 3A — Academic structure & Student Information System ✅
- [x] Institution-wide academic years (central) + per-branch sessions (one current), terms
- [x] Classes (promotion order, group flag), sections (capacity, class teacher, room), study groups, subjects, subject combinations per class/group with full/pass marks, rooms
- [x] `BelongsToInstitution` scope for institution-wide tables; `TenantRule` validation (posted ids of another branch fail validation)
- [x] Concurrency-safe per-branch `sequences` (student IDs `BR01-260001`; reused later for admission/invoice/receipt numbers)
- [x] Students (permanent record, encrypted medical notes, private photo), guardians (sibling de-duplication by mobile), guardian links, documents (private disk, MIME-checked, authorised download)
- [x] Session-based enrollments: one per student per session, unique roll per section, capacity check, group rule, history kept
- [x] Search by name / ID / birth reg. / mobile / class / section / roll / session / status
- [x] Status lifecycle (withdrawn / transferred / graduated / suspended) closing the current enrollment
- [x] Bulk promotion / retention into the next session with automatic rolls (idempotent)
- [x] CSV import (all-or-nothing, dry run, per-line error report, template) and CSV export
- [x] Dashboards: active students, new admissions, boys/girls, left, students per branch (drill-down), students per class, admission trend
- [x] API: `GET /api/v1/students`, `GET /api/v1/students/{id}` (no health/NID fields)
- [x] Demo seed: year 2026, sessions, classes VI–XII, groups, 19 subjects, rooms, 24 students per branch
- [x] Tests: `AcademicStructureTest` (7), `StudentManagementTest` (16)

### UI refresh — same design as the Multi-Branch eCommerce platform ✅
- [x] Back-office theme: white boxed sidebar with colourful icons, collapsible groups, ☰ mini-sidebar (remembered), mobile slide-in; topbar with language, activity bell, user, logout (Font Awesome 6, Poppins / Hind Siliguri)
- [x] Menus defined as arrays in `layouts/central` and `layouts/branch`; items hidden by permission; "coming in part N" items shown disabled
- [x] Dashboards: 12 pastel statistic cards each + colour-headed chart panels; Chart.js styled like the eCommerce dashboards
- [x] Login / forgot / reset pages: full-screen photo + overlay, white card, logo + title, click-to-load YouTube video, icon input fields, show-password, "Developed By" footer
- [x] Institution self-registration (central domain, off by default): mobile, e-mail, institution name, subdomain with live availability check and Bangla → English auto-conversion; creates institution + first branch + Institution Administrator
- [x] Settings: central (platform or institution level — site name, logo, footer, login/registration backgrounds & YouTube videos, darkness, registration switch) and branch (profile, logo, colour, language, own login photo/video)
- [x] Tests: `BrandingAndRegistrationTest` (11)

### Part 3B-1 — Staff/HR, leave & attendance ✅
- [x] Designations (teaching / non-teaching, rank), employees (teachers + staff): generated IDs `BR01-E0001`, employment type, department, designation, joining date, qualification, experience, private photo, confidential NID + basic salary (`employees.view-salary`), emergency contact
- [x] Optional login account for an employee (teacher portal / back-office) with anti-escalation role check (only roles whose permissions the actor holds)
- [x] Employee documents (private disk, MIME-checked; NID copies need the salary permission), append-only employment history (joined, designation / department / type change, salary revision, resignation, termination, retirement, suspension, reinstatement); separation deactivates the login and revokes API tokens
- [x] Leave types (yearly allowance, paid / unpaid, unlimited), applications (full / half day, weekly holidays excluded, overlap and balance checks, no self-approval), approve / reject / cancel; approved leave is written into staff attendance and reverted on cancellation; balances per year
- [x] Student attendance: daily and subject-wise class registers (Present / Absent / Late / Half-day / Leave / Excused), "mark everyone", unique per student + day + slot, today editable, limited back-dating for missed registers, past records only through correction requests
- [x] Teacher portal: own sections (class teacher) only, register + correction requests + leave; teacher home shows today's register status
- [x] Scan station (USB/Bluetooth barcode & QR scanners, camera via BarcodeDetector): students → present/late by class start + grace; employees → check-in then check-out
- [x] Staff attendance: day register with in/out times, late / early-leave / overtime / worked minutes from branch rules, half-day rule, approved leave kept
- [x] Correction workflow (request → approve / reject, auto-approve for approvers, no self-review, staff can never approve their own attendance), audit-logged
- [x] Biometric / RFID / QR device adapter: `POST /api/v1/attendance/punches` with per-device hashed key, branch-bound, idempotent punch log, key rotation, enable/disable
- [x] Branch rules: class start, office hours, grace minutes, overtime threshold, half-day minutes, back-dating window, absence-alert threshold, weekly holidays
- [x] Consecutive-absence alerts (`attendance:absence-alerts`, scheduled 14:30 and 19:00 per branch, idempotent) + `ConsecutiveAbsenceDetected` event for guardian notifications (sent by the Part 5 notification hub)
- [x] Monthly sheets (students per section, staff) on screen, print and CSV; percentage rule documented
- [x] Dashboards: students present / absent / % today, teachers present / absent, employees, pending leave, absence alerts; daily attendance trend, class-wise %, staff status today; central branch-wise attendance comparison with drill-down
- [x] API: `GET /api/v1/teachers`, `GET /api/v1/employees(/{id})`, `GET|POST /api/v1/attendance/students`
- [x] Demo seed: designations, leave types, 13 employees per branch (demo logins linked), class teacher of Six-A, 10 school days of attendance, one absence streak, one pending leave
- [x] Tests: `HumanResourcesTest` (8), `AttendanceTest` (9)

### Part 3B-2 — Admissions, transfers, certificates ✅
- [x] Admission rounds per class & session: dates, seats, online on/off, age limits, admission test (date, venue, total & pass marks), admission fee (optionally required before admission), required documents, per-field form settings (hidden / optional / required), draft / open / closed
- [x] Public admission site on every branch host (`/admission`): open rounds, online form with document uploads, honeypot + rate limits, application numbers `BR01-A26-00042`, confirmation page, status check by application no. + mobile, published merit list (application numbers only)
- [x] Offline (walk-in) entry by the admission desk with the same form; duplicate (birth registration / name + DOB + guardian) and age checks
- [x] Review: verify, reject with reason, cancel; test-marks entry (absent), merit list (marks → ties by earlier application; no test → first come), selected up to free seats, waiting list with positions, promote from waiting list, publish / hide result
- [x] Admission fee hook: paid / waived / unpaid with receipt reference (Part 4 issues the invoice), enforced before admission when configured
- [x] Admit: student + guardian + enrollment through `StudentService::admit` (IDs, rolls, capacity), photo and documents copied to the student record
- [x] Branch → branch transfer inside the institution: request from the student profile, accept (new record + enrollment in the target, source becomes "transferred", guardians & photo copied) / reject / cancel; both branches see the history
- [x] Re-admission (same ID; same-session return reopens the old enrollment), withdrawal via status
- [x] Transfer / character / study certificates: rules (study = enrolled; TC = left or "mark as withdrawn"), serial numbers `BR01-TC-26-0001`, snapshot data, A4 print view, revoke, public verification page by code
- [x] Central staff branch assignments: employee of one branch works in another; login access granted and removed automatically
- [x] Menus: Admissions (applications, rounds, public page), Students → Branch transfers / Certificates, Central → Staff assignments; public website links to each branch's admission page
- [x] API: `GET /api/v1/admissions`, `GET /api/v1/admissions/{id}/applications`
- [x] Demo seed: one open Class Six round per branch with 12 applications, marks and merit list
- [x] Tests: `AdmissionTest` (9)
- [x] Fixes after the 3B-1 test run: numeric JSON assertion, closed registration → 404 before validation, wide registration card without video

### Part 3C-1 — Timetable, grading engine & exam set-up ✅
- [x] Bell schedule per shift (periods and breaks, order, times)
- [x] Class routine builder: section × day × period → subject, teacher, room; Saturday-first week without weekly holidays; empty cell = free period; all-or-nothing save that reports every teacher / room double-booking across sections and rejects teachers / rooms of other branches
- [x] Printable routines: section (print view), teacher-wise and room-wise; teacher portal "My routine"
- [x] Configurable grading scales (bands on %, grade point, remark, max GPA, 4th-subject base point, GPA decimals, round / floor, default scale); Bangladesh SSC/HSC scale created automatically
- [x] GPA engine `GradingService` (pure PHP): component pass rules (theory / practical / CA), absent / incomplete, compulsory fail ⇒ GPA 0 / F, optional-subject bonus above the base point, GPA cap, rounding — `tests/Unit/GradingServiceTest` (10)
- [x] Exams: types (class test, term, half-yearly, annual, pre-test, test, model), session / term, grading scale, dates, minimum attendance for admit cards, instructions
- [x] Papers per class: "add class" creates every class subject as a paper (theory 100 % or 75/25 theory/practical, pass 33 %), editable components, full / pass marks, 4th subject, counts-in-GPA, date & time; validation of pass ≤ full, dates inside the exam period, no overlapping papers for the same class & group
- [x] Status workflow: draft → scheduled → marks entry (and back); processing → approved → published is driven by results (3C-2); approved / published exams locked; only drafts deletable
- [x] Printable exam routine for all classes
- [x] Menus: Academics → Class routine / Periods, Examinations → Exams / Grading scales, teacher portal → My routine
- [x] Demo seed: bell schedule, routines for the first six sections, one scheduled half-yearly exam with three classes per branch (`DemoTimetableExamSeeder`)
- [x] Tests: `TimetableTest` (5), `ExaminationTest` (5)

### Part 3C-2 — Marks, results & exam documents ✅
- [x] Marks entry per paper & section (theory / practical / CA, absent, blank = not yet entered), keyboard-friendly sheet, progress per paper & section; the first marks move a scheduled exam into "marks entry"
- [x] Teachers (teacher portal) enter marks only for the subject & section pairs they teach in the session's class routine; exam controllers (`examinations.update`) for every paper
- [x] Mark corrections once marks entry is closed: request with reason → approve / reject (`marks.approve-correction`, no self-review; approvers' own requests apply at once); every approval re-processes the results and sends the exam back for approval
- [x] Result processing with the grading engine: component & overall pass rules, absent, incomplete (missing marks), optional 4th subject (only if taken), GPA with / without 4th, letter grade, failed-subject count, remarks, attendance % up to the exam; merit positions in class and in section (competition ranking, passed students first)
- [x] Workflow: marks entry → processing (re-process, reopen) → approved (`results.approve`) → published (`results.publish`, can be withdrawn); every step audit-logged; `ExamResultsPublished` event for the Part 5 notification hub
- [x] Result list with class tabs, section filter, pass rate, average GPA and grade distribution
- [x] Printables: tabulation sheet (A4 landscape), mark sheets / report cards (single or whole section, highest marks per paper, grading key), admit cards (paper schedule, instructions, photo, attendance eligibility with list of ineligible students), seat plan (classes interleaved, rooms by capacity); "NOT PUBLISHED" watermark until publication
- [x] Student & parent portal logins from the student profile (student ID / guardian mobile, password reset by the office)
- [x] Student portal: class, attendance %, upcoming exams with own admit card (eligibility enforced), published results & mark sheets
- [x] Parent portal: all linked children with class, attendance % and latest result; results & mark sheets per child
- [x] Public result lookup on every branch host (published exam + student ID + date of birth, POST, rate-limited)
- [x] Dashboard: grade distribution and class-wise pass rate of the latest published exam
- [x] API: `GET /api/v1/exams`, `GET /api/v1/exams/{id}/results`, `GET /api/v1/my/results`
- [x] Demo seed: a past "First term examination" per branch with marks, processed, approved and published; student + guardian portal logins (password `password`) — `DemoResultsSeeder`
- [x] Tests: `MarksAndResultsTest` (6)

### Part 4-1 — Fees, receipts & the double-entry foundation ✅
- [x] Double-entry ledger per branch: default chart of accounts (created on first use), financial years (July–June by default, configurable, closable), vouchers numbered per type & year (`BR01-RV2526-00001`), balanced-entry and open-year checks, posted entries never edited — reversals only
- [x] Chart of accounts screen with balances from postings; cash, bank and mobile-banking (bKash / Nagad / Rocket) accounts
- [x] Fee heads (admission, tuition, session, exam, lab, library, transport, hostel, development, fine, other), each with its own income account
- [x] Session-wise, class-wise (and group-wise) fee structure with billing months; copy to other classes
- [x] Student-specific scholarships, waivers, staff-child / merit / sibling discounts (percent or fixed, per head or all heads, chosen months) and extra fees (e.g. transport); automatic sibling discount for the 2nd+ child sharing a guardian
- [x] Monthly invoice generation (screen + scheduler on the 1st, idempotent per student & month), one-off invoices, due dates, invoice print (student & office copies)
- [x] Receipts: cash / bank / cheque / bKash / Nagad / Rocket / card, partial payments, selected or oldest-first settlement, over-payment kept as advance credit and applied to the next invoices automatically; money receipt print with amount in words
- [x] Receipt reversal (bounced cheque, wrong student) with reason — mirrors the receipt and every advance application; invoice cancellation (paid part becomes advance); waivers; refunds out of advance credit (refund voucher)
- [x] Late fees: fixed or percent, grace days, charged once per invoice by the nightly job or on demand
- [x] Student fee page: dues, advance, invoices, receipts & refunds, adjustments, student ledger from the books
- [x] Reports: daily / period collection by method, account & collector (CSV), outstanding dues with aging 0/1–30/31–60/61–90/90+ (CSV), discounts & waivers; everything from recorded transactions
- [x] Portals: student and parent fee status, invoices and receipts (online payment arrives with the gateways in Part 5)
- [x] Dashboard: collected today / this month, outstanding, overdue invoices, billed vs collected (6 months)
- [x] API: `GET /api/v1/fees/invoices`, `GET /api/v1/payments`, `GET /api/v1/my/fees`
- [x] Demo seed: fee heads, structures for every class, scholarships, a transport user, three months of invoices and receipts per branch (`DemoFinanceSeeder`)
- [x] Tests: `FeesTest` (10) — generation & idempotency, discounts, sibling rule, partial/advance/credit, reversal, cancellation & refund, late fee & waiver, ledger rules & closed years, access, fee structure screen; every scenario checks that the books balance and that the receivable / advance accounts equal the open invoices / unused credit

### Part 4-2 — Expenses, payroll & accounting reports ✅
- [x] Suppliers / payees (name, phone, address, BIN/TIN)
- [x] Expense vouchers: expense account, supplier, bill / cheque no., attachment (PDF / image on the private disk), paid now (cash / bank / mobile) or on credit; request → approve (posted) or reject; nobody approves their own request; credit bills paid later (payment voucher); reversal with reason (mirror entries)
- [x] Manual vouchers — journal, receipt, payment and contra (contra only between cash / bank / mobile accounts); balanced-entry check, no future dates, reversal of manual vouchers only (fee / expense / payroll vouchers are corrected from their document); day book with filters and voucher print
- [x] Payroll: salary structure per employee (basic, allowances, deductions, payment method & account), monthly salary sheet from the structures; absent days without leave and leave without pay (from staff attendance) deducted at basic ÷ days-basis (settings); bonus / arrear and extra deduction per line while in draft; approval by someone other than the preparer posts Dr Salaries / Cr Salaries payable + Cr Salary deductions payable (PF, tax …); payment from cash / bank; cancel reverses everything; printable salary sheet and salary slips (amount in words)
- [x] Reports from the books: general ledger per account (opening, running balance, closing), trial balance, income & expenditure, balance sheet (accumulated surplus, balance check), cash flow (direct method, contra excluded), budget vs actual per financial year, bank reconciliation (tick cleared lines, cleared balance vs statement)
- [x] Financial years: list, close after audit (posting refused), reopen (needs accounting.reverse); report pages keep working in a closed year
- [x] Central area: consolidated finance — every branch's income, expenditure, surplus, fees collected, fees outstanding, cash & bank side by side with totals, and the consolidated income & expenditure by account
- [x] Permissions: `payroll.approve` added to branch-admin and principal (re-run `RolesAndPermissionsSeeder`)
- [x] Demo seed: opening balances, suppliers, salary structures for every employee, last month's salary sheet approved and paid, expenses (one pending, one on credit), budget (`DemoAccountingSeeder`)
- [x] Tests: `AccountingTest` (6) — expenses (self-approval, credit + payment, reject, reversal, attachment), payroll (unpaid days, adjust, second approver, pay, cancel, re-prepare), manual vouchers (balance, contra rule, future date, reversal), statements agree (trial balance, I&E, balance sheet, cash flow, ledger), budget / reconciliation / year closing, central consolidated report (institution isolation)

### Part 5-1 — Notification hub & notice board ✅
- [x] One notification hub for **in-app, SMS, e-mail and WhatsApp**: provider adapters (SSL Wireless, BulkSMSBD, Alpha SMS, "log only"; branch SMTP or the app mailer; WhatsApp Cloud API templates), per-branch settings with encrypted secrets, channels per event, test message
- [x] Bangla and English templates for every event with `{variables}` (defaults in `config/notifications.php`, branch overrides in the template editor, unknown-variable warning); each recipient gets the language of their portal account, otherwise the branch default
- [x] Events wired: admission application received, admission confirmed, fee invoice issued, fee payment received, weekly overdue-fee reminder (optional), consecutive absence, results published, notice published, bulk message
- [x] Queue-based sending with atomic claim (duplicate jobs harmless), retry with back-off for transient errors (3 attempts), immediate failure for configuration errors, manual retry / retry-all-failed, cancel; de-duplication per source × recipient × channel
- [x] Bulk & scheduled messages to all / staff / teachers / students / guardians / classes / sections / chosen students; SMS part counter (Bangla 70/67, English 160/153)
- [x] Delivery tracking: status history per message, WhatsApp webhook with signature check (sent → delivered → read, never backwards)
- [x] Consent & opt-out: office opt-out list, guardians and students choose SMS / WhatsApp / e-mail in their portal; emergency notices and messages still reach everyone
- [x] Message history with filters, message detail, usage report (per month × channel, billable SMS parts, per event)
- [x] In-app inbox for every portal with a bell badge, polling and optional desktop (browser) notifications
- [x] Notice board: categories, audiences (everyone, staff, teachers, students, guardians, classes/sections, chosen students), emergency & pinned notices, attachment (private disk), publish now or at a time, expiry, archive, read receipts, distribution by in-app + chosen channels once when it goes live
- [x] Institution-wide notices written in the central area and copied to the chosen branches
- [x] API: `GET /api/v1/notices`, `GET /api/v1/notices/{id}`, `GET /api/v1/notifications`, `POST /api/v1/notifications/{id}/read`
- [x] Scheduler: `notifications:dispatch` every minute, `notifications:fee-reminders` daily
- [x] Demo seed: notices per branch (`DemoCommunicationSeeder`)
- [x] Tests: `NotificationsTest` (9), `NoticesTest` (6), unit `NotificationHelpersTest` (2)

### Part 5-2 — Online payment gateways ✅ (verified: full suite 200 tests green in the sandbox with the project's vendor)
- [x] **Shataj SMS (our own SMS gateway)** added as an SMS provider: form POST to `{server}/services/send.php` (key, number, message, devices `id` / `id|sim`, type sms), delivery status pulled from `/services/read-messages.php` by the minute scheduler (sent → delivered / failed); tests in `NotificationsTest` (2)
- [x] **ShatajPay (our own gateway) is the default**: listed first and preselected in the portals; headers `username/password/appkey/appsecret/gatewayid/sandbox`, random UUID `orderId`, fixed callback `/gateway/payment/callback?status=&orderId=`. The callback is unsigned and ShatajPay has no status API yet, so "Complete" is held for review (the office confirms in the ShatajPay panel and accepts); "Failed" is believed. **Pending on ShatajPay's side: a server-to-server verification API** — once published, only `ShatajPayGateway::verify()` changes
- [x] One gateway interface (`initiate` / `verify` / `refund`) with further adapters for **SSLCOMMERZ v4**, **bKash Tokenized Checkout v1.2.0-beta**, **Nagad merchant checkout** (RSA-encrypted + SHA256-signed requests, signed answers verified), **shurjoPay v2** and **aamarPay JSON API**; sandbox / live per gateway and branch
- [x] Per-branch settings (Fees & accounts → Online payment settings): enable, sandbox, credentials (secrets encrypted, never shown or flashed back), deposit account (default bank / mobile wallet)
- [x] Portal: parents (per child) and students choose open invoices and a gateway on the Fees page; payment status page; recent attempts; public result page for payments started from the mobile app
- [x] Money is posted **only after server-side verification** with the gateway (validation / execute / status / verification APIs). The browser's success page is never trusted; forged SSLCOMMERZ IPNs (bad `verify_sign`) → 403
- [x] Idempotent: repeated returns / IPNs, row lock on finalize, unique gateway transaction id; tran id and result are in the callback path so the gateway's own query string cannot break them
- [x] Held for review instead of posted: amount mismatch, non-BDT currency, duplicate gateway transaction, risk flag, or a receipt that cannot be posted (closed year); the office accepts (posts the receipt, surplus becomes advance) or rejects
- [x] Reconciliation every ten minutes (`payments:reconcile`): re-asks the gateway about open attempts (and failed / cancelled ones for an hour), expires abandoned checkouts
- [x] Refunds: bKash and SSLCOMMERZ through their API; shurjoPay, aamarPay and Nagad from the merchant panel with the reference recorded; the money receipt is reversed in the same transaction (invoices become due again)
- [x] Office screens: online payments list (filters, totals per gateway / status, review badge in the menu), detail with gateway log (secrets redacted), re-query, review decision, refund
- [x] API: `GET /api/v1/my/online-payments/gateways`, `POST /api/v1/my/online-payments`, `GET /api/v1/my/online-payments/{tran}`
- [x] Tests: `OnlinePaymentsTest` (10) — ShatajPay and the five other gateways with faked HTTP (Nagad with generated RSA keys), idempotency, forged IPN, success-page-only, review + accept, bKash refund, reconciliation and expiry, access rules, encrypted settings, API flow

### Part 5-3 — LMS, Zoom & Google Meet
**5-3a — Homework, assignments, materials, progress ✅** (`LearningTest`, 7 tests)
- [x] Homework / assignment / project: draft → published (students and guardians notified) → closed / reopened; due date, optional marks, submission mode (file / text / either / none), late hand-in policy, attachment
- [x] Student hand-in (file or text, late detection, resubmission after "returned"), teacher evaluation (marks, feedback, return for revision), files on the private disk behind authorised downloads
- [x] Class materials: documents, videos, recorded lessons and links, shared per class / section / subject; hidden until shared
- [x] Teachers only see and change their own work and only for the sections they teach (class teacher or timetable); back office sees everything; parents see only their own children
- [x] Learning progress for teachers (per section), students and parents (hand-ins, missing, late, average marks by subject)

**5-3b — Online / offline classes, Zoom & Google Meet ✅** (`OnlineClassTest`, 8 tests — **all provider calls are faked with `Http::fake`; no live Zoom or Google account has been used, so run the "Test connection" button with your own credentials before relying on it**)
- [x] Schedule online (Zoom, Google Meet or a pasted link) and offline (room) classes; edit / reschedule / cancel / complete; students and guardians notified of new, moved and cancelled classes
- [x] Zoom: Server-to-Server OAuth (account credentials), create / update / delete meetings, join + host link + passcode, cloud recording link, token cached encrypted
- [x] Google Meet: OAuth authorisation-code flow with offline access (state-checked, redirect URI configurable per branch), Calendar API event with `conferenceData` (`hangoutsMeet`), update / delete, automatic access-token refresh
- [x] Per-branch connection screen (Learning → Zoom & Google Meet): secrets are encrypted at rest, write-only in the form, never in the audit trail; test-connection, disconnect
- [x] A provider outage never loses a class: it is saved with `sync_status = failed`, shown with a "Try again" button
- [x] Students / parents: upcoming classes with the join link from 15 minutes before the start, recordings, attendance history; teachers take class attendance, which feeds the learning progress
**5-3c — Quizzes & learning API ✅** (`QuizTest`, 7 tests)
- [x] Quiz builder: single-choice, multiple-choice and true/false questions with marks and explanation; time limit, attempt limit, availability window, shuffled questions, pass mark, "show answers" (never / after submit / after close); draft → published (students and guardians notified) → closed / reopened
- [x] Questions are locked once a student has attempted the quiz (results stay comparable)
- [x] Automatic grading (a question is right only when the chosen answers match exactly); best attempt counts; the clock is kept on the server (expires at the earlier of time limit and closing time, 30 s grace), progress is autosaved, a timed-out attempt is submitted automatically
- [x] Student: list, start, attempt page with countdown, result with review; guardian: child's quiz results (answers shown only according to the quiz setting)
- [x] Teachers only manage their own quizzes for the sections they teach; quizzes feed the learning-progress cards
- [x] Read-only mobile API: `GET /api/v1/my/{assignments,online-classes,materials,quizzes,learning}` for students and guardians (join link only from 15 minutes before the start; never any quiz answers)

### Branch websites (each branch has its own public site) ✅ (`BranchSiteTest`, 14 tests)
- [x] The branch address (e.g. `dhaka.schooldomain.com`) now shows the branch's own website instead of the login page — hero + three boxes, "Discover our School", Message, Our Team, Our Teacher, Our Branches (3×3), All Branch Locations map, Latest News & Blog, partner strip, footer; top bar with Login, menu with About Us / Academic / Regulations / Notices / Contact and the Apply button (admission page)
- [x] Branch administrator edits it all under **Website → Site settings** (pictures, texts, up to 2 messages, 6 team members, 8 teachers — optionally filled from an employee, whose photo is then copied for the public site), **Pages** (with a "Create standard pages" button) and **News & notices**
- [x] Every branch's content is separate: pages and posts carry `branch_id`; the institution site never shows branch posts and vice versa; a branch administrator gets 404 on another site's content; hero / partner logos / social / footer fall back to the institution's when the branch has none
- [x] Public notices of a branch live at `/public-notices` (the signed-in notice board keeps `/notices`)
- [x] Newsletter box on the branch site subscribes the address to the institution's list (no mail is sent yet, as on the main site)

### Part 6 — Operations: library, transport, hostel, inventory & assets, documents, reports
**6a — Library ✅** (`LibraryTest`, 12 tests)
- [x] Catalogue: categories, titles (Bangla + English title, authors, publisher, edition, language, shelf, price) with ISBN-10/13 validation; any number of physical copies per title, each with its own accession number (`LB-000001`, a per-branch sequence) and a Code 128 barcode (rendered as inline SVG — printable label sheet per title, no image library needed)
- [x] Circulation desk: pick a student or staff member, scan a barcode to issue (due date from the branch rules, or chosen), return by scanning (good / damaged / lost), renew; two desks can never issue the same copy (the copy row is locked in a transaction)
- [x] Rules per branch (Library → Rules): books at a time and loan days for students and for staff, renewals, fine per day, grace days, fine cap, lost-book charge (% of price), how long a returned copy is held for the next person, reminder interval, block new loans while overdue / while fines are unpaid
- [x] Fines: overdue (per day), lost, damaged (manual amount); collected at the desk — posted to the accounts (Dr cash/bank/mobile, Cr "Late fees & fines") with a receipt number `LF-yy-00001` and a printable receipt; waiving needs `library.delete` and a reason and posts nothing
- [x] Reservations: when every copy is out, members queue; a returned copy is held for the first in the queue (notified), uncollected holds expire and pass to the next person; cancelling a held reservation releases the copy
- [x] `library:housekeeping` (07:30 daily via `tenancy:run`) expires holds and sends overdue reminders (`library.overdue`, `library.reservation_ready` notification events — in-app + SMS, Bangla/English templates)
- [x] Portals: student sees loans, due dates, fine so far, reservations and can search the catalogue and reserve; guardian sees each child's library account (read-only); `GET /api/v1/my/library`
- [x] Library home with figures, due-today list and most-borrowed titles; per-member library account page
- [x] Library reports with PDF/Excel export — done in the reports centre (6e)

**6b — Transport & hostel ✅** (`TransportHostelTest`, 19 tests)
- [x] Transport: vehicles (type, capacity, fuel, insurance / fitness / tax-token / route-permit expiry with a 30-day warning, maintenance log, fuel log), drivers and helpers (licence number and expiry — a licence is required for drivers; optionally linked to an employee), routes with ordered stops (pick-up / drop times, optional per-stop fee, morning / afternoon start) and a printable route sheet with the riding students
- [x] Seats: a student takes a seat at a stop; the route's vehicle capacity is enforced under a row lock (two clerks can never fill the last seat twice), one active seat per student, retired vehicles / out-of-service routes refuse new seats; transfer to another stop / route and ending a seat keep the history; the seat keeps the fee it was allocated at (stop fee → else route fee)
- [x] Driver and helper daily attendance (present / absent / leave, one row per person and day)
- [x] Hostel: hostels (boys / girls / mixed, warden from the employees), rooms with building / floor / type / monthly fee, any number of beds per room (a bed can be taken out of use), meal plans with a monthly fee
- [x] Bed allocation: one student per bed and one bed per student (row lock), boys' / girls' hostels only accept the matching gender, transfer (the meal plan follows the student), vacate with a reason, change the meal plan; hostel roll call (present / absent / leave / late) accepts residents only; visitor check-in / check-out; maintenance requests with priority, status, cost and resolution
- [x] Fees are **normal fee invoices** of the student (fee heads of category *transport* / *hostel* are created on first use with their own income account), so they show up in dues, receipts, the ledger, reminders and the portals like any other fee. One invoice per seat/stay and month (`generated_key` `transport:{id}:{YYYY-MM}` / `hostel:{id}:{YYYY-MM}`): generating twice never bills twice. A month a seat or stay touches is billed **in full — no pro-rating** (decision; change `generateFees` if a school wants day-based billing). Rooms and meals are separate lines of one hostel invoice
- [x] `services:generate-fees` (1st of the month, 06:30, via `tenancy:run`) creates both kinds of invoices for every branch whose "automatic invoices" switch is on (`--period=YYYY-MM`, `--force` to ignore the switch); also available as a button under Transport → Fees and Hostel → Fees
- [x] Portals: student and guardian "Transport & hostel" page (route, stop, times, driver / helper phone, vehicle, bed, warden, recent roll call; read-only) and `GET /api/v1/my/facilities`
- [x] Live tracking: **the platform does not track vehicles.** A vehicle may carry an optional `https` tracking link of the school's own GPS provider; guardians of riding students get a "Track the bus" button to it. Real GPS integration is out of scope
- [x] Costs of fuel and maintenance are recorded for reporting only — they are not posted to the accounts (record them as expenses in Fees & accounts if needed)
- [x] Transport / hostel reports with PDF/Excel export — done in the reports centre (6e)
**6c — Inventory & assets** (split in two)
**6c-1 — Inventory ✅** (`InventoryTest`, 17 tests)
- [x] Item catalogue: categories (general / laboratory, optional expense account charged on issue), items (`IT-000001` per-branch code, Bangla + English name, unit, consumable / durable, reorder level, shelf), opening stock posted as a stock adjustment against the opening balance
- [x] Stock keeping at **moving weighted-average cost**: every change is an immutable stock-card line (`stock_transactions`) with signed quantity / value and running balances; stock never goes below zero; the last unit takes the value that is left so nothing is stranded; item page = stock card with brought / carried forward
- [x] Purchase requests (pending → approved / rejected / cancelled → ordered) → purchase orders (draft → approved → partial → received / cancelled, printable) → goods receiving (with or without an order, partial receipts, no over-receipt, bill number, **credit or paid now**) → supplier payments against a receipt (part payments, no overpay). Nobody approves their own request or order. Suppliers are the existing accounts `vendors`, with what the branch owes each
- [x] Stock issues of consumables to a department / employee / named recipient at average cost (duplicate lines add up), charged to the category's expense account (default "Consumables & supplies used"); reversal puts the goods back at the same value
- [x] Stock adjustments (opening, damage, loss, expired, found, recount, other) — approve permission; stock audits: count sheet of all or one category → enter counts → complete = one "recount" adjustment of (counted − stock on hand now); uncounted lines are untouched
- [x] **Books stay in step**: new system accounts *Inventory stock* (asset), *Consumables & supplies used* and *Stock adjustments* (expenses). Receipt Dr stock / Cr payable (or cash / bank); payment Dr payable / Cr cash / bank; issue Dr expense / Cr stock; shortage Dr adjustment / Cr stock. Every document can be reversed (mirror entries, stock moved back at the original value — refused if the goods were already used or a payment is attached). A test asserts after each scenario that Σ item values = Inventory stock account = Σ stock card
- [x] Reports: stock & valuation (by category, low / out of stock), consumption (by item and by department), purchases & supplier dues; home with figures, shortages and latest movements; **central "Branch-wise inventory"** (stock value, shortages, open orders, bought this year, owed to suppliers per branch)
- [x] Role *Store Keeper* added (re-run `RolesAndPermissionsSeeder`); existing `inventory.*` grants of Branch admin and Lab manager apply. `inventory.approve` is needed for adjustments, audit completion, supplier payments, reversals, and for approving requests / orders
- [x] Demo seed `DemoInventorySeeder` (categories, items with opening stock, suppliers, a credit and a paid purchase, an approved order waiting for goods, a pending request, issues)
- [x] Durable equipment goes to the **asset register (6c-2)**: *Items → Move to asset register* takes units out of stock at their average cost and registers one asset per unit (durable items cannot be issued as consumables)
- [x] Inventory reports with PDF/Excel export — done in the reports centre (6e)
**6c-2 — Asset register ✅** (`AssetsTest` 19 + `AssetsWebTest` 7 tests)
- [x] Asset categories (general / laboratory) carry the default depreciation method, useful life, salvage % and rate; every asset keeps its own copy. Assets get a per-branch tag `AS-000001`, Bangla + English name, brand / model / serial, room / location, condition, warranty date and laboratory safety notes
- [x] Registering: **purchase** (Dr Fixed assets / Cr cash, bank or mobile), **donation** (Cr Other income) and **opening** (assets already owned: Cr Accumulated depreciation + Cr Opening balance); up to 50 identical units at once. Credit purchases go through Inventory → Goods receiving, then **Move from stock** (Dr Fixed assets / Cr Inventory stock at average cost). Registration can be cancelled (books reversed) only while the asset is untouched
- [x] Allocation: hand over to a department, employee, room or named person with an expected return date; one holder at a time; take-back with return condition; overdue returns listed. Assets under repair or marked missing cannot be handed out
- [x] Maintenance & calibration: recurring plans (preventive / calibration / inspection / safety, every 1–120 months, responsible employee, vendor) → "done" logs the job and moves the next date; work orders (repair / upgrade …) open → in progress → completed / cancelled, optionally taking the asset out of service meanwhile. Costs are **recorded for reporting only — not posted to the books**. `assets:housekeeping` (07:45 via `tenancy:run`) sends the `assets.maintenance_due` notification to the responsible employee, at most once a week per task
- [x] Depreciation: whole-month convention from the month the asset enters service; straight line (last month takes the remainder) or declining balance (floored at salvage); a run charges every missed month, one journal per month (Dr Depreciation / Cr Accumulated depreciation, dated month-end, or today for the current month). A run can be reversed only while it is the latest charge of each asset. Method / life / salvage are locked once depreciation has posted. 12-month forecast per asset
- [x] Disposal: sale, scrap, donated, lost or stolen — outstanding depreciation is caught up first, then Dr money / Dr Accumulated depreciation / Dr Loss / Cr Fixed assets / Cr Gain. Reversal brings the asset back. Needs `assets.approve`
- [x] Physical verification: count sheet by category / room → found / missing / damaged per asset → completing stamps the verification date, updates location and condition and flags missing assets (nothing is written off automatically)
- [x] Printable Code 128 asset tags, CSV export of the register, reports (summary by category / kind, depreciation by period, maintenance), overview with the register cross-checked against the *Fixed assets* and *Accumulated depreciation* accounts (warning banner on a difference), **central "Branch-wise assets"**
- [x] New system accounts: *Fixed assets* 1700, *Accumulated depreciation* 1790, *Asset disposal gain* 4950, *Depreciation* 5650, *Asset disposal loss* 5960. Permissions `assets.*` for branch admin; lab manager can view / create / update; store keeper and principal view (principal also approves). Re-run `RolesAndPermissionsSeeder`
- [x] Demo seed `DemoAssetsSeeder` (5 categories, ~10 assets, hand-overs, an overdue calibration, an open repair, depreciation to last month)
- [x] Assets reports with PDF/Excel export — done in the reports centre (6e)
**6d — Certificates, ID cards & PDF ✅** (`DocumentsTest`, 11 tests; needs `composer require mpdf/mpdf`)
- [x] PDF engine: `PdfService` wraps **mPDF** (UTF-8, Bangla shaping via OpenType, private temp folder, watermark, images embedded as data URIs). mPDF's bundled FreeSerif has Bangla glyphs; for a nicer face put `NotoSansBengali-Regular.ttf` / `-Bold.ttf` (or names set in `config/pdf.php`) into `storage/fonts`. Without the package a clear message is shown. Tests replace the service with a recorder, so the suite does not need mPDF
- [x] Document settings (*Students → Document settings*): signatory name / title, **signature** and **seal** images, accent colour, certificate border (classic / modern / plain), footer line, ID card colour / validity / back text / emergency phone / show blood group & guardian phone — per branch
- [x] Certificates: A4-landscape **PDF** of transfer / character / study certificates with letterhead, serial, signature, seal, QR to the public verify page and a REVOKED watermark; new type **Certificate of Achievement** (`AC`, "awarded for" + details). The print view and the PDF share one wording partial
- [x] ID cards for students (current session, by class / section / search) and staff: CR80 54 × 85.6 mm front (photo, name, class / roll or designation, ID, blood group, guardian phone, Code 128 of the student / employee code — the scan station reads it) and back (rules, address, emergency phone, **QR → public verification**, valid until, signature). Layouts: **A4 sheet 3 × 3** (back page mirrored for duplex) or one card per page for card printers; front only or both sides
- [x] ID card register: printing issues `IDS-000001` / `IDE-000001` once (reprints only count), validity (default 12 months, end of month), lost / revoked / **replaced** (a new card voids the old one; an expired card is replaced on the next print). Permissions `id-cards.view / issue / revoke` (branch admin, principal). Public page `/id/verify?code=` shows valid / expired / lost / replaced with the holder's name and role only
- [x] Admit cards (2 per A4 page, eligibility rule as on screen) and mark sheets (one student or a whole section, one page each) as PDF from the exam screens
- [x] Done in 6e: staff certificates (experience / service) and the portal "download my mark sheet as PDF"
- [ ] Not done: photo cropping for ID cards
**6e — Reports centre, exports & staff certificates ✅** (`ReportsTest` 18 tests, `StaffCertificatesTest` 4, `DocumentsTest` +1; Excel needs PHP `zip`, PDF needs mPDF)
- [x] One framework: every report is a class (`app/Services/Reports/Definitions/**`) that declares permission, filters and builds a `ReportResult` (typed columns, rows, totals, subtitle, summary, notes). The same result feeds the screen, **CSV** (UTF-8 BOM, formula-injection guard), **Excel .xlsx** (own writer on `ZipArchive`, no library: numeric/date/money styles, frozen header, autofilter, totals, Bangla text) and **PDF** (mPDF via `PdfService`, landscape above 6 columns, page-numbered footer)
- [x] 31 reports in 11 groups: students (list), attendance (student / staff monthly), fees (collection, dues ageing, discounts & waivers), accounts (trial balance, income & expenditure), staff list, library (issued, overdue, catalogue & stock, fines, most borrowed), transport (roster, vehicle & driver documents, running costs), hostel (residents, occupancy, attendance, visitors), inventory (stock & valuation, consumption, purchases & supplier dues), assets (register, summary, depreciation, maintenance) and central **branch comparison** (assets, inventory, finance)
- [x] Reports centre in the branch menu (*Reports centre → All reports / My exports*) and in the central menu (branch-comparison reports only; scope follows the host). `reports.view` opens the centre and each report's own permission opens that report (the list shows only what the user may open). Export needs `reports.export`, or `financial-reports.export` / `students.export` for finance and student reports. Filters are normalised (dates swapped, range capped at 366 days, unknown values dropped)
- [x] Background exports: *Export → Prepare in the background* (and automatically above 3 000 rows) creates an `ExportRun` and a queued `RunReportExport` job; the finished file waits on the private disk for 7 days, only its owner can see or download it, and an in-app message (`reports.export_ready` / `reports.export_failed`) announces it. At most 3 pending per user, 100 000 rows per export; stuck runs fail after 6 hours; the daily `reports:housekeeping` removes expired files
- [x] Portal: students and parents can download a **published** mark sheet as PDF (own / own children's only, 404 otherwise)
- [x] Staff certificates (*Staff & HR → Staff certificates*, or the Certificate button on an employee): **service** certificate for working staff, **experience** certificate for staff who have left; serial `BR-SV-26-0001` / `BR-EX-26-0001`, snapshot of the facts (salary is never printed), conduct / purpose / remarks, PDF with the branch letterhead, signature, seal and QR, REVOKED watermark, and public verification on `/certificates/verify` (codes are unique across student and staff certificates). Permissions `staff-certificates.view / issue` for branch admin, principal and HR manager
- [ ] Not verified: real mPDF output (Bangla shaping, barcode / QR sizes, page layout) of the report PDF and of all 6d/6e documents — the tests use a recorder in place of mPDF

- [x] 6e Reports & queued exports
**7 — SaaS subscriptions, 2FA, backups, monitoring & performance ✅** (`SubscriptionsTest` 7, `TwoFactorTest` 7, `OperationsTest` 11)
- [x] Subscriptions: plans (price monthly / yearly, branch limit, active-student limit, trial days, grace days), one subscription per institution (trial / active / suspended / cancelled), manual payment records that extend the period, phases trial → active → **grace** (works, warning banner) → **expired** (read-only: every change refused, HTTP 402 for the API, nothing deleted). Limits checked on student admission and branch creation; no subscription = unlimited. Self-registration starts a trial on the first active plan with trial days. Platform owner only (`subscriptions.*`): *Central → Subscriptions / Plans*
- [x] Two-factor authentication (TOTP, RFC 6238, no package): *Account security* page for every user, 8 one-time recovery codes, sign-in challenge step (throttled, codes cannot be replayed), API login takes `otp`, mandatory per role via `SECURITY_2FA_REQUIRED_ROLES`, admin reset button for lost phones, secrets encrypted and audited
- [x] Backups: `backup:run` (nightly, retention 30 days / newest 3), one zip with every table as JSON lines + uploaded files + manifest (row counts, SHA-256), optional AES-256 with `BACKUP_PASSPHRASE`; `backup:verify` (checksums) and **`backup:verify --drill`** (restore into a scratch database built from the migrations and compare every table — weekly by schedule); `backup:restore <id|file.zip> --force` for disasters; Backups page (run now, test restore, download, delete) for the platform owner
- [x] Monitoring: `HealthService` (database, cache, storage + free disk, queue, scheduler heartbeat, backup age, restore-drill age, subscriptions, configuration, slow queries), *System health* page, `/health/status?token=` for uptime monitors (200 / 503), `monitor:check` every 10 minutes with e-mail alerts
- [x] Performance: `performance:audit` (unindexed `*_id` / `status` columns, biggest tables), migration `add_performance_indexes` for what it found, slow-query log (`PERF_SLOW_QUERY_MS`)
- [x] Docs: `docs/OPERATIONS.md` (runbook: processes, backups, restore drill, disaster recovery, monitoring, 2FA, subscriptions)
- [ ] Not verified: restore drill into a real scratch MySQL database; a full disaster-recovery rehearsal on a spare server
**8 — Final verification & handover ✅**
- [x] Full automated suite green (see the count in `docs/HANDOVER.md` §8); every route is behind `auth` + `tenant.access` or a signed / throttled public endpoint; every new `__()` string has a Bangla translation
- [x] `docs/HANDOVER.md`: modules, first-day checklist, scheduled jobs, roles, security summary, known limits, troubleshooting table
- [ ] Still to do by the owner on the real machine: PDF visual check with real mPDF, one live sandbox payment per gateway, SMS gateway test, real disaster-recovery rehearsal

## File manifest — Part 7 (SaaS, 2FA, backups, monitoring, performance)

**migrations** — `database/migrations/2026_12_01_000100_create_subscription_tables.php`, `…000200_add_two_factor_to_users.php`, `…000300_create_backup_runs_table.php`, `…000400_add_performance_indexes.php`
**config** — `config/security.php`, `config/backup.php`, `config/monitor.php`, `routes/console.php` (backup, drill, heartbeat, monitor), `.env.example`
**subscriptions** — `app/Models/Saas/{Plan,Subscription,SubscriptionPayment}.php`, `app/Services/Saas/SubscriptionService.php`, `app/Http/Middleware/EnforceSubscription.php`, `app/Http/Controllers/Central/Saas/{PlanController,SubscriptionController}.php`, `resources/views/central/saas/**`, `resources/views/partials/subscription-banner.blade.php`; hooks in `app/Services/Tenancy/BranchService.php`, `app/Services/Students/StudentService.php`, `app/Services/Registration/InstitutionRegistrationService.php`, `bootstrap/app.php`, `routes/api.php`
**two-factor** — `app/Services/Security/TotpService.php`, `app/Http/Controllers/Account/SecurityController.php`, `app/Http/Controllers/Auth/{LoginController,TwoFactorChallengeController}.php`, `app/Http/Controllers/Api/V1/AuthController.php`, `app/Http/Controllers/Central/UserController.php`, `app/Http/Middleware/EnsureTenantAccess.php`, `app/Models/User.php`, `resources/views/account/security.blade.php`, `resources/views/auth/two-factor.blade.php`, `resources/views/central/users/index.blade.php`, `routes/web.php`
**backups / monitoring** — `app/Models/Saas/BackupRun.php`, `app/Services/Backup/BackupService.php`, `app/Jobs/RunBackupJob.php`, `app/Console/Commands/{BackupRunCommand,BackupVerifyCommand,BackupRestoreCommand,MonitorCheck,PerformanceAudit}.php`, `app/Services/Monitoring/HealthService.php`, `app/Http/Controllers/Central/SystemController.php`, `resources/views/central/system/{backups,health}.blade.php`, `app/Providers/AppServiceProvider.php` (slow-query log), `resources/views/layouts/{central,shell}.blade.php`, `routes/central.php`
**docs / lang / tests** — `docs/{OPERATIONS,HANDOVER,PROGRESS}.md`, `lang/bn.json`, `tests/Feature/{SubscriptionsTest,TwoFactorTest,OperationsTest}.php`

Setup: `php artisan migrate` (four new migrations); `php artisan db:seed --class=RolesAndPermissionsSeeder` is not needed (the `subscriptions.*` and `backups.*` permissions already existed); add the new `.env` keys (see `.env.example`); cron + queue worker as in `docs/OPERATIONS.md`.

## File manifest — Part 6e (Reports centre, exports, staff certificates)

**migrations** — `database/migrations/2026_11_10_000100_create_export_runs_table.php`, `database/migrations/2026_11_10_000200_create_staff_certificates_table.php`
**config / console** — `config/reports.php` (groups, report list, limits), `config/school.php` (`staff-certificates.*`), `config/notifications.php` (`reports.export_ready`, `reports.export_failed`), `routes/console.php` (`reports:housekeeping`), `app/Console/Commands/ReportsHousekeeping.php`
**framework** — `app/Services/Reports/{ReportResult,Report,ReportRegistry,ExportService}.php`, `app/Services/Reports/Exporters/{CsvExporter,XlsxWriter}.php`, `app/Models/Reports/ExportRun.php`, `app/Jobs/RunReportExport.php`, `app/Services/Documents/PdfService.php` (footer option)
**report definitions** — `app/Services/Reports/Definitions/**` (Concerns, Library, Transport, Hostel, Inventory, Assets, Finance, People, Attendance, Branches)
**UI** — `app/Http/Controllers/Reports/{ReportCentreController,ExportRunController}.php`, `resources/views/reports/{index,show,exports}.blade.php`, `resources/views/pdf/report.blade.php`, `resources/views/layouts/{branch,central}.blade.php` (menus), `routes/branch.php`, `routes/central.php`
**portal mark sheet PDF** — `app/Http/Controllers/Portal/{StudentPortalController,ParentPortalController}.php`, `routes/portal.php`, `resources/views/portal/{results.blade.php,partials/result-rows.blade.php,partials/parent-home.blade.php}`
**staff certificates** — `app/Models/HR/StaffCertificate.php`, `app/Services/HR/StaffCertificateService.php`, `app/Http/Controllers/Branch/HR/StaffCertificateController.php`, `app/Services/Documents/DocumentService.php` (`staffCertificatePdf`), `resources/views/branch/hr/certificates/{index,_modal,_text}.blade.php`, `resources/views/branch/hr/employees/show.blade.php`, `resources/views/pdf/certificate.blade.php`, `app/Http/Controllers/Public/CertificateVerifyController.php`, `resources/views/public/certificate-verify.blade.php`
**lang / tests** — `lang/bn.json`, `tests/Feature/{ReportsTest,StaffCertificatesTest,DocumentsTest}.php`

Setup: `php artisan migrate` (two new migrations); re-run `php artisan db:seed --class=RolesAndPermissionsSeeder` (new `staff-certificates.*` permissions); run a queue worker (`php artisan queue:work`, see `deploy/supervisor/school-worker.conf`) so background exports are built; the existing `schedule:run` cron drives `reports:housekeeping`. Excel export needs the PHP `zip` extension (on by default in Laragon); PDF needs `composer require mpdf/mpdf` (already installed).

## File manifest — Part 6a (Library)

**migration** — `database/migrations/2026_10_10_000100_create_library_tables.php` (library_categories, library_books, library_book_copies, library_transactions, library_reservations, library_fines)
**config** — `config/library.php` (default rules), `config/notifications.php` (`library.reservation_ready`, `library.overdue`), `routes/console.php` (`library:housekeeping`)
**models** — `app/Models/Library/{LibraryCategory,LibraryBook,LibraryBookCopy,LibraryTransaction,LibraryReservation,LibraryFine}.php`
**services** — `app/Services/Library/{LibraryService,LibrarySettings,LibraryMembers}.php`, `app/Support/Code128.php`, `app/Console/Commands/LibraryHousekeeping.php`
**branch UI** — `app/Http/Controllers/Branch/Library/{Library,Book,Category,Circulation,Reservation,Fine,Settings}Controller.php`, `resources/views/branch/library/**`, `resources/views/layouts/branch.blade.php` (Library menu), `routes/branch.php`
**portals / API** — `app/Http/Controllers/Portal/LibraryPortalController.php`, `resources/views/portal/library.blade.php`, `resources/views/layouts/portal.blade.php`, `resources/views/portal/partials/parent-home.blade.php`, `routes/portal.php`, `app/Http/Controllers/Api/V1/LibraryController.php`, `routes/api.php`
**lang / tests** — `lang/bn.json`, `tests/Feature/LibraryTest.php`

Setup: `php artisan migrate`; no new permissions (the existing `library.*` grants of the Librarian / Branch admin roles apply, so re-seeding is not needed). Schedule: the existing `schedule:run` cron already drives `library:housekeeping`.

## File manifest — Part 6b (Transport & hostel)

**migrations** — `database/migrations/2026_10_15_000100_create_transport_tables.php` (transport_vehicles, transport_drivers, transport_routes, transport_stops, transport_allocations, transport_maintenance, transport_fuel_logs, transport_driver_attendance), `database/migrations/2026_10_15_000200_create_hostel_tables.php` (hostels, hostel_meal_plans, hostel_rooms, hostel_beds, hostel_allocations, hostel_attendance, hostel_visitors, hostel_maintenance_requests)
**models** — `app/Models/Transport/{Vehicle,Driver,TransportRoute,Stop,Allocation,Maintenance,FuelLog,DriverAttendance}.php`, `app/Models/Hostel/{Hostel,MealPlan,Room,Bed,Allocation,Attendance,Visitor,MaintenanceRequest}.php`
**services / console** — `app/Services/Transport/TransportService.php`, `app/Services/Hostel/HostelService.php`, `app/Services/Operations/{ServiceFees,StudentFacilities}.php`, `app/Console/Commands/ServiceFeesGenerate.php`, `routes/console.php` (`services:generate-fees`)
**branch UI** — `app/Http/Controllers/Branch/Transport/{Transport,Vehicle,Driver,Route,TransportAllocation}Controller.php`, `app/Http/Controllers/Branch/Hostel/{Hostel,Room,HostelAllocation,MealPlan,HostelAttendance,Visitor,HostelMaintenance}Controller.php`, `app/Http/Controllers/Branch/Operations/StudentLookupController.php`, `resources/views/branch/transport/**`, `resources/views/branch/hostel/**`, `resources/views/branch/partials/service-fees.blade.php`, `resources/views/components/student-picker.blade.php`, `resources/views/layouts/branch.blade.php` (Transport and Hostel menus), `routes/branch.php`
**portals / API** — `app/Http/Controllers/Portal/FacilitiesPortalController.php`, `resources/views/portal/facilities.blade.php`, `resources/views/layouts/portal.blade.php`, `resources/views/portal/partials/parent-home.blade.php`, `routes/portal.php`, `app/Http/Controllers/Api/V1/FacilitiesController.php`, `routes/api.php`
**lang / tests** — `lang/bn.json`, `tests/Feature/TransportHostelTest.php`

Setup: `php artisan migrate`; no new permissions (the existing `transport.*` / `hostel.*` grants of the Branch admin, Transport manager and Hostel manager roles apply, so re-seeding is not needed). Schedule: the existing `schedule:run` cron already drives `services:generate-fees`. The fee heads "Transport fee" and "Hostel fee" are created automatically the first time invoices are generated.

## File manifest (Parts 0–3A)

**(root)** — `.editorconfig`, `.env.example`, `.gitignore`, `README.md`, `artisan`, `composer.json`, `package.json`, `phpunit.xml`, `vite.config.js`
**app/Console** — `app/Console/Commands/CreateSuperAdmin.php`, `app/Console/Commands/TenancyRun.php`
**app/Enums** — `app/Enums/RecordStatus.php`, `app/Enums/TenantMode.php`, `app/Enums/UserType.php`
**app/Exceptions** — `app/Exceptions/CrossTenantViolation.php`, `app/Exceptions/TenantContextMissing.php`
**app/Http** — `app/Http/Controllers/Api/V1/AuthController.php`, `app/Http/Controllers/Api/V1/BranchController.php`, `app/Http/Controllers/Api/V1/DepartmentController.php`, `app/Http/Controllers/Api/V1/StudentController.php`, `app/Http/Controllers/Auth/HandoffController.php`, `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Controllers/Auth/PasswordResetController.php`, `app/Http/Controllers/Branch/Academic/AcademicSessionController.php`, `app/Http/Controllers/Branch/Academic/ClassSubjectController.php`, `app/Http/Controllers/Branch/Academic/RoomController.php`, `app/Http/Controllers/Branch/Academic/SchoolClassController.php`, `app/Http/Controllers/Branch/Academic/SectionController.php`, `app/Http/Controllers/Branch/Academic/StudyGroupController.php`, `app/Http/Controllers/Branch/Academic/SubjectController.php`, `app/Http/Controllers/Branch/AuditLogController.php`, `app/Http/Controllers/Branch/DashboardController.php`, `app/Http/Controllers/Branch/DepartmentController.php`, `app/Http/Controllers/Branch/Students/PromotionController.php`, `app/Http/Controllers/Branch/Students/StudentController.php`, `app/Http/Controllers/Branch/Students/StudentDocumentController.php`, `app/Http/Controllers/Branch/Students/StudentGuardianController.php`, `app/Http/Controllers/Branch/Students/StudentImportExportController.php`, `app/Http/Controllers/Central/AcademicYearController.php`, `app/Http/Controllers/Central/AuditLogController.php`, `app/Http/Controllers/Central/BranchController.php`, `app/Http/Controllers/Central/BranchDomainController.php`, `app/Http/Controllers/Central/DashboardController.php`, `app/Http/Controllers/Central/InstitutionController.php`, `app/Http/Controllers/Central/RoleController.php`, `app/Http/Controllers/Central/UserController.php`, `app/Http/Controllers/Controller.php`, `app/Http/Controllers/HomeRedirectController.php`, `app/Http/Controllers/LocaleController.php`, `app/Http/Controllers/Portal/ParentPortalController.php`, `app/Http/Controllers/Portal/StudentPortalController.php`, `app/Http/Controllers/Portal/TeacherPortalController.php`, `app/Http/Controllers/Public/WebsiteController.php`, `app/Http/Controllers/SwitchBranchController.php`, `app/Http/Middleware/EnsurePortal.php`, `app/Http/Middleware/EnsureTenantAccess.php`, `app/Http/Middleware/RequireBranch.php`, `app/Http/Middleware/RequireCentral.php`, `app/Http/Middleware/ResolveTenant.php`, `app/Http/Middleware/SetLocale.php`, `app/Http/Requests/Academic/AcademicSessionRequest.php`, `app/Http/Requests/Academic/AcademicYearRequest.php`, `app/Http/Requests/Academic/ClassSubjectRequest.php`, `app/Http/Requests/Academic/RoomRequest.php`, `app/Http/Requests/Academic/SchoolClassRequest.php`, `app/Http/Requests/Academic/SectionRequest.php`, `app/Http/Requests/Academic/StudyGroupRequest.php`, `app/Http/Requests/Academic/SubjectRequest.php`, `app/Http/Requests/Academic/TermRequest.php`, `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Requests/Branch/DepartmentRequest.php`, `app/Http/Requests/Central/BranchRequest.php`, `app/Http/Requests/Central/DomainRequest.php`, `app/Http/Requests/Central/InstitutionRequest.php`, `app/Http/Requests/Central/RoleRequest.php`, `app/Http/Requests/Central/UserRequest.php`, `app/Http/Requests/Students/PromotionRequest.php`, `app/Http/Requests/Students/StudentDocumentRequest.php`, `app/Http/Requests/Students/StudentRequest.php`, `app/Http/Resources/BranchResource.php`, `app/Http/Resources/DepartmentResource.php`, `app/Http/Resources/StudentResource.php`, `app/Http/Resources/UserResource.php`
**app/Jobs** — `app/Jobs/Concerns/TenantAware.php`, `app/Jobs/Middleware/WithTenantContext.php`
**app/Listeners** — `app/Listeners/LogAuthenticationEvents.php`
**app/Models** — `app/Models/Academic/AcademicSession.php`, `app/Models/Academic/AcademicYear.php`, `app/Models/Academic/ClassSubject.php`, `app/Models/Academic/Room.php`, `app/Models/Academic/SchoolClass.php`, `app/Models/Academic/Section.php`, `app/Models/Academic/StudyGroup.php`, `app/Models/Academic/Subject.php`, `app/Models/Academic/Term.php`, `app/Models/AuditLog.php`, `app/Models/Branch.php`, `app/Models/Concerns/Auditable.php`, `app/Models/Concerns/BelongsToBranch.php`, `app/Models/Concerns/BelongsToInstitution.php`, `app/Models/Department.php`, `app/Models/Domain.php`, `app/Models/Institution.php`, `app/Models/Scopes/BranchScope.php`, `app/Models/Scopes/InstitutionScope.php`, `app/Models/Students/Guardian.php`, `app/Models/Students/Student.php`, `app/Models/Students/StudentDocument.php`, `app/Models/Students/StudentEnrollment.php`, `app/Models/SystemSetting.php`, `app/Models/User.php`
**app/Policies** — `app/Policies/AcademicStructurePolicy.php`, `app/Policies/BranchPolicy.php`, `app/Policies/DepartmentPolicy.php`, `app/Policies/GuardianPolicy.php`, `app/Policies/InstitutionPolicy.php`, `app/Policies/RolePolicy.php`, `app/Policies/StudentPolicy.php`, `app/Policies/UserPolicy.php`
**app/Providers** — `app/Providers/AppServiceProvider.php`
**app/Services** — `app/Services/Audit/AuditLogger.php`, `app/Services/Dashboard/DashboardService.php`, `app/Services/Settings/SettingsService.php`, `app/Services/Students/StudentImportService.php`, `app/Services/Students/StudentService.php`, `app/Services/Tenancy/BranchService.php`, `app/Services/Tenancy/HandoffService.php`, `app/Services/Tenancy/SequenceService.php`
**app/Support** — `app/Support/ApiResponse.php`, `app/Support/TenantRule.php`, `app/Support/helpers.php`
**app/Tenancy** — `app/Tenancy/TenantContext.php`, `app/Tenancy/TenantResolver.php`
**bootstrap** — `bootstrap/app.php`, `bootstrap/cache/.gitignore`, `bootstrap/providers.php`
**config** — `config/app.php`, `config/database.php`, `config/school.php`, `config/tenancy.php`
**database** — `database/factories/BranchFactory.php`, `database/factories/DepartmentFactory.php`, `database/factories/InstitutionFactory.php`, `database/factories/UserFactory.php`, `database/migrations/0001_01_01_000000_create_institutions_table.php`, `database/migrations/0001_01_01_000001_create_users_table.php`, `database/migrations/0001_01_01_000002_create_cache_table.php`, `database/migrations/0001_01_01_000003_create_jobs_table.php`, `database/migrations/2026_01_01_000100_create_permission_tables.php`, `database/migrations/2026_01_01_000200_create_personal_access_tokens_table.php`, `database/migrations/2026_01_01_000300_create_audit_logs_table.php`, `database/migrations/2026_01_01_000400_create_system_settings_table.php`, `database/migrations/2026_01_01_000500_create_departments_table.php`, `database/migrations/2026_02_01_000100_create_sequences_table.php`, `database/migrations/2026_02_01_000200_create_academic_structure_tables.php`, `database/migrations/2026_02_01_000300_create_student_tables.php`, `database/seeders/DatabaseSeeder.php`, `database/seeders/DemoAcademicSeeder.php`, `database/seeders/DemoInstitutionSeeder.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `database/seeders/SuperAdminSeeder.php`
**deploy** — `deploy/apache/school.conf`, `deploy/nginx/school.conf`, `deploy/scripts/backup.sh`, `deploy/scripts/deploy.sh`, `deploy/supervisor/school-worker.conf`
**docs** — `docs/API.md`, `docs/ARCHITECTURE.md`, `docs/DECISIONS.md`, `docs/INSTALLATION.md`, `docs/PROGRESS.md`
**lang** — `lang/bn.json`
**public** — `public/.htaccess`, `public/index.php`, `public/robots.txt`
**resources** — `resources/js/app.js`, `resources/js/dashboard.js`, `resources/scss/app.scss`
**resources/views/auth** — `resources/views/auth/forgot-password.blade.php`, `resources/views/auth/login.blade.php`, `resources/views/auth/reset-password.blade.php`
**resources/views/branch** — `resources/views/branch/academics/classes/form.blade.php`, `resources/views/branch/academics/classes/index.blade.php`, `resources/views/branch/academics/classes/subjects.blade.php`, `resources/views/branch/academics/groups/form.blade.php`, `resources/views/branch/academics/groups/index.blade.php`, `resources/views/branch/academics/rooms/form.blade.php`, `resources/views/branch/academics/rooms/index.blade.php`, `resources/views/branch/academics/sections/form.blade.php`, `resources/views/branch/academics/sessions/form.blade.php`, `resources/views/branch/academics/sessions/index.blade.php`, `resources/views/branch/academics/subjects/form.blade.php`, `resources/views/branch/academics/subjects/index.blade.php`, `resources/views/branch/dashboard.blade.php`, `resources/views/branch/departments/form.blade.php`, `resources/views/branch/departments/index.blade.php`, `resources/views/branch/students/create.blade.php`, `resources/views/branch/students/edit.blade.php`, `resources/views/branch/students/import.blade.php`, `resources/views/branch/students/index.blade.php`, `resources/views/branch/students/partials/profile-fields.blade.php`, `resources/views/branch/students/promotion.blade.php`, `resources/views/branch/students/show.blade.php`
**resources/views/central** — `resources/views/central/academic-years/form.blade.php`, `resources/views/central/academic-years/index.blade.php`, `resources/views/central/branches/form.blade.php`, `resources/views/central/branches/index.blade.php`, `resources/views/central/branches/show.blade.php`, `resources/views/central/dashboard.blade.php`, `resources/views/central/institutions/form.blade.php`, `resources/views/central/institutions/index.blade.php`, `resources/views/central/roles/form.blade.php`, `resources/views/central/roles/index.blade.php`, `resources/views/central/users/form.blade.php`, `resources/views/central/users/index.blade.php`
**resources/views/components** — `resources/views/components/chart-card.blade.php`, `resources/views/components/empty-state.blade.php`, `resources/views/components/form/actions.blade.php`, `resources/views/components/form/input.blade.php`, `resources/views/components/form/select.blade.php`, `resources/views/components/form/switch.blade.php`, `resources/views/components/nav-link.blade.php`, `resources/views/components/page-header.blade.php`, `resources/views/components/row-actions.blade.php`, `resources/views/components/stat-card.blade.php`, `resources/views/components/status-badge.blade.php`
**resources/views/errors** — `resources/views/errors/403.blade.php`, `resources/views/errors/404.blade.php`, `resources/views/errors/419.blade.php`, `resources/views/errors/429.blade.php`, `resources/views/errors/500.blade.php`, `resources/views/errors/503.blade.php`
**resources/views/layouts** — `resources/views/layouts/base.blade.php`, `resources/views/layouts/branch.blade.php`, `resources/views/layouts/central.blade.php`, `resources/views/layouts/guest.blade.php`, `resources/views/layouts/portal.blade.php`, `resources/views/layouts/shell.blade.php`, `resources/views/layouts/website.blade.php`
**resources/views/partials** — `resources/views/partials/alerts.blade.php`, `resources/views/partials/confirm-modal.blade.php`, `resources/views/partials/toasts.blade.php`, `resources/views/partials/topbar.blade.php`, `resources/views/partials/user-menu.blade.php`
**resources/views/portal** — `resources/views/portal/home.blade.php`
**resources/views/shared** — `resources/views/shared/audit-logs.blade.php`, `resources/views/shared/switch-branch.blade.php`
**resources/views/website** — `resources/views/website/branches.blade.php`, `resources/views/website/home.blade.php`, `resources/views/website/partials/branch-grid.blade.php`
**routes** — `routes/api.php`, `routes/branch.php`, `routes/central.php`, `routes/console.php`, `routes/portal.php`, `routes/web.php`
**tests** — `tests/Feature/AcademicStructureTest.php`, `tests/Feature/ApiTest.php`, `tests/Feature/AuthenticationTest.php`, `tests/Feature/AuthorizationTest.php`, `tests/Feature/BranchSwitchingTest.php`, `tests/Feature/CentralManagementTest.php`, `tests/Feature/DashboardTest.php`, `tests/Feature/StudentManagementTest.php`, `tests/Feature/TenantIsolationTest.php`, `tests/Feature/TenantResolutionTest.php`, `tests/TestCase.php`, `tests/Unit/HelpersTest.php`, `tests/Unit/RolePatternTest.php`, `tests/Unit/TenantContextTest.php`

## File manifest — Part 3B-1 (new or changed)

**migrations** — `database/migrations/2026_03_01_000100_create_hr_tables.php`, `database/migrations/2026_03_01_000200_create_attendance_tables.php`
**enums / events / console** — `app/Enums/AttendanceStatus.php`, `app/Events/Attendance/ConsecutiveAbsenceDetected.php`, `app/Console/Commands/AttendanceAbsenceAlerts.php`, `routes/console.php`
**models** — `app/Models/HR/{Designation,Employee,EmployeeDocument,EmploymentHistory,LeaveType,LeaveApplication}.php`, `app/Models/Attendance/{StudentAttendance,StaffAttendance,AttendanceCorrection,AttendanceDevice,AttendancePunch,AttendanceAlert}.php`
**services** — `app/Services/HR/{EmployeeService,LeaveService}.php`, `app/Services/Attendance/{AttendanceSettings,StudentAttendanceService,StaffAttendanceService,AttendanceCorrectionService,AbsenceAlertService,DevicePunchService}.php`, `app/Services/Dashboard/DashboardService.php`
**policies / provider** — `app/Policies/{EmployeePolicy,LeaveApplicationPolicy}.php`, `app/Providers/AppServiceProvider.php` (policies, morph map, `attendance.mark-section` gate, `device` rate limiter)
**requests** — `app/Http/Requests/HR/{DesignationRequest,LeaveTypeRequest,EmployeeRequest,EmployeeDocumentRequest,LeaveApplicationRequest}.php`, `app/Http/Requests/Attendance/{StudentAttendanceRequest,StaffAttendanceRequest,CorrectionRequest}.php`
**controllers** — `app/Http/Controllers/Concerns/SharedWithTeacherPortal.php`, `app/Http/Controllers/Branch/HR/{DesignationController,LeaveTypeController,EmployeeController,EmployeeDocumentController,LeaveApplicationController}.php`, `app/Http/Controllers/Branch/Attendance/{StudentAttendanceController,StaffAttendanceController,AttendanceScanController,AttendanceCorrectionController,AttendanceReportController,AttendanceSettingsController}.php`, `app/Http/Controllers/Api/V1/{EmployeeController,AttendanceController,AttendanceDeviceController}.php`, `app/Http/Controllers/Portal/TeacherPortalController.php`, `app/Http/Resources/EmployeeResource.php`
**routes** — `routes/branch.php`, `routes/portal.php`, `routes/api.php`
**views** — `resources/views/branch/hr/**` (designations, leave-types, employees, leaves), `resources/views/branch/attendance/**` (students, staff, scan, corrections, reports, settings, partials), `resources/views/layouts/{branch,portal}.blade.php`, `resources/views/portal/home.blade.php`, `resources/views/{branch,central}/dashboard.blade.php`
**assets / lang** — `resources/js/dashboard.js`, `public/build/*`, `lang/bn.json`
**seed / tests** — `database/seeders/{DemoHrAttendanceSeeder,DatabaseSeeder}.php`, `tests/TestCase.php`, `tests/Feature/{HumanResourcesTest,AttendanceTest}.php`

## File manifest — Part 3B-2 (new or changed)

**migration** — `database/migrations/2026_03_01_000300_create_admission_tables.php`
**models** — `app/Models/Admissions/{AdmissionCampaign,AdmissionApplication,AdmissionDocument}.php`, `app/Models/Students/{StudentTransfer,Certificate,Student}.php`, `app/Models/HR/StaffBranchAssignment.php`
**services** — `app/Services/Admissions/AdmissionService.php`, `app/Services/Students/{TransferService,CertificateService,StudentService}.php`, `app/Services/HR/StaffAssignmentService.php`
**requests** — `app/Http/Requests/Admissions/{AdmissionCampaignRequest,AdmissionApplicationRequest}.php`, `app/Http/Requests/Auth/RegisterRequest.php`
**controllers** — `app/Http/Controllers/Branch/Admissions/{CampaignController,ApplicationController,MeritController}.php`, `app/Http/Controllers/Public/{AdmissionPortalController,CertificateVerifyController}.php`, `app/Http/Controllers/Branch/Students/{TransferController,CertificateController,StudentController}.php`, `app/Http/Controllers/Central/StaffAssignmentController.php`, `app/Http/Controllers/Api/V1/AdmissionController.php`
**provider / routes** — `app/Providers/AppServiceProvider.php` (rate limiters), `routes/{branch,central,api}.php`
**views** — `resources/views/layouts/{public,branch,central,website,auth}.blade.php`, `resources/views/public/**`, `resources/views/branch/admissions/**`, `resources/views/branch/students/{show.blade.php,partials/lifecycle-modals.blade.php,transfers/index.blade.php,certificates/*}`, `resources/views/central/staff-assignments/index.blade.php`, `resources/views/website/partials/branch-grid.blade.php`
**assets / lang / seed / tests** — `resources/scss/ui/_auth.scss`, `public/build/*`, `lang/bn.json`, `database/seeders/{DemoAdmissionSeeder,DatabaseSeeder}.php`, `tests/Feature/{AdmissionTest,AttendanceTest}.php`

## File manifest — Part 3C-1 (new or changed)

**migrations** — `database/migrations/2026_04_01_000100_create_timetable_tables.php`, `database/migrations/2026_04_01_000200_create_examination_tables.php`
**models** — `app/Models/Academic/{Period,TimetableEntry}.php`, `app/Models/Exams/{GradingScale,Examination,ExaminationSubject}.php`
**services** — `app/Services/Academic/TimetableService.php`, `app/Services/Exams/{GradingService,ExaminationService}.php`
**controllers** — `app/Http/Controllers/Branch/Academic/{PeriodController,TimetableController}.php`, `app/Http/Controllers/Branch/Exams/{GradingScaleController,ExaminationController}.php`
**routes** — `routes/{branch,portal}.php`
**views** — `resources/views/branch/academics/periods/index.blade.php`, `resources/views/branch/academics/timetable/{index,teacher,room,_week}.blade.php`, `resources/views/branch/exams/{index,form,show,routine}.blade.php`, `resources/views/branch/exams/grading/{index,form}.blade.php`, `resources/views/layouts/{branch,portal}.blade.php`
**lang / seed / tests** — `lang/bn.json`, `database/seeders/{DemoTimetableExamSeeder,DatabaseSeeder}.php`, `tests/Unit/GradingServiceTest.php`, `tests/Feature/{TimetableTest,ExaminationTest}.php`

## File manifest — Part 3C-2 (new or changed)

**migration** — `database/migrations/2026_04_01_000300_create_marks_results_tables.php`
**models / events** — `app/Models/Exams/{ExamMark,MarkCorrection,ExamResult,Examination}.php`, `app/Events/Exams/ExamResultsPublished.php`
**services** — `app/Services/Exams/{MarkEntryService,MarkCorrectionService,ResultService,ExamDocumentService}.php`, `app/Services/Students/PortalAccountService.php`, `app/Services/Portal/PortalService.php`, `app/Services/Dashboard/DashboardService.php`
**controllers** — `app/Http/Controllers/Branch/Exams/{MarkController,MarkCorrectionController,ResultController}.php`, `app/Http/Controllers/Branch/Students/PortalAccountController.php`, `app/Http/Controllers/Portal/{StudentPortalController,ParentPortalController}.php`, `app/Http/Controllers/Public/ResultLookupController.php`, `app/Http/Controllers/Api/V1/ExamController.php`
**routes** — `routes/{branch,portal,api}.php`
**views** — `resources/views/branch/exams/{show,corrections}.blade.php`, `resources/views/branch/exams/marks/*`, `resources/views/branch/exams/results/*`, `resources/views/branch/exams/documents/*`, `resources/views/branch/students/{show.blade.php,partials/portal-logins.blade.php}`, `resources/views/portal/{home,results}.blade.php`, `resources/views/portal/partials/*`, `resources/views/public/results.blade.php`, `resources/views/layouts/{branch,portal,public}.blade.php`, `resources/views/branch/dashboard.blade.php`
**assets / lang / seed / tests / docs** — `resources/js/dashboard.js`, `public/build/*`, `lang/bn.json`, `database/seeders/{DemoTimetableExamSeeder,DemoResultsSeeder,DatabaseSeeder}.php`, `tests/Feature/MarksAndResultsTest.php`, `docs/{API,PROGRESS}.md`

## File manifest — Part 4-1 (new or changed)

**migrations** — `database/migrations/2026_05_01_000100_create_accounting_tables.php`, `database/migrations/2026_05_01_000200_create_fee_tables.php`
**models** — `app/Models/Finance/{FinancialYear,Account,JournalEntry,JournalLine,FeeHead,FeeStructure,StudentFeeAdjustment,FeeInvoice,FeeInvoiceItem,FeePayment,FeeAllocation,FeeRefund}.php`
**services** — `app/Services/Finance/{FinanceSettings,ChartOfAccounts,LedgerService,FeeInvoiceService,FeePaymentService,FeeReportService}.php`, `app/Services/Portal/PortalService.php`, `app/Services/Dashboard/DashboardService.php`, `app/Support/AmountInWords.php`
**controllers** — `app/Http/Controllers/Branch/Finance/*`, `app/Http/Controllers/Portal/{StudentPortalController,ParentPortalController}.php`, `app/Http/Controllers/Api/V1/FeeController.php`, `app/Http/Controllers/Branch/Exams/ResultController.php` (fix)
**commands / schedule** — `app/Console/Commands/{FeesGenerateInvoices,FeesLateFees}.php`, `routes/console.php`
**provider / routes** — `app/Providers/AppServiceProvider.php` (morph map), `routes/{branch,portal,api}.php`
**views** — `resources/views/branch/finance/**`, `resources/views/portal/{fees.blade.php,partials/parent-home.blade.php}`, `resources/views/layouts/{branch,portal}.blade.php`, `resources/views/branch/dashboard.blade.php`
**assets / lang / seed / tests** — `resources/js/dashboard.js`, `public/build/*`, `lang/bn.json`, `database/seeders/{DemoFinanceSeeder,DatabaseSeeder}.php`, `tests/Feature/FeesTest.php`

## File manifest — Part 4-2 (new or changed)

**migration** — `database/migrations/2026_05_01_000300_create_expense_payroll_tables.php` (vendors, expenses, employee_salaries, payroll_runs, payroll_items, budgets, journal_lines.reconciled_on/by)
**models** — `app/Models/Finance/{Vendor,Expense,EmployeeSalary,PayrollRun,PayrollItem,Budget,JournalLine}.php`, `app/Models/HR/Employee.php` (salary relation)
**services** — `app/Services/Finance/{ExpenseService,PayrollService,AccountingReportService,ConsolidatedReportService,LedgerService,ChartOfAccounts,FinanceSettings}.php`
**controllers** — `app/Http/Controllers/Branch/Finance/{VendorController,ExpenseController,PayrollController,JournalController,AccountingReportController,FinanceSettingsController}.php`, `app/Http/Controllers/Central/FinanceReportController.php`
**config / provider / routes** — `config/school.php`, `app/Providers/AppServiceProvider.php` (morph map), `routes/{branch,central}.php`
**views** — `resources/views/branch/finance/{vendors,expenses,payroll,journal}/*`, `resources/views/branch/finance/reports/{_acc_nav,_range,ledger,trial-balance,income-expenditure,balance-sheet,cash-flow,budget,reconcile,years}.blade.php`, `resources/views/branch/finance/settings.blade.php`, `resources/views/central/finance/index.blade.php`, `resources/views/layouts/{branch,central}.blade.php`
**lang / seed / tests** — `lang/bn.json`, `database/seeders/{DemoAccountingSeeder,DatabaseSeeder}.php`, `tests/Feature/AccountingTest.php`

## File manifest — Part 5-1 (new or changed)

**migration** — `database/migrations/2026_06_01_000100_create_communication_tables.php` (message_templates, notification_batches, notification_messages, notification_opt_outs, notices, notice_reads)
**config** — `config/notifications.php` (events, default Bangla / English templates, SMS drivers, retry policy)
**models** — `app/Models/Communication/{MessageTemplate,NotificationBatch,NotificationMessage,NotificationOptOut,Notice,NoticeRead}.php`
**services** — `app/Services/Notifications/{NotificationHub,NotificationSettings,TemplateRenderer,RecipientResolver,Recipient,SendResult,EmailSender,WhatsAppCloudGateway,PhoneNumber,SmsCounter}.php`, `app/Services/Notifications/Sms/{SmsGateway,HttpSmsGateway,LogSmsGateway,SslWirelessGateway,BulkSmsBdGateway,AlphaSmsGateway}.php`, `app/Services/Communication/{NoticeService,NoticeAudience,FeeReminderService}.php`; events raised in `FeeInvoiceService`, `FeePaymentService`, `AdmissionService`
**events / listeners / job / mail** — `app/Events/Finance/{FeeInvoiceIssued,FeePaymentReceived}.php`, `app/Events/Admissions/{AdmissionApplicationSubmitted,StudentAdmitted}.php`, `app/Listeners/Notifications/*`, `app/Jobs/SendNotificationMessage.php`, `app/Mail/NotificationMail.php`
**controllers** — `app/Http/Controllers/Branch/Communication/{NoticeController,MessageController,TemplateController,NotificationSettingsController,OptOutController}.php`, `app/Http/Controllers/Communication/{InboxController,NoticeBoardController,MessagePreferencesController}.php`, `app/Http/Controllers/Webhooks/WhatsAppWebhookController.php`, `app/Http/Controllers/Central/InstitutionNoticeController.php`, `app/Http/Controllers/Api/V1/NoticeController.php`
**commands / schedule** — `app/Console/Commands/{NotificationsDispatch,NotificationsFeeReminders}.php`, `routes/console.php`
**routes / bootstrap / provider** — `routes/{branch,central,api}.php`, `bootstrap/app.php` (CSRF exemption for `webhooks/*`), `app/Providers/AppServiceProvider.php` (morph map)
**views** — `resources/views/branch/communication/**`, `resources/views/communication/{inbox,board,notice,preferences}.blade.php`, `resources/views/central/notices/*`, `resources/views/emails/notification*.blade.php`, `resources/views/layouts/{shell,branch,portal,central}.blade.php`
**assets / lang / seed / tests / docs** — `resources/js/{app,notifications}.js`, `public/build/*`, `lang/bn.json`, `database/seeders/{DemoCommunicationSeeder,DatabaseSeeder}.php`, `tests/Feature/{NotificationsTest,NoticesTest}.php`, `tests/Unit/NotificationHelpersTest.php`, `docs/{API,INSTALLATION,PROGRESS}.md`

## File manifest — Part 5-2 (new or changed)

**migration** — `database/migrations/2026_06_01_000200_create_online_payment_tables.php` (online_payments, online_payment_events)
**config** — `config/payments.php` (gateways, fields, refund support, expiry)
**models** — `app/Models/Payments/{OnlinePayment,OnlinePaymentEvent}.php`
**services** — `app/Services/Notifications/Sms/ShatajSmsGateway.php`, `app/Services/Notifications/NotificationHub.php` (Shataj SMS status sync), `app/Services/Payments/{OnlinePaymentService,PaymentSettings,InitiateResult,VerifyResult,RefundResult,GatewayException}.php`, `app/Services/Payments/Gateways/{PaymentGateway,HttpGateway,ShatajPayGateway,SslCommerzGateway,BkashGateway,NagadGateway,ShurjoPayGateway,AamarPayGateway}.php`
**controllers** — `app/Http/Controllers/Payments/{OnlinePaymentController,GatewayCallbackController}.php`, `app/Http/Controllers/Branch/Finance/OnlinePaymentController.php`, `app/Http/Controllers/Api/V1/OnlinePaymentController.php`
**command / schedule** — `app/Console/Commands/PaymentsReconcile.php`, `routes/console.php`
**routes / bootstrap** — `routes/{branch,api}.php`, `bootstrap/app.php` (CSRF exemption for callbacks, secrets never flashed)
**views** — `resources/views/portal/{fees,payment-status,payment-done}.blade.php`, `resources/views/branch/finance/online-payments/{index,show,settings}.blade.php`, `resources/views/layouts/branch.blade.php`, `resources/views/branch/hr/leave-types/index.blade.php`
**lang / tests / docs** — `lang/bn.json`, `tests/Feature/OnlinePaymentsTest.php`, `docs/{API,INSTALLATION,PROGRESS}.md`
**ShatajPay / Shataj SMS additions** — `app/Services/Payments/Gateways/ShatajPayGateway.php`, `app/Services/Notifications/Sms/ShatajSmsGateway.php`, `config/notifications.php`, `app/Http/Controllers/Branch/Communication/NotificationSettingsController.php`, `app/Console/Commands/NotificationsDispatch.php`, `resources/views/branch/communication/settings.blade.php`, `tests/Feature/NotificationsTest.php`

## File manifest — Part 5-3 (new or changed)

**migrations** — `database/migrations/2026_08_01_000100_create_learning_tables.php` (learning_materials, assignments, assignment_submissions), `2026_08_01_000200_create_online_class_tables.php` (online_class_integrations, online_classes, online_class_attendances), `2026_08_01_000300_create_quiz_tables.php` (quizzes, quiz_questions, quiz_attempts)
**config** — `config/online_classes.php` (providers, credential fields), `config/school.php` (materials, quizzes permissions + role grants), `config/notifications.php` (assignment.published / evaluated, online_class.scheduled / rescheduled / cancelled, quiz.published)
**models** — `app/Models/Learning/{LearningMaterial,Assignment,AssignmentSubmission,OnlineClass,OnlineClassIntegration,OnlineClassAttendance,Quiz,QuizQuestion,QuizAttempt}.php`
**services** — `app/Services/Learning/{LearningAccess,AssignmentService,LearningProgress,OnlineClassService,QuizService}.php`, `app/Services/Learning/Meetings/{MeetingProvider,MeetingProviders,MeetingDetails,MeetingException,ZoomProvider,GoogleMeetProvider}.php`
**controllers** — `app/Http/Controllers/Branch/Learning/{AssignmentController,MaterialController,ProgressController,OnlineClassController,OnlineClassIntegrationController,QuizController}.php`, `app/Http/Controllers/Portal/{LearningPortalController,QuizPortalController}.php`, `app/Http/Controllers/Api/V1/LearningController.php`
**routes / provider** — `routes/learning.php` (shared by `branch.learning.*` and `portal.teacher.learning.*`), `routes/{branch,portal,api}.php`, `app/Providers/AppServiceProvider.php` (morph map)
**views** — `resources/views/branch/learning/**`, `resources/views/portal/learning/**`, `resources/views/layouts/{branch,portal}.blade.php`, `resources/views/portal/partials/parent-home.blade.php`
**lang / tests / docs** — `lang/bn.json`, `tests/Feature/{LearningTest,OnlineClassTest,QuizTest}.php`, `docs/PROGRESS.md`

Setup: `php artisan migrate`, re-run `RolesAndPermissionsSeeder` (new permissions), `php artisan storage:link`. Zoom needs a Server-to-Server OAuth app (scopes meeting:write:admin, meeting:read:admin, cloud_recording:read:admin); Google needs a Web OAuth client with the Calendar API enabled and the redirect URI shown on the settings screen registered.

## File manifest — Branch websites

**migration** — `database/migrations/2026_10_05_000100_add_branch_to_website_content.php` (website_pages.branch_id, website_posts.branch_id)
**config** — `config/website.php` (`branch` defaults), `config/school.php` (branch-admin: `website.*`; principal: `website.view`, `website.update`)
**services / models / policy** — `app/Services/Website/BranchSite.php`, `app/Services/Website/SiteContent.php`, `app/Models/Website/{Page,Post}.php`, `app/Policies/WebsitePolicy.php`
**public** — `app/Http/Controllers/Public/BranchSiteController.php`, `resources/views/layouts/branch-site.blade.php`, `resources/views/branch-site/{home,message}.blade.php`, `resources/views/website/{page,posts,post}.blade.php` (shared), `public/css/website.css`
**branch admin** — `app/Http/Controllers/Branch/Website/{SettingsController,PageController,PostController}.php` (Page/Post extend the Central ones), `app/Http/Requests/Website/{PageRequest,PostRequest}.php`, `resources/views/branch/website/{settings,partials/people}.blade.php`, `resources/views/central/website/{pages,posts}/*` (shared; take `$layout` / `$routePrefix`), `resources/views/layouts/branch.blade.php` (Website menu), `routes/branch.php`
**lang / tests** — `lang/bn.json`, `tests/Feature/BranchSiteTest.php`

Setup: `php artisan migrate`, re-run `RolesAndPermissionsSeeder` (new grants), `php artisan storage:link`; then, per branch, sign in as the branch administrator → Website → Site settings / Pages → "Create standard pages".

## File manifest — Public website & CMS (home page per MASTER design)

**migrations** — `database/migrations/2026_07_01_000100_add_website_fields_to_branches.php` (branches.cover_path, latitude, longitude), `2026_07_01_000200_create_website_tables.php` (website_pages, website_posts, website_subscribers)
**config** — `config/website.php` (hero image, three feature boxes, partners, social, footer text, default page list)
**models / services / support** — `app/Models/Website/{Page,Post,Subscriber}.php`, `app/Services/Website/SiteContent.php`, `app/Support/Website.php`, `app/Models/Branch.php` (coverUrl, hasCoordinates)
**public controllers** — `app/Http/Controllers/Public/{WebsiteController,PageController,PostController,NewsletterController}.php`
**admin (Super Admin → Website)** — `app/Http/Controllers/Central/Website/{SettingsController,PageController,PostController,SubscriberController}.php`, `app/Policies/WebsitePolicy.php`, `app/Http/Requests/Website/{PageRequest,PostRequest}.php`, branch cover/coordinates in `Central/BranchController`, `Requests/Central/BranchRequest`, `resources/views/central/branches/form.blade.php`
**routes / provider** — `routes/central.php` (public: `/page/{slug}`, `/news`, `/notices`, `/newsletter`; admin: `central.website.*`), `app/Providers/AppServiceProvider.php` (policies)
**views / assets** — `resources/views/layouts/website.blade.php`, `resources/views/website/{home,branches,page,posts,post}.blade.php`, `resources/views/website/partials/{branch-grid,branch-map,news-card}.blade.php`, `resources/views/central/website/**`, `resources/views/layouts/central.blade.php` (Website menu), `public/css/website.css`
**seed / lang / tests** — `database/seeders/{WebsiteDefaultsSeeder,DatabaseSeeder,DemoInstitutionSeeder}.php`, `lang/bn.json`, `tests/Feature/WebsiteTest.php` (13 tests)

Notes: branch photo grid shows the first 9 branches (3×3) with a "View all" link to `/branches`; map is Leaflet + OpenStreetMap; page/news bodies are Markdown with raw HTML stripped; newsletter box stores sign-ups (no mail is sent yet — the list can be exported as CSV). Default pages are created by `php artisan db:seed --class=WebsiteDefaultsSeeder` (starter text only; edit in Website → Pages).

## File manifest — Part 6c-1 (Inventory)

**migration** — `database/migrations/2026_10_20_000100_create_inventory_tables.php` (inventory_categories, inventory_items, inventory_requisitions(+_items), purchase_orders(+_items), goods_receipts(+_items), supplier_payments, stock_issues(+_items), stock_audits(+_items), stock_adjustments(+_items), stock_transactions)
**models** — `app/Models/Inventory/{Category,Item,StockTransaction,Requisition,RequisitionItem,PurchaseOrder,PurchaseOrderItem,GoodsReceipt,GoodsReceiptItem,SupplierPayment,StockIssue,StockIssueItem,StockAdjustment,StockAdjustmentItem,StockAudit,StockAuditItem}.php`
**services** — `app/Services/Inventory/{InventoryService,ProcurementService,InventoryReportService}.php`, `app/Services/Finance/ChartOfAccounts.php` (accounts `inventory_stock`, `stock_consumed`, `stock_adjustment`)
**branch UI** — `app/Http/Controllers/Branch/Inventory/{Inventory,Category,Item,Supplier,Requisition,PurchaseOrder,GoodsReceipt,Issue,Adjustment,Audit,Report}Controller.php`, `resources/views/branch/inventory/**`, `resources/views/components/inv-status.blade.php`, `resources/views/layouts/branch.blade.php` (Inventory menu), `routes/branch.php`
**central** — `app/Http/Controllers/Central/InventoryReportController.php`, `resources/views/central/inventory/index.blade.php`, `resources/views/layouts/central.blade.php`, `routes/central.php`
**provider / config / seed / lang / tests** — `app/Providers/AppServiceProvider.php` (morph map: goods_receipt, supplier_payment, stock_issue, stock_adjustment), `config/school.php` (role store-keeper), `database/seeders/{DemoInventorySeeder,DatabaseSeeder}.php`, `lang/bn.json`, `tests/Feature/InventoryTest.php`

Setup: `php artisan migrate`, re-run `RolesAndPermissionsSeeder` (new role). The three new system accounts are created in each branch's chart the first time inventory posts to the books (or when `ChartOfAccounts::ensure()` runs). No new schedule.

## File manifest — Part 6c-2 (Asset register)

**migration** — `database/migrations/2026_10_25_000100_create_asset_tables.php` (asset_categories, assets, asset_allocations, asset_maintenance_plans, asset_maintenance_logs, asset_depreciation_runs, asset_depreciations, asset_disposals, asset_audits, asset_audit_items)
**models** — `app/Models/Assets/{Category,Asset,Allocation,MaintenancePlan,MaintenanceLog,DepreciationRun,Depreciation,Disposal,Audit,AuditItem}.php`
**services** — `app/Services/Assets/{AssetService,DepreciationService,DisposalService,MaintenanceService,AssetAuditService,AssetReportService}.php`, `app/Services/Assets/Concerns/AssetHelpers.php`, `app/Services/Finance/ChartOfAccounts.php` (accounts `fixed_assets`, `accumulated_depreciation`, `asset_disposal_gain`, `depreciation`, `asset_disposal_loss`), `app/Console/Commands/AssetsHousekeeping.php`
**branch UI** — `app/Http/Controllers/Branch/Assets/{AssetLookups,Overview,Category,Asset,Allocation,Maintenance,Depreciation,Disposal,Audit,Report}Controller.php`, `resources/views/branch/assets/**`, `resources/views/components/asset-status.blade.php`, `resources/views/branch/inventory/items/show.blade.php` ("Move to asset register"), `resources/views/layouts/branch.blade.php` (Assets menu, overdue badge), `routes/branch.php`
**central** — `app/Http/Controllers/Central/AssetReportController.php`, `resources/views/central/assets/index.blade.php`, `resources/views/layouts/central.blade.php`, `routes/central.php`
**provider / config / seed / lang / tests** — `app/Providers/AppServiceProvider.php` (morph map: asset, asset_depreciation_run, asset_disposal), `config/school.php` (assets permissions), `config/notifications.php` (`assets.maintenance_due`), `routes/console.php` (`assets:housekeeping`), `database/seeders/{DemoAssetsSeeder,DatabaseSeeder}.php`, `lang/bn.json`, `tests/Feature/{AssetsTest,AssetsWebTest}.php`

Setup: `php artisan migrate`, re-run `RolesAndPermissionsSeeder` (new `assets.*` permissions), optionally `php artisan db:seed --class=DemoAssetsSeeder`. The five system accounts appear in each branch's chart the first time assets post to the books (or when `ChartOfAccounts::ensure()` runs). The existing `schedule:run` cron drives `assets:housekeeping`.

## File manifest — Part 6d (Certificates, ID cards & PDF)

**package** — `composer require mpdf/mpdf` (not bundled; `composer.json` / `composer.lock` change on your side)
**config** — `config/pdf.php`, `config/school.php` (permission `id-cards.*` for branch admin and principal)
**migration** — `database/migrations/2026_11_01_000100_create_id_cards_table.php` (id_cards)
**model / services** — `app/Models/Documents/IdCard.php`, `app/Services/Documents/{PdfService,DocumentService,DocumentSettings,IdCardService}.php`, `app/Models/Students/Certificate.php` + `app/Services/Students/CertificateService.php` (type `achievement`)
**controllers** — `app/Http/Controllers/Branch/Documents/{IdCardController,DocumentSettingsController}.php`, `app/Http/Controllers/Public/IdCardVerifyController.php`, `app/Http/Controllers/Branch/Students/CertificateController.php` (`pdf`), `app/Http/Controllers/Branch/Exams/ResultController.php` (`marksheetPdf`, `marksheetsPdf`, `admitCardsPdf`)
**PDF templates** — `resources/views/pdf/{_doc,_letterhead,_id-front,_id-back,id-cards,certificate,admit-cards,marksheets}.blade.php`
**screens** — `resources/views/branch/documents/{settings,id-cards/index,id-cards/register}.blade.php`, `resources/views/public/id-card-verify.blade.php`, `resources/views/branch/students/certificates/{_text,print,index}.blade.php`, `resources/views/branch/students/partials/lifecycle-modals.blade.php`, `resources/views/branch/students/show.blade.php`, `resources/views/branch/hr/employees/show.blade.php`, `resources/views/branch/exams/{results/index,documents/admit-cards}.blade.php`, `resources/views/layouts/branch.blade.php` (menu), `routes/branch.php`
**lang / tests** — `lang/bn.json`, `tests/Feature/DocumentsTest.php`

Setup: `composer require mpdf/mpdf`, `php artisan migrate`, re-run `RolesAndPermissionsSeeder` (new `id-cards.*` permissions). Optional: Bangla font files in `storage/fonts` (see `config/pdf.php`). Make sure `storage/app/mpdf` is writable (created on first use).

### Real-PDF check (mPDF 8.3.1, Bangla)

Rendered every document with the real mPDF (certificate, staff certificate, ID cards single and sheet, admit cards, mark sheets, reports centre PDF) and inspected the pages. Fixed:

- **Bold Bangla printed as empty boxes** — `FreeSerifBold` has no Bangla glyphs. `PdfService` now registers the bundled family without a bold file (`bnserif`) so mPDF thickens the regular glyphs. A configured custom font (Noto Sans Bengali) is unaffected.
- **Block `text-align` inside a table cell is ignored by mPDF** — centred titles, footers and signature lines now sit in cells of their own (`pdf/certificate`, `pdf/admit-cards`, `pdf/marksheets`).
- **`border:double` / box borders vanished** with `border-collapse: collapse` — outer boxes use `border-collapse: separate`.
- **ID card overflowed its 85.6 mm page** (a third page appeared) — mPDF adds cell padding on top of `height`; row heights reduced accordingly. Photo placeholders are now cells with a real size.
- **QR codes need `mpdf/qrcode`** (a separate package, not installed with mpdf) — `PdfService::qrAvailable()`; without the package the QR is left out and the code printed as text instead of failing the PDF.
- **QR verified with the real `mpdf/qrcode`**: certificate and ID-card QR codes render and decode to the right verification URL (checked by scanning the rendered pages). The ID-card QR was shrunk (size 0.62) so it no longer touches the card number.
- **Photo cropping** (the old "leftover"): `PdfService::image($path, $maxPx, $ratio)` crops a photo to the frame shape (ID card 22 × 27 mm, admit card 24 × 29 mm) — centre horizontally, a little above centre vertically — instead of stretching it. Covered by `DocumentsTest::test_photos_are_cropped_to_the_frame_instead_of_squeezed`.

## Bangla QR fee payments

Students / parents pay fees by scanning a **Bangla QR** (Bangladesh Bank's national EMV merchant-presented QR) with any bank or mobile-banking app.

- **Setup** — the school pastes the text of the static Bangla QR its bank issued (Online payments → Gateway settings → Bangla QR). `EmvQr::inspect()` checks the CRC-16, the account tags (26–51), the currency (050) and shows the merchant name / city. Not offered until the text is saved.
- **Per bill** — `EmvQr::dynamic()` turns it into a dynamic QR (point of initiation 12, amount tag 54, bill number = our payment reference in tag 62/01, student ID in 62/05, CRC recalculated; every other tag of the bank's QR is kept). `App\Support\Qr\QrEncoder` draws it (dependency-free, byte mode, level M, versions 1–20, checked by scanning rendered symbols in a browser).
- **Flow** — portal Fees → choose *Bangla QR* → QR page (`/online-payments/qr/{tran}`) → payer pays in any app → sends the TrxID (+ amount, wallet number) → attempt goes to **review** → accounts office accepts (`resolveReview`, books the receipt; surplus becomes advance credit) or rejects. The same invoices reuse one open attempt; attempts live 3 days (`payments.qr_expire_after_minutes`) and an expired one can still be claimed; a TrxID cannot be used twice.
- **Statement matching** — Online payments → *Match a bank statement*: upload the account statement as CSV (columns TrxID + Amount, optional Reference / Narration / Date, comma / semicolon / tab). A row with the same TrxID and amount as a claim is accepted; a row whose text holds the reference of an unclaimed attempt with the same amount is accepted too; amount differences and unknown rows are listed, never guessed. Re-uploading the same statement changes nothing.
- **Mobile app** — `GET my/online-payments/gateways` marks it `manual`; `POST my/online-payments` returns `qr.payload` (the EMV text to draw as a QR code), `POST my/online-payments/{tran}/claim` sends the TrxID.
- **Files** — `app/Support/Qr/QrEncoder.php`, `app/Services/Payments/BanglaQr/{EmvQr,StatementParser}.php`, `app/Services/Payments/Gateways/BanglaQrGateway.php`, `OnlinePaymentService` (`startManual`, `qrPayload`, `submitClaim`, `reconcileStatement`), `PaymentSettings` (`isManual`, `qrText`), both `OnlinePaymentController`s + `Api\V1\OnlinePaymentController`, `resources/views/portal/payment-qr.blade.php`, `resources/views/branch/finance/online-payments/statement.blade.php`, `config/payments.php`, `lang/bn.json`, `tests/Feature/BanglaQrTest.php` (10 tests). No migration, no new package.
- **Limits** — Bangla QR has no verification API: the money is only *confirmed* by a person or a bank statement. The merchant account template inside the QR is the bank's; if a bank issues dynamic-only QRs, ask for a static merchant QR.

## Hero banner slider and branch Google Maps

- **Hero slides** — the home page of the main website and of every branch website now shows a sliding banner when there are several pictures (up to 8; auto-play every 6 s, arrows, dots, swipe, pause on hover/focus, no motion with `prefers-reduced-motion`). Each slide can carry a caption title, text and a button link (`/path` or `https://…`; `javascript:` and `//host` are rejected). One picture shows a plain banner without controls; none shows the name banner.
- **Admin** — Super Admin → Website → Home page settings and branch Website → Site settings: *Hero banner slides* (order, captions, link, remove, add several pictures at once). A branch without slides of its own shows the institution's.
- **Data** — `hero.slides = [{image,title,text,link}]`; `hero.image` always mirrors the first slide, so old data (a single hero picture) keeps working and appears as slide 1. Logic: `App\Services\Website\HeroSlides` (used by `SiteContent` and `BranchSite`); view `website/partials/hero.blade.php`; editor `website/partials/hero-slides-editor.blade.php`. A path posted by the browser is trusted only when it is already a slide, and only files in the branch's own folder are deleted.
- **All Branch Locations** (main site, branch site, *View all branches*) — every branch of the institution is listed with **Show on map**, **Google Maps**, **Directions** and **Visit branch**; the right side is an embedded **Google Map** (no API key) of the selected branch — the branch being viewed comes first — plus an **All branches** tab with every pin (OpenStreetMap). A branch with only an address is found by its address text. Helpers: `Branch::googleMapsUrl() / googleDirectionsUrl() / googleEmbedUrl()`.
- **Tests** — `tests/Feature/HeroSlidesAndMapsTest.php` (11).
