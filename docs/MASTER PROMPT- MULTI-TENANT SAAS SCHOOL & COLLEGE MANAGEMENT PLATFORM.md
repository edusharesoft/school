# MASTER PROMPT: MULTI-TENANT SAAS SCHOOL & COLLEGE MANAGEMENT PLATFORM

## 1. YOUR ROLE

Act as a Senior Software Architect, Laravel Full-Stack Developer, SaaS Platform Engineer, Database Architect, UI/UX Designer, DevOps Engineer, and Quality Assurance Specialist.

Your task is to design and develop a complete, production-ready, secure, scalable, multi-tenant School & College Management System using PHP Laravel and MySQL.

Do not deliver only a prototype, sample code, UI template, or project specification. Build the actual application with complete source code, database migrations, models, controllers, middleware, routes, views, APIs, integrations, tests, and deployment documentation.

The project must be suitable for a renowned educational institution in Bangladesh operating 10 school and college branches.

Project type: Multi-Tenant SaaS School & College Management Platform.

## 2. INSTITUTION AND BUSINESS MODEL

The institution has 10 independent branches.

Each branch must have its own software interface, branch-specific subdomain, administrative users, students, teachers, classes, fees, examinations, attendance records, financial accounts, and reports.

The main domain must provide a centralized platform for managing all 10 branches.

Example domain structure:

- Main website: https://schooldomain.com
- Central administration: https://schooldomain.com/super-admin
- Branch 1: https://branch1.schooldomain.com
- Branch 2: https://branch2.schooldomain.com
- Branch 3: https://branch3.schooldomain.com
- Branch 4: https://branch4.schooldomain.com
- Branch 5: https://branch5.schooldomain.com
- Branch 6: https://branch6.schooldomain.com
- Branch 7: https://branch7.schooldomain.com
- Branch 8: https://branch8.schooldomain.com
- Branch 9: https://branch9.schooldomain.com
- Branch 10: https://branch10.schooldomain.com

Use configurable domain names and branch slugs. Do not hardcode these example domains.

The system must support adding more branches in the future without modifying the core application.

Implement a genuine multi-tenant architecture. Each branch must access only its own authorized data, while the Super Admin can access consolidated institutional data.

Use a shared MySQL database with tenant_id or branch_id isolation as the default architecture. Explain how to migrate to separate databases per tenant if required in the future.

## 3. REQUIRED TECHNOLOGY STACK

Backend:
- PHP 8.3 or a compatible supported PHP version.
- Latest stable Laravel version compatible with the selected PHP version.
- MySQL 8.0 or compatible version.
- Laravel Eloquent ORM.
- Laravel Queues and Scheduler.
- RESTful API architecture.

Frontend:
- Laravel Blade.
- Bootstrap 5 or Tailwind CSS.
- Alpine.js or Livewire where appropriate.
- Chart.js for interactive dashboards.
- Responsive design for desktop, tablet, and mobile.
- DataTables or an equivalent searchable, sortable, paginated table solution.

Additional technologies:
- Redis for caching and queues where available.
- Laravel Filesystem for secure document storage.
- Laravel Notifications and Events.
- PHPUnit or Pest for automated testing.

Select compatible stable package versions and document all dependencies.

## 4. USER ROLES AND PERMISSIONS

Implement role-based access control with granular permissions.

Required roles:

1. Super Admin
2. Institution Administrator
3. Branch Administrator
4. Principal
5. Vice Principal
6. Academic Coordinator
7. Teacher
8. Class Teacher
9. Accountant
10. Admission Officer
11. Examination Controller
12. Librarian
13. Laboratory Manager
14. HR Manager
15. Transport Manager
16. Hostel Manager
17. Receptionist
18. Student
19. Parent/Guardian
20. Staff Member
21. Auditor

Super Admin must be able to create roles, assign permissions, configure modules, and control branch access.

A user may have access to one or multiple branches depending on assigned permissions.

Implement policies, middleware, and authorization checks on every protected operation.

Never rely only on hiding menu items to enforce access control.

## 5. MULTI-TENANT ARCHITECTURE

Implement:

- Tenant/Institution management.
- Branch management.
- Subdomain resolution.
- Tenant identification middleware.
- Branch-specific login and dashboards.
- Tenant-aware database queries.
- Tenant-specific configuration.
- Branch-specific academic sessions.
- Branch-specific fee structures.
- Branch-specific grading systems.
- Branch-specific report templates.
- Tenant-specific branding and logos.
- Branch-specific SMS and notification settings.
- Tenant-specific payment gateway configuration where supported.
- Centralized Super Admin reporting.
- Cross-tenant access prevention.
- Tenant-aware file and document storage.
- Audit logging.

