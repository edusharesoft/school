<?php

/*
|--------------------------------------------------------------------------
| Roles & permission catalogue
|--------------------------------------------------------------------------
| Permissions are "<module>.<action>". The catalogue is synced into the
| `permissions` table by RolesAndPermissionsSeeder (idempotent, safe to re-run
| on every deploy). Roles listed under `roles` are *system roles*: they can be
| extended with extra permissions from the UI but cannot be deleted or renamed.
| Super Admin can create any number of additional custom roles.
|
| Role permission patterns support:
|   '*'            every permission
|   'students.*'   every action in a module
|   '!payroll.*'   exclusion (applied after inclusions)
*/

$crud = ['view', 'create', 'update', 'delete'];

return [

    'permissions' => [
        // Platform / central administration (Part 2)
        'central' => ['access'],
        'dashboard' => ['central', 'branch', 'export'],
        'institutions' => $crud,
        'branches' => [...$crud, 'access-all', 'switch'],
        'users' => [...$crud, 'impersonate'],
        'roles' => $crud,
        'audit-logs' => ['view'],
        'settings' => ['view', 'update'],
        'departments' => $crud,

        // Academic (Part 3)
        'academics' => $crud,
        'admissions' => [...$crud, 'approve', 'collect-fee'],
        'students' => [...$crud, 'import', 'export', 'transfer', 'promote', 'view-health'],
        'guardians' => $crud,
        'employees' => [...$crud, 'view-salary'],
        'leaves' => [...$crud, 'approve'],
        'student-attendance' => ['view', 'mark', 'correct', 'approve-correction'],
        'staff-attendance' => ['view', 'mark', 'correct', 'approve-correction'],
        'timetables' => $crud,
        'homework' => $crud,
        'examinations' => $crud,
        'marks' => ['view', 'enter', 'approve-correction'],
        'results' => ['view', 'process', 'approve', 'publish'],
        'certificates' => ['view', 'issue'],
        'staff-certificates' => ['view', 'issue'],
        'id-cards' => ['view', 'issue', 'revoke'],

        // Finance (Part 4)
        'fees' => $crud,
        'invoices' => [...$crud, 'generate'],
        'payments' => ['view', 'collect', 'refund', 'reconcile'],
        'accounting' => ['view', 'post', 'reverse'],
        'expenses' => [...$crud, 'approve'],
        'payroll' => ['view', 'process', 'approve'],
        'financial-reports' => ['view', 'export'],

        // Communication & learning (Part 5)
        'notices' => [...$crud, 'publish'],
        'notifications' => ['view', 'send', 'configure'],
        'online-classes' => $crud,
        'assignments' => [...$crud, 'evaluate'],
        'materials' => $crud,
        'quizzes' => $crud,
        'integrations' => ['view', 'configure'],

        // Operations (Part 6)
        'library' => [...$crud, 'issue'],
        'transport' => $crud,
        'hostel' => $crud,
        'inventory' => [...$crud, 'approve'],
        'assets' => [...$crud, 'approve'],
        'website' => $crud,
        'reports' => ['view', 'export'],

        // SaaS (Part 7)
        'subscriptions' => $crud,
        'backups' => ['view', 'run'],
    ],

    /*
    | System roles (master prompt §4). name => [label, label_bn, scope, portal, permissions]
    |  scope  : central = may use the central area; branch = branch area only
    |  portal : which home the user lands on after login (admin|teacher|student|parent)
    */
    'roles' => [
        'super-admin' => [
            'label' => 'Super Admin', 'label_bn' => 'সুপার অ্যাডমিন', 'scope' => 'central', 'portal' => 'admin',
            'permissions' => ['*'],
        ],
        'institution-admin' => [
            'label' => 'Institution Administrator', 'label_bn' => 'প্রতিষ্ঠান প্রশাসক', 'scope' => 'central', 'portal' => 'admin',
            'permissions' => ['*', '!institutions.create', '!institutions.delete', '!subscriptions.*', '!backups.run'],
        ],
        'branch-admin' => [
            'label' => 'Branch Administrator', 'label_bn' => 'শাখা প্রশাসক', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => [
                'dashboard.branch', 'dashboard.export', 'settings.*', 'users.view', 'users.create', 'users.update', 'roles.view',
                'audit-logs.view', 'departments.*', 'academics.*', 'admissions.*', 'students.*', 'guardians.*', 'employees.*',
                'leaves.*', 'student-attendance.*', 'staff-attendance.*', 'timetables.*', 'homework.*', 'examinations.*',
                'marks.*', 'results.*', 'certificates.*', 'staff-certificates.*', 'id-cards.*', 'fees.*', 'invoices.*', 'payments.*', 'accounting.view',
                'expenses.*', 'payroll.view', 'payroll.approve', 'financial-reports.*', 'notices.*', 'notifications.*', 'online-classes.*',
                'assignments.*', 'materials.*', 'quizzes.*', 'library.*', 'transport.*', 'hostel.*', 'inventory.*', 'assets.*', 'reports.*',
                'integrations.view', 'integrations.configure', 'website.*', 'branches.view', 'branches.switch',
            ],
        ],
        'principal' => [
            'label' => 'Principal', 'label_bn' => 'অধ্যক্ষ', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => [
                'dashboard.branch', 'dashboard.export', 'departments.view', 'academics.*', 'admissions.*', 'students.*',
                'guardians.*', 'employees.view', 'leaves.*', 'student-attendance.*', 'staff-attendance.*', 'timetables.*',
                'homework.*', 'examinations.*', 'marks.*', 'results.*', 'certificates.*', 'staff-certificates.*', 'id-cards.*', 'fees.view', 'invoices.view',
                'payments.view', 'expenses.view', 'expenses.approve', 'payroll.view', 'payroll.approve', 'financial-reports.view', 'notices.*',
                'notifications.view', 'notifications.send', 'online-classes.*', 'assignments.view', 'materials.view', 'quizzes.view', 'reports.*',
                'audit-logs.view', 'website.view', 'website.update', 'branches.view', 'assets.view', 'assets.approve',
            ],
        ],
        'vice-principal' => [
            'label' => 'Vice Principal', 'label_bn' => 'উপাধ্যক্ষ', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => [
                'dashboard.branch', 'departments.view', 'academics.*', 'admissions.view', 'admissions.approve', 'students.view',
                'students.update', 'students.promote', 'guardians.view', 'employees.view', 'leaves.view', 'leaves.approve',
                'student-attendance.*', 'staff-attendance.*', 'timetables.*', 'homework.*', 'examinations.*', 'marks.*',
                'results.view', 'results.process', 'results.approve', 'certificates.view', 'notices.*', 'notifications.send',
                'online-classes.*', 'assignments.view', 'materials.view', 'quizzes.view', 'reports.view',
            ],
        ],
        'academic-coordinator' => [
            'label' => 'Academic Coordinator', 'label_bn' => 'একাডেমিক সমন্বয়ক', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => [
                'dashboard.branch', 'academics.*', 'students.view', 'timetables.*', 'homework.*', 'examinations.view',
                'marks.view', 'results.view', 'online-classes.*', 'assignments.*', 'materials.*', 'quizzes.*', 'notices.view', 'notices.create',
                'student-attendance.view', 'reports.view',
            ],
        ],
        'teacher' => [
            'label' => 'Teacher', 'label_bn' => 'শিক্ষক', 'scope' => 'branch', 'portal' => 'teacher',
            'permissions' => [
                'students.view', 'student-attendance.view', 'student-attendance.mark', 'timetables.view', 'homework.*',
                'marks.view', 'marks.enter', 'examinations.view', 'online-classes.view', 'online-classes.create',
                'online-classes.update', 'assignments.*', 'materials.*', 'quizzes.*', 'notices.view', 'leaves.view', 'leaves.create',
            ],
        ],
        'class-teacher' => [
            'label' => 'Class Teacher', 'label_bn' => 'শ্রেণি শিক্ষক', 'scope' => 'branch', 'portal' => 'teacher',
            'permissions' => [
                'students.view', 'guardians.view', 'student-attendance.view', 'student-attendance.mark',
                'student-attendance.correct', 'timetables.view', 'homework.*', 'marks.view', 'marks.enter',
                'examinations.view', 'results.view', 'online-classes.view', 'online-classes.create', 'online-classes.update',
                'assignments.*', 'materials.*', 'quizzes.*', 'notices.view', 'notices.create', 'leaves.view', 'leaves.create', 'notifications.send',
            ],
        ],
        'accountant' => [
            'label' => 'Accountant', 'label_bn' => 'হিসাবরক্ষক', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => [
                'dashboard.branch', 'students.view', 'fees.*', 'invoices.*', 'payments.*', 'accounting.*', 'expenses.*',
                'payroll.view', 'payroll.process', 'financial-reports.*', 'admissions.collect-fee', 'reports.view', 'reports.export',
            ],
        ],
        'admission-officer' => [
            'label' => 'Admission Officer', 'label_bn' => 'ভর্তি কর্মকর্তা', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => [
                'dashboard.branch', 'admissions.view', 'admissions.create', 'admissions.update', 'admissions.collect-fee',
                'students.view', 'students.create', 'guardians.*', 'certificates.view', 'reports.view',
            ],
        ],
        'exam-controller' => [
            'label' => 'Examination Controller', 'label_bn' => 'পরীক্ষা নিয়ন্ত্রক', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => [
                'dashboard.branch', 'students.view', 'examinations.*', 'marks.*', 'results.view', 'results.process',
                'results.approve', 'results.publish', 'certificates.*', 'reports.view', 'reports.export',
            ],
        ],
        'librarian' => [
            'label' => 'Librarian', 'label_bn' => 'গ্রন্থাগারিক', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => ['students.view', 'library.*', 'reports.view'],
        ],
        'store-keeper' => [
            'label' => 'Store Keeper', 'label_bn' => 'স্টোর কিপার', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => ['inventory.view', 'inventory.create', 'inventory.update', 'assets.view', 'reports.view'],
        ],
        'lab-manager' => [
            'label' => 'Laboratory Manager', 'label_bn' => 'ল্যাবরেটরি ব্যবস্থাপক', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => ['inventory.view', 'inventory.create', 'inventory.update', 'assets.view', 'assets.create', 'assets.update', 'reports.view'],
        ],
        'hr-manager' => [
            'label' => 'HR Manager', 'label_bn' => 'মানবসম্পদ ব্যবস্থাপক', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => [
                'dashboard.branch', 'departments.*', 'employees.*', 'leaves.*', 'staff-attendance.*', 'payroll.*', 'staff-certificates.*',
                'users.view', 'users.create', 'users.update', 'reports.view', 'reports.export',
            ],
        ],
        'transport-manager' => [
            'label' => 'Transport Manager', 'label_bn' => 'পরিবহন ব্যবস্থাপক', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => ['students.view', 'transport.*', 'reports.view'],
        ],
        'hostel-manager' => [
            'label' => 'Hostel Manager', 'label_bn' => 'হোস্টেল ব্যবস্থাপক', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => ['students.view', 'hostel.*', 'reports.view'],
        ],
        'receptionist' => [
            'label' => 'Receptionist', 'label_bn' => 'অভ্যর্থনাকারী', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => ['admissions.view', 'admissions.create', 'students.view', 'guardians.view', 'notices.view'],
        ],
        'student' => [
            'label' => 'Student', 'label_bn' => 'শিক্ষার্থী', 'scope' => 'branch', 'portal' => 'student',
            'permissions' => [],
        ],
        'parent' => [
            'label' => 'Parent/Guardian', 'label_bn' => 'অভিভাবক', 'scope' => 'branch', 'portal' => 'parent',
            'permissions' => [],
        ],
        'staff' => [
            'label' => 'Staff Member', 'label_bn' => 'কর্মচারী', 'scope' => 'branch', 'portal' => 'admin',
            'permissions' => ['notices.view', 'leaves.view', 'leaves.create'],
        ],
        'auditor' => [
            'label' => 'Auditor', 'label_bn' => 'নিরীক্ষক', 'scope' => 'central', 'portal' => 'admin',
            'permissions' => [
                'central.access', 'dashboard.central', 'dashboard.branch', 'branches.view', 'branches.access-all', 'audit-logs.view',
                'fees.view', 'invoices.view', 'payments.view', 'accounting.view', 'expenses.view', 'payroll.view',
                'financial-reports.*', 'reports.view', 'reports.export',
            ],
        ],
    ],

    // Roles that only the platform Super Admin may assign.
    'protected_roles' => ['super-admin'],
];
