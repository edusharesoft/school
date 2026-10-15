<?php

/*
|--------------------------------------------------------------------------
| Library defaults
|--------------------------------------------------------------------------
| Every branch can override these under Library → Rules (stored in branches.settings->library).
| Fines are in the branch currency per day; "lost_fee_percent" is a share of the book price.
*/

return [
    'defaults' => [
        'student_max_books' => 3,
        'student_loan_days' => 14,
        'employee_max_books' => 5,
        'employee_loan_days' => 30,
        'max_renewals' => 2,
        'fine_per_day' => 2.00,
        'grace_days' => 0,
        'max_fine_per_loan' => 0,        // 0 = no cap
        'lost_fee_percent' => 100,       // of the book price, charged when a copy is lost
        'block_on_unpaid_fines' => true,
        'block_when_overdue' => true,
        'reservation_hold_days' => 3,    // how long a returned copy is kept for the next person in the queue
        'overdue_reminder_days' => 3,    // repeat the overdue reminder at most this often
    ],

    'languages' => ['bn' => 'Bangla', 'en' => 'English', 'ar' => 'Arabic', 'hi' => 'Hindi', 'other' => 'Other'],
];