Every tenant-owned database record must have appropriate tenant and branch relationships.

Use global scopes or a reliable tenant-context service where appropriate, but ensure that background jobs, console commands, exports, APIs, and scheduled tasks also enforce tenant isolation.

Provide a safe mechanism for switching branches for authorized users.

Prevent unauthorized access by changing IDs in URLs, forms, APIs, or request payloads.

## 6. SUPER ADMIN DASHBOARD

Create a modern centralized dashboard for the entire institution.

The dashboard must include:

- Total branches.
- Active and inactive branches.
- Total students.
- New admissions.
- Total teachers.
- Total employees.
- Students present today.
- Students absent today.
- Teachers present today.
- Teachers absent today.
- Attendance percentages.
- Today's fee collections.
- Monthly fee collections.
- Outstanding fees.
- Total expenses.
- Examination performance.
- Branch-wise student statistics.
- Branch-wise teacher statistics.
- Branch-wise attendance comparisons.
- Branch-wise admission trends.
- Branch-wise fee collection comparisons.
- Branch-wise outstanding dues.
- Academic performance comparisons.
- Recent activities and alerts.

Provide date filters, academic year filters, branch filters, class filters, and export options.

Use Chart.js to generate:

- Daily attendance line charts.
- Branch-wise attendance bar charts.
- Monthly revenue charts.
- Fee collection versus outstanding charts.
- Student admission growth charts.
- Examination performance charts.
- Branch comparison charts.

The Super Admin must be able to drill down from institution-level statistics to an individual branch, class, and authorized record.

Show clear loading, empty, and error states.

Use optimized database aggregation queries and caching for large datasets.

## 7. BRANCH ADMIN DASHBOARD

Each branch dashboard must show only the authorized branch's data.

Include:

- Total students and staff.
- Today's attendance.
- Class-wise attendance.
- Teacher attendance.
- New admissions.
- Fee collections.
- Outstanding payments.
- Upcoming examinations.
- Recent examination results.
- Timetable.
- Upcoming events.
- Pending tasks.
- Parent communications.
- Important notices.
- Branch-specific charts and reports.

## 8. STUDENT ADMISSION AND ENROLLMENT

Develop a complete admission management module.

Features:

- Online admission application.
- Offline admission entry.
- Admission application number.
- Applicant profile.
- Student photograph.
- Birth registration or other approved identity details.
- Parent/guardian information.
- Emergency contact.
- Present and permanent address.
- Previous school information.
- Required document uploads.
- Admission test management.
- Merit list.
- Waiting list.
- Application approval and rejection.
- Admission fee collection.
- Student ID generation.
- Student roll number generation.
- Class and section allocation.
- Session-based enrollment.
- Student transfer between branches.
- Student promotion.
- Student withdrawal.
- Student re-admission.
- Student history.
- Transfer certificate.
- Character certificate.
- Study certificate.

Support configurable admission forms, admission criteria, application deadlines, and required documents.

Prevent duplicate enrollment in the same academic session.

Maintain a permanent student record and a separate session-based enrollment record.

## 9. STUDENT INFORMATION SYSTEM

Create a comprehensive Student Information System.

Maintain:

- Student profile.
- Academic history.
- Class and section history.
- Attendance history.
- Examination results.
- Fee ledger.
- Payment history.
- Discipline records.
- Health and emergency information with restricted access.
- Library transactions.
- Transport allocation.
- Hostel allocation.
- Certificates.
- Uploaded documents.
- Parent/guardian relationships.
- Notifications and communication history.

Provide student search by name, ID, roll number, class, section, and session.

Support bulk import and export using CSV or Excel with validation and error reporting.

## 10. TEACHER AND HR MANAGEMENT

Implement a complete staff and HR module.

Features:

- Teacher and employee profiles.
- Employee ID.
- Department and designation.
- Branch assignment.
- Employment type.
- Joining date.
- Qualification and experience.
- Document management.
- Attendance.
- Leave applications.
- Leave approval.
- Duty roster.
- Timetable assignment.
- Salary structure.
- Payroll processing.
- Allowances and deductions.
- Payslip generation.
- Bonus and overtime.
- Employee evaluation.
- Staff notices.
- Employment history.
- Resignation and termination records.

Restrict confidential HR and salary data to authorized personnel.

## 11. STUDENT AND TEACHER ATTENDANCE

Implement a robust attendance management system.

Student attendance methods:

- Manual attendance.
- Class teacher attendance.
- Bulk attendance.
- QR code-based attendance.
- Student ID card scanning.
- Compatible biometric device integration through an approved API or middleware.
- Authorized online attendance where appropriate.

Teacher and employee attendance:

- Manual attendance.
- Check-in and check-out.
- QR-based attendance.
- Approved biometric integration.
- Late arrival tracking.
- Early departure tracking.
- Leave adjustment.
- Overtime calculation.

Attendance statuses:

- Present.
- Absent.
- Late.
- Half-day.
- Leave.
- Excused absence.

Required functionality:

- Daily attendance register.
- Class-wise attendance.
- Subject-wise attendance where applicable.
- Monthly attendance sheet.
- Attendance correction requests.
- Approval workflow.
- Attendance percentage.
- Consecutive absence alerts.
- Parent notifications.
- Daily and monthly reports.
- Branch-wise attendance comparisons.

Use Bangladesh local time consistently.

Prevent duplicate attendance records and unauthorized attendance modifications.

Maintain an audit trail for changes.

## 12. ACADEMIC MANAGEMENT

Implement:

- Academic years.
- Academic sessions.
- Terms and semesters.
- Classes and grades.
- Sections.
- Groups and streams.
- Subjects.
- Subject combinations.
- Class teachers.
- Subject teachers.
- Student enrollment.
- Student promotion.
- Academic calendars.
- Class routines.
- Teacher timetables.
- Room allocation.
- Class schedules.
- Syllabus management.
- Lesson plans.
- Homework.
- Assignments.
- Project submissions.
- Academic notices.
- Holiday calendars.

Support configurable Bangladeshi school and college academic structures.

Do not hardcode one specific board, grading system, or curriculum.

## 13. EXAMINATION AND RESULT MANAGEMENT

Create a complete examination system.

Features:

- Examination types.
- Class-wise examinations.
- Subject-wise examinations.
- Exam schedules.
- Exam rooms.
- Seat plans.
- Admit cards.
- Subject marks.
- Practical and theory marks.
- Continuous assessment.
- Class tests.
- Assignment marks.
- Attendance eligibility.
- Configurable grading systems.
- GPA and grade calculation.
- Subject-wise results.
- Total marks.
- Merit positions where institutionally permitted.
- Tabulation sheets.
- Mark sheets.
- Report cards.
- Transcript generation.
- Result publication.
- Result correction workflow.
- Result approval.
- Result history.
- PDF result cards.
- Parent and student result portals.

The grading formula must be configurable by academic session, class, examination, and institution.

Handle absent students, optional subjects, failed subjects, rounding rules, and incomplete marks correctly.

Never publish results before authorized approval.

Add automated tests for grading calculations.

## 14. FEE AND FINANCIAL MANAGEMENT

Develop a comprehensive fee management system.

Fee categories:

- Admission fee.
- Monthly tuition fee.
- Session fee.
- Examination fee.
- Laboratory fee.
- Library fee.
- Transport fee.
- Hostel fee.
- Development fee.
- Other configurable charges.

Features:

- Class-wise fee structures.
- Session-wise fee structures.
- Student-specific fees.
- Discounts.
- Scholarships.
- Waivers.
- Sibling discounts.
- Late fees.
- Installments.
- Monthly invoices.
- Automatic invoice generation.
- Due date management.
- Partial payments.
- Advance payments.
- Refunds.
- Payment receipts.
- Outstanding balance.
- Student fee ledger.
- Daily collection reports.
- Monthly collection reports.
- Branch-wise financial reports.
- Payment reconciliation.
- Financial audit logs.

Implement a double-entry accounting foundation or a clearly documented accounting integration.

Maintain separate financial records for each branch and consolidated institutional reports.

Never modify a completed financial transaction silently. Use reversals, refunds, and adjustment entries with proper authorization and audit history.

## 15. PAYMENT GATEWAY INTEGRATION

Design a payment gateway abstraction layer.

Support integration with available and approved providers in Bangladesh, such as:

- SSLCOMMERZ.
- aamarPay.
- shurjoPay.
- bKash Merchant Payment API.
- Nagad Merchant Payment API.

Use the actual provider's current official API documentation during implementation.

Requirements:

- Secure checkout.
- Payment initiation.
- Server-side callback verification.
- Signature or hash verification.
- Transaction ID.
- Payment status.
- Idempotent webhook handling.
- Duplicate callback prevention.
- Payment reconciliation.
- Refund workflow where supported.
- Failed payment handling.
- Payment receipt.
- Sandbox mode.
- Production configuration.
- Branch-specific merchant credentials when supported.

Never trust a frontend success redirect as proof of payment.

Never hardcode API secrets or merchant credentials.

Store secrets in environment variables or an appropriate secret manager.

## 16. SMS, EMAIL, WHATSAPP AND NOTIFICATIONS

Create a unified notification system.

Channels:

- SMS.
- Email.
- WhatsApp Business Platform.
- In-app notifications.
- Browser notifications where appropriate.

Use provider adapters so services can be changed without rewriting business logic.

Support notifications for:

- Admission confirmation.
- Fee invoices.
- Successful payments.
- Outstanding fees.
- Student absence.
- Teacher absence.
- Examination schedules.
- Published results.
- Parent-teacher meetings.
- Homework.
- School holidays.
- Emergency notices.
- General announcements.

Implement:

- Bangla and English message templates.
- Template variables.
- Scheduled messages.
- Bulk messaging.
- Delivery status tracking where supported.
- Retry handling.
- Queue-based processing.
- Consent and opt-out management.
- Notification history.
- Per-branch provider configuration.
- Message usage reports.

For WhatsApp, use the official WhatsApp Business Platform or an authorized provider. Respect approved template requirements and applicable messaging rules.

Do not assume unrestricted automated WhatsApp messaging is available.

## 17. ONLINE AND OFFLINE CLASSES

Implement a blended learning management system.

Features:

- Online class scheduling.
- Offline classroom scheduling.
- Course and subject management.
- Live class links.
- Recorded lessons.
- Video lesson library.
- Class materials.
- PDF and document uploads.
- Homework and assignments.
- Assignment submissions.
- Teacher feedback.
- Student progress tracking.
- Class attendance.
- Online quizzes.
- Learning resources.
- Parent access to learning progress.

## 18. ZOOM AND GOOGLE MEET INTEGRATION

Implement integration adapters for Zoom and Google Meet.

Zoom:

- OAuth or the supported server-to-server authentication method.
- Meeting creation.
- Meeting scheduling.
- Meeting links.
- Meeting details.
- Host and participant information where authorized.
- Meeting cancellation.
- Meeting update.
- Recording links where permitted and available.

Google Meet:

- Google OAuth.
- Google Calendar API integration.
- Meeting creation through the supported Google Calendar workflow.
- Calendar event scheduling.
- Meet conference links.
- Event updates and cancellations.

Requirements:

- Separate configuration for each institution or branch where appropriate.
- Secure token storage.
- Token refresh.
- API error handling.
- Permission validation.
- Time zone handling.
- Integration logs.
- User-friendly fallback if an API is unavailable.

Use official APIs and current documentation.

Do not promise recording access or meeting capabilities that the provider's account permissions do not support.

## 19. TIMETABLE AND CLASS ROUTINE

Implement:

- Class routine builder.
- Teacher timetable.
- Subject timetable.
- Room timetable.
- Period configuration.
- Break schedules.
- Teacher availability.
- Room conflict detection.
- Teacher double-booking prevention.
- Class scheduling conflict detection.
- Printable routines.
- PDF export.
- Student and parent timetable views.

## 20. LIBRARY MANAGEMENT

Include:

- Book catalog.
- ISBN.
- Categories.
- Authors.
- Publishers.
- Book copies.
- Barcode support.
- Book issue and return.
- Renewal.
- Reservation.
- Overdue tracking.
- Fines.
- Lost or damaged books.
- Student and staff library accounts.
- Library reports.

## 21. TRANSPORT MANAGEMENT

Include:

- Vehicle records.
- Vehicle registration.
- Driver records.
- Route management.
- Stops.
- Transport fees.
- Student transport allocation.
- Seat capacity.
- Route schedules.
- Vehicle maintenance records.
- Fuel records.
- Driver attendance.
- Transport payment reports.
- Emergency contacts.

Keep live GPS tracking as an optional integration module with explicit provider configuration and appropriate consent.

## 22. HOSTEL AND FACILITY MANAGEMENT

Include:

- Hostel management.
- Buildings and floors.
- Rooms and beds.
- Student allocation.
- Hostel fees.
- Meal plans where applicable.
- Hostel attendance.
- Visitor records.
- Maintenance requests.
- Room transfer.
- Vacating records.
- Hostel reports.

## 23. INVENTORY, LABORATORY AND ASSETS

Implement:

- School inventory.
- Purchase requests.
- Suppliers.
- Purchase orders.
- Goods receiving.
- Stock management.
- Asset registers.
- Asset allocation.
- Maintenance schedules.
- Laboratory equipment.
- Consumable stock.
- Stock adjustments.
- Inventory audit.
- Branch-wise inventory reports.

## 24. PARENT, STUDENT AND TEACHER PORTALS

Create separate responsive portals.

Parent portal:

- Multiple children under one account.
- Attendance.
- Results.
- Fee invoices.
- Online payment.
- Payment history.
- Homework.
- Class routine.
- Notices.
- Teacher communication.
- Leave applications.
- Academic progress.
- Notifications.

Student portal:

- Profile.
- Attendance.
- Timetable.
- Homework.
- Assignments.
- Results.
- Admit cards.
- Fee status.
- Learning materials.
- Online class links.
- Notices.

Teacher portal:

- Assigned classes.
- Timetable.
- Attendance marking.
- Homework.
- Assignment evaluation.
- Marks entry.
- Student performance.
- Leave applications.
- Notices.
- Parent communication.

## 25. COMMUNICATION AND NOTICE BOARD

Create an institutional communication center.

Features:

- Institution-wide notices.
- Branch-specific notices.
- Class-specific notices.
- Student-specific notices.
- Parent notices.
- Teacher notices.
- Emergency announcements.
- Notice attachments.
- Notice scheduling.
- Notice expiration.
- Read receipts where appropriate.
- SMS and email distribution.
- Notice archive.

Only authorized users may publish official notices.

## 26. ACCOUNTING AND FINANCIAL REPORTING

Provide:

- Chart of accounts.
- Income and expense categories.
- Cash and bank accounts.
- Payment vouchers.
- Receipt vouchers.
- Journal entries.
- General ledger.
- Trial balance.
- Income and expenditure statement.
- Balance sheet where the accounting model supports it.
- Cash flow reporting.
- Bank reconciliation.
- Budget management.
- Branch-wise accounts.
- Consolidated institutional reports.
- Financial year configuration.
- Audit trail.

Financial reports must be based on properly recorded transactions, not manually entered dashboard totals.

## 27. REPORTING AND DATA EXPORT

Implement comprehensive reports.

Required reports:

- Student register.
- Admission register.
- Student attendance.
- Teacher attendance.
- Staff attendance.
- Fee collection.
- Outstanding dues.
- Discounts and waivers.
- Income and expenses.
- Examination results.
- Student performance.
- Teacher workload.
- Payroll.
- Library.
- Transport.
- Hostel.
- Inventory.
- Branch comparison.
- Audit logs.

Provide:

- PDF export.
- Excel export.
- CSV export.
- Print-friendly layouts.
- Date filters.
- Session filters.
- Branch filters.
- Class filters.
- Section filters.
- Permission-based access.

Use queued jobs for large exports.

## 28. DOCUMENT AND CERTIFICATE GENERATION

Create customizable templates for:

- Student ID cards.
- Teacher ID cards.
- Admit cards.
- Payment receipts.
- Fee invoices.
- Report cards.
- Transcripts.
- Transfer certificates.
- Character certificates.
- Study certificates.
- Salary slips.
- Appointment letters.
- Experience certificates.

Support Bangla and English text, institution branding, logos, signatures, and configurable layouts.

Use a PDF library with appropriate Unicode font support.

## 29. MAIN WEBSITE AND ADMISSION WEBSITE

Create a public institutional website with:

- Home page.
- About the institution.
- Branch directory.
- Principal and administration information.
- Academic programs.
- Admission notices.
- Online admission.
- News and events.
- Notices.
- Results lookup.
- Contact information.
- Image gallery.
- Download center.
- Frequently asked questions.
- SEO-friendly URLs.
- Sitemap.
- Robots.txt.
- Privacy policy.
- Terms and conditions.

Allow authorized administrators to manage website content through a CMS.

The public website must not expose private student or financial information.

## 30. DATABASE DESIGN

Create a normalized, scalable MySQL schema.

At minimum, consider the following tables:

- institutions
- branches
- domains
- users
- roles
- permissions
- model_has_roles
- model_has_permissions
- academic_years
- academic_sessions
- classes
- sections
- subjects
- students
- student_guardians
- student_enrollments
- student_documents
- admission_applications
- teachers
- employees
- departments
- designations
- staff_branch_assignments
- student_attendances
- staff_attendances
- attendance_correction_requests
- leave_applications
- timetables
- timetable_periods
- examinations
- examination_subjects
- marks
- grading_scales
- results
- fee_categories
- fee_structures
- student_fee_assignments
- invoices
- invoice_items
- payments
- payment_transactions
- refunds
- accounting_accounts
- journal_entries
- journal_entry_lines
- expenses
- payrolls
- payroll_items
- notification_templates
- notifications
- notification_logs
- online_classes
- online_class_integrations
- assignments
- assignment_submissions
- library_books
- library_book_copies
- library_transactions
- vehicles
- transport_routes
- transport_allocations
- hostels
- hostel_rooms
- hostel_allocations
- inventory_items
- stock_transactions
- assets
- notices
- documents
- audit_logs
- system_settings

Add all necessary supporting tables.

For each table, define:

- Primary key.
- Foreign keys.
- Tenant and branch relationships.
- Appropriate indexes.
- Unique constraints.
- Nullable fields.
- Created and updated timestamps.
- Soft deletes where appropriate.

Use database transactions for critical operations.

Define deletion and archival policies for financial, academic, and student records.

Avoid unnecessary duplication and maintain referential integrity.

## 31. SECURITY REQUIREMENTS

Implement security as a core requirement.

Include:

- Laravel authentication.
- Role-based authorization.
- CSRF protection.
- XSS prevention.
- SQL injection prevention.
- Input validation.
- Secure password hashing.
- Login rate limiting.
- Password reset.
- Optional two-factor authentication.
- Session security.
- Secure file uploads.
- MIME type and file size validation.
- Private document storage.
- Signed URLs or authorized downloads.
- Tenant isolation.
- API authentication.
- Webhook verification.
- Audit logging.
- Sensitive data access restrictions.
- Secure secrets management.
- Backup and recovery.
- Data retention policies.

Use HTTPS in production.

Do not expose passwords, API secrets, payment credentials, private student data, or sensitive documents in logs or API responses.

Follow applicable Bangladesh data protection, child privacy, education, and payment-related requirements. Identify any legal requirements that need institutional verification.

## 32. PERFORMANCE AND SCALABILITY

Optimize for thousands of students and hundreds of staff members across multiple branches.

Implement:

- Database indexing.
- Query optimization.
- Eager loading.
- Pagination.
- Caching.
- Queue-based notifications.
- Background exports.
- Scheduled fee generation.
- Scheduled reminders.
- Efficient dashboard aggregation.
- File storage optimization.
- Rate limiting.
- Monitoring and error logging.

Avoid N+1 query problems.

Do not load every student's record into memory for dashboard statistics.

Use database aggregation and appropriately scoped queries.

## 33. USER INTERFACE AND UX

Create a professional, modern school management interface.

Design requirements:

- Clean administrative dashboard.
- Responsive sidebar navigation.
- Top navigation bar.
- Institution and branch logo.
- User profile menu.
- Breadcrumbs.
- Dashboard statistics cards.
- Interactive charts.
- Searchable data tables.
- Advanced filters.
- Pagination.
- Confirmation dialogs.
- Form validation.
- Toast notifications.
- Empty states.
- Loading indicators.
- Error states.
- Accessible controls.
- Consistent typography.
- Mobile-friendly layouts.

Support:

- English.
- Bangla.
- Language switching.
- Bangla-compatible Unicode fonts.
- Institution-specific branding.
- Configurable colors and logos.

Use a consistent design system across the Super Admin, Branch Admin, Teacher, Student, and Parent portals.

## 34. SYSTEM SETTINGS

Provide centralized and branch-level settings.

Include:

- Institution profile.
- Branch profile.
- Logo and favicon.
- Domain and subdomain.
- Address and contact details.
- Academic year.
- Time zone.
- Currency.
- Date format.
- Language.
- Grading configuration.
- Fee configuration.
- Notification providers.
- Payment gateways.
- Online meeting integrations.
- Email configuration.
- SMS configuration.
- Document templates.
- Backup configuration.
- Security settings.
- User permissions.
- Maintenance mode.

Use Bangladesh Standard Time (Asia/Dhaka) as the default time zone and BDT as the default currency.

## 35. API DEVELOPMENT

Create a versioned REST API.

Example:

/api/v1/auth
/api/v1/branches
/api/v1/students
/api/v1/admissions
/api/v1/attendance
/api/v1/teachers
/api/v1/examinations
/api/v1/results
/api/v1/fees
/api/v1/payments
/api/v1/notices
/api/v1/online-classes
/api/v1/reports

Implement:

- Authentication.
- Authorization.
- Tenant identification.
- Request validation.
- Consistent JSON responses.
- Pagination.
- Filtering.
- Rate limiting.
- API error handling.
- API documentation.
- Integration tests.

Do not expose unauthorized cross-tenant data through API endpoints.

## 36. AUTOMATION AND SCHEDULING

Implement Laravel scheduled tasks for:

- Monthly fee invoice generation.
- Fee due reminders.
- Late fee calculation.
- Attendance summaries.
- Consecutive absence alerts.
- Examination reminders.
- Upcoming event reminders.
- Scheduled notices.
- Payroll processing support.
- Backup monitoring.
- Subscription renewal reminders if SaaS billing is enabled.

Ensure scheduled jobs are idempotent and tenant-aware.

Prevent duplicate invoices and duplicate notifications.

Provide a failed-job monitoring and retry mechanism.

## 37. SAAS SUBSCRIPTION MANAGEMENT

Design the platform to support commercial SaaS operation.

Include:

- Subscription plans.
- Trial periods.
- Monthly and yearly billing.
- Branch-based or student-capacity-based plans.
- Feature limits.
- Storage limits.
- User limits.
- Subscription invoices.
- Payment records.
- Renewal reminders.
- Grace periods.
- Subscription suspension.
- Subscription reactivation.
- Plan upgrades and downgrades.
- Usage statistics.
- Platform-level revenue reports.

Keep institution subscription billing separate from the school's internal student fee collection.

Allow the platform owner to offer the system to additional institutions in the future.

## 38. BACKUP AND DISASTER RECOVERY

Implement:

- Database backup strategy.
- Uploaded document backup.
- Scheduled backups.
- Backup status monitoring.
- Restore documentation.
- Tenant data export.
- Data recovery procedures.
- Production environment configuration.

Do not store database backups in a publicly accessible directory.

Document backup frequency, retention, encryption, and restoration testing.

## 39. TESTING REQUIREMENTS

Write automated tests for:

- Authentication.
- Role-based permissions.
- Tenant isolation.
- Branch isolation.
- Student admission.
- Student enrollment.
- Attendance creation.
- Attendance correction.
- Fee invoice generation.
- Partial payment.
- Payment callback verification.
- Duplicate payment callback prevention.
- Examination mark entry.
- GPA and grade calculations.
- Result publication.
- Parent access to children.
- Teacher access to assigned classes.
- Cross-branch access prevention.
- Dashboard statistics.
- Scheduled jobs.
- API authorization.

Use realistic factories and seeders.

Include test cases for unauthorized access, invalid input, duplicate records, and failed integrations.

## 40. REQUIRED PROJECT STRUCTURE AND SOURCE CODE

Deliver the complete project in a clear, maintainable structure.

Include:

- Composer dependencies.
- Environment example file.
- Laravel configuration.
- Database migrations.
- Eloquent models.
- Relationships.
- Factories.
- Seeders.
- Middleware.
- Policies.
- Form Requests.
- Controllers.
- Services.
- Repositories where justified.
- Jobs.
- Events.
- Listeners.
- Notifications.
- Console commands.
- Scheduled tasks.
- API routes.
- Web routes.
- Blade layouts.
- Reusable components.
- Admin dashboard views.
- Branch dashboard views.
- Teacher portal views.
- Student portal views.
- Parent portal views.
- CSS and JavaScript assets.
- Localization files.
- Payment gateway adapters.
- SMS adapters.
- WhatsApp adapters.
- Zoom integration.
- Google Meet integration.
- Automated tests.
- Deployment scripts.
- Installation guide.
- API documentation.
- Backup and restore guide.

Use a modular architecture with clearly separated domains.

Do not put the entire application into a single controller or a few oversized files.

## 41. IMPLEMENTATION WORKFLOW

Follow this development workflow strictly.

PHASE 1: Architecture and planning
- Confirm the Laravel and PHP versions.
- Define the architecture.
- Define the tenant resolution strategy.
- Define the domain and subdomain configuration.
- Define the module structure.
- Define the database relationships.
- Identify integration dependencies.
- Document security boundaries.

PHASE 2: Core application
- Set up Laravel.
- Configure MySQL.
- Implement authentication.
- Implement institution and branch management.
- Implement tenant middleware.
- Implement permissions.
- Create database migrations and seeders.
- Create the initial dashboards.

PHASE 3: Academic modules
- Student admission.
- Student information.
- Teacher and staff management.
- Academic sessions.
- Classes and subjects.
- Attendance.
- Timetables.
- Examinations.
- Results.

PHASE 4: Financial modules
- Fee structures.
- Invoices.
- Payments.
- Accounting.
- Expenses.
- Payroll.
- Financial reports.

PHASE 5: Communication and integrations
- SMS.
- Email.
- WhatsApp.
- Payment gateways.
- Zoom.
- Google Meet.
- Online learning.

PHASE 6: Additional modules
- Library.
- Transport.
- Hostel.
- Inventory.
- Website CMS.
- Certificates.
- Reports.

PHASE 7: SaaS and production readiness
- Subscription billing.
- Performance optimization.
- Security testing.
- Automated testing.
- Backup and recovery.
- Production deployment.
- Monitoring.

PHASE 8: Final delivery
- Verify installation.
- Run migrations.
- Run automated tests.
- Verify tenant isolation.
- Verify payment callback handling.
- Verify dashboard calculations.
- Document all environment variables.
- Provide deployment and maintenance instructions.

## 42. SOURCE CODE DELIVERY RULES

I need actual working source code, not merely descriptions.

For every implementation phase:

1. Explain the purpose of the module briefly.
2. Show the exact file path.
3. Provide the complete file contents.
4. Include all required imports.
5. Include migrations and relationships.
6. Include validation and authorization.
7. Include routes and views.
8. Include relevant tests.
9. Explain how to run and verify the module.
10. List any external credentials or services required.

Never use placeholders such as:

- "Implement this later."
- "Add your logic here."
- "Code omitted for brevity."
- "Continue similarly."
- "The remaining files are straightforward."

Do not claim an integration is complete if it has only a mock implementation.

If credentials are unavailable, implement a documented adapter and sandbox/test mode. Clearly identify what must be configured before production use.

Use maintainable, production-oriented code and follow Laravel conventions.

## 43. DEVELOPMENT ENVIRONMENT AND DEPLOYMENT

Provide installation instructions for:

- Local development on Windows.
- Linux server deployment.
- Apache or Nginx.
- MySQL database setup.
- Environment configuration.
- Composer installation.
- Frontend asset compilation.
- Database migrations.
- Seeder execution.
- Storage linking.
- Queue workers.
- Laravel Scheduler.
- SSL certificates.
- Wildcard DNS.
- Wildcard subdomain configuration.
- File permissions.
- Production cache optimization.
- Log management.
- Backup setup.

Include the required DNS configuration for:

- Main domain.
- Wildcard subdomain.
- SSL certificate.
- HTTPS redirection.

Provide an example Nginx or Apache virtual host configuration.

Document the deployment architecture and production prerequisites.

## 44. ACCEPTANCE CRITERIA

The project is complete only when:

1. The main domain can access the institutional website and central administration.
2. Ten branch subdomains resolve to the correct branches.
3. Authorized users can log in to their respective portals.
4. Branch users cannot access another branch's private records.
5. Super Admin can view consolidated institutional statistics.
6. Student admission and enrollment work correctly.
7. Teacher and student attendance work correctly.
8. Fees, invoices, and payments work correctly.
9. Examination marks and results calculate correctly.
10. Parent and student portals show authorized information.
11. SMS and email notifications can be configured and tested.
12. Payment gateway sandbox transactions can be verified.
13. Zoom and Google Meet integrations work when valid credentials are configured.
14. Reports can be filtered and exported.
15. Automated tests cover critical workflows.
16. Installation and deployment documentation is complete.
17. The application can add new branches without changing its core code.
18. Sensitive records are protected by authorization and tenant isolation.
19. Dashboard statistics are calculated from actual database records.
20. No critical module is represented as complete when it is only a mockup.

## 45. FIRST RESPONSE AND EXECUTION INSTRUCTIONS

Begin by presenting:

A. The recommended technical architecture.

B. The project directory structure.

C. The complete database schema and entity relationships.

D. The multi-tenant and subdomain implementation strategy.

E. The implementation roadmap.

F. The security strategy.

G. The required environment and deployment setup.

After presenting the architecture, immediately begin implementing Phase 1 and Phase 2 with actual Laravel source code.

Do not stop after producing the plan.

Because the entire platform is too large for one response, divide the implementation into clearly numbered parts. Maintain a progress checklist and a file manifest.

At the end of each response, state:
- What has been completed.
- Which files have been delivered.
- What remains.
- The exact next implementation step.

When I reply "CONTINUE", resume from the next unfinished task without repeating completed code.

Maintain consistent naming, relationships, route names, database columns, and architectural decisions across all parts.

Do not silently change previous architectural decisions. If a change is necessary, explain the reason and provide the migration strategy.

Start now by designing the architecture and implementing the foundational Laravel application.
