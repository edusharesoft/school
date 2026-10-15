<?php

/*
|--------------------------------------------------------------------------
| Notification hub (Part 5-1)
|--------------------------------------------------------------------------
| Events the hub can send, their variables, default channels and the default Bangla / English templates.
| A branch can override any template (Communication → Templates) and switch channels per event
| (Communication → Settings). Variables are written as {name}. Unknown variables render as empty text.
|
| WhatsApp: business-initiated messages must use a template approved on the WhatsApp Business account.
| 'whatsapp_params' is the order of the {{1}}, {{2}} … parameters of that template; the template name
| is set per branch. Without an approved template name the WhatsApp channel is skipped for that event.
*/

$sign = ' - {branch}';

return [

    'channels' => ['in_app', 'sms', 'email', 'whatsapp'],

    'sms_drivers' => [
        'log' => ['label' => 'Log only (test — nothing is sent)', 'fields' => []],
        'shataj_sms' => ['label' => 'Shataj SMS (our own gateway)', 'fields' => ['api_key', 'server', 'device']],
        'ssl_wireless' => ['label' => 'SSL Wireless (ISMS Plus)', 'fields' => ['api_token', 'sid']],
        'bulksmsbd' => ['label' => 'BulkSMSBD', 'fields' => ['api_key', 'sender_id']],
        'alpha_sms' => ['label' => 'Alpha SMS (sms.net.bd)', 'fields' => ['api_key', 'sender_id']],
    ],

    // Retry policy for SMS / e-mail / WhatsApp (seconds between attempts).
    'max_attempts' => 3,
    'backoff' => [60, 300, 900],

    'events' => [
        'admission.received' => [
            'label' => 'Admission application received',
            'recipients' => 'applicant',
            'channels' => ['sms', 'email'],
            'vars' => ['name', 'application_no', 'class', 'branch', 'url'],
            'whatsapp_params' => ['name', 'application_no', 'class'],
            'templates' => [
                'bn' => [
                    'subject' => 'ভর্তির আবেদন গৃহীত — {application_no}',
                    'sms' => '{name}-এর ভর্তির আবেদন ({class}) গৃহীত হয়েছে। আবেদন নং {application_no}। অবস্থা দেখুন: {url}'.$sign,
                    'body' => "প্রিয় অভিভাবক,\n\n{name}-এর {class} শ্রেণিতে ভর্তির আবেদন গৃহীত হয়েছে। আবেদন নম্বর: {application_no}।\nআবেদনের অবস্থা দেখতে এই নম্বর ও মোবাইল নম্বর ব্যবহার করুন: {url}\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Admission application received — {application_no}',
                    'sms' => 'Admission application of {name} ({class}) received. Application no {application_no}. Track: {url}'.$sign,
                    'body' => "Dear guardian,\n\nThe admission application of {name} for class {class} has been received. Application number: {application_no}.\nUse this number and your mobile number to track it: {url}\n\n{branch}",
                ],
            ],
        ],

        'admission.confirmed' => [
            'label' => 'Admission confirmed',
            'recipients' => 'guardians',
            'channels' => ['in_app', 'sms', 'email'],
            'vars' => ['student_name', 'student_code', 'class', 'section', 'roll', 'branch'],
            'whatsapp_params' => ['student_name', 'class', 'student_code'],
            'templates' => [
                'bn' => [
                    'subject' => 'ভর্তি নিশ্চিত — {student_name}',
                    'sms' => '{student_name} {class} শ্রেণিতে ({section}) ভর্তি হয়েছে। আইডি {student_code}, রোল {roll}।'.$sign,
                    'body' => "অভিনন্দন! {student_name} {class} শ্রেণি, শাখা {section}-এ ভর্তি হয়েছে।\nশিক্ষার্থী আইডি: {student_code}\nরোল: {roll}\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Admission confirmed — {student_name}',
                    'sms' => '{student_name} is admitted to class {class} ({section}). ID {student_code}, roll {roll}.'.$sign,
                    'body' => "Congratulations! {student_name} is admitted to class {class}, section {section}.\nStudent ID: {student_code}\nRoll: {roll}\n\n{branch}",
                ],
            ],
        ],

        'fee.invoice' => [
            'label' => 'Fee invoice issued',
            'recipients' => 'guardians',
            'channels' => ['in_app', 'email'],
            'vars' => ['student_name', 'invoice_no', 'month', 'amount', 'due_date', 'branch', 'url'],
            'whatsapp_params' => ['student_name', 'month', 'amount', 'due_date'],
            'templates' => [
                'bn' => [
                    'subject' => '{month} মাসের ফি — {student_name}',
                    'sms' => '{student_name}-এর {month} মাসের ফি {amount} টাকা, শেষ তারিখ {due_date}। ইনভয়েস {invoice_no}।'.$sign,
                    'body' => "{student_name}-এর {month} মাসের ফি ইনভয়েস ({invoice_no}) তৈরি হয়েছে।\nপরিমাণ: {amount} টাকা\nপরিশোধের শেষ তারিখ: {due_date}\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Fees for {month} — {student_name}',
                    'sms' => 'Fees of {student_name} for {month}: Tk {amount}, due {due_date}. Invoice {invoice_no}.'.$sign,
                    'body' => "The fee invoice {invoice_no} of {student_name} for {month} has been issued.\nAmount: Tk {amount}\nDue date: {due_date}\n\n{branch}",
                ],
            ],
        ],

        'fee.due' => [
            'label' => 'Outstanding fee reminder',
            'recipients' => 'guardians',
            'channels' => ['in_app', 'sms'],
            'vars' => ['student_name', 'amount', 'invoices', 'oldest_due', 'branch', 'url'],
            'whatsapp_params' => ['student_name', 'amount', 'oldest_due'],
            'templates' => [
                'bn' => [
                    'subject' => 'বকেয়া ফি স্মরণ — {student_name}',
                    'sms' => '{student_name}-এর বকেয়া ফি {amount} টাকা ({invoices}টি ইনভয়েস, {oldest_due} থেকে)। অনুগ্রহ করে পরিশোধ করুন।'.$sign,
                    'body' => "{student_name}-এর মোট বকেয়া ফি {amount} টাকা ({invoices}টি ইনভয়েস; সবচেয়ে পুরনোটির শেষ তারিখ {oldest_due})।\nঅনুগ্রহ করে দ্রুত পরিশোধ করুন।\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Fee reminder — {student_name}',
                    'sms' => 'Outstanding fees of {student_name}: Tk {amount} ({invoices} invoice(s), since {oldest_due}). Please pay.'.$sign,
                    'body' => "The outstanding fees of {student_name} are Tk {amount} ({invoices} invoice(s); the oldest fell due on {oldest_due}).\nPlease pay at your earliest convenience.\n\n{branch}",
                ],
            ],
        ],

        'payment.received' => [
            'label' => 'Fee payment received',
            'recipients' => 'guardians',
            'channels' => ['in_app', 'sms', 'email'],
            'vars' => ['student_name', 'receipt_no', 'amount', 'date', 'balance', 'branch', 'url'],
            'whatsapp_params' => ['student_name', 'amount', 'receipt_no', 'balance'],
            'templates' => [
                'bn' => [
                    'subject' => 'ফি জমার রসিদ {receipt_no}',
                    'sms' => '{student_name}-এর ফি {amount} টাকা জমা হয়েছে ({date})। রসিদ {receipt_no}। বকেয়া {balance} টাকা।'.$sign,
                    'body' => "{student_name}-এর ফি বাবদ {amount} টাকা {date} তারিখে জমা হয়েছে।\nরসিদ নম্বর: {receipt_no}\nবর্তমান বকেয়া: {balance} টাকা\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Fee receipt {receipt_no}',
                    'sms' => 'Tk {amount} received for {student_name} on {date}. Receipt {receipt_no}. Due now Tk {balance}.'.$sign,
                    'body' => "Tk {amount} has been received for {student_name} on {date}.\nReceipt number: {receipt_no}\nOutstanding now: Tk {balance}\n\n{branch}",
                ],
            ],
        ],

        'attendance.absent' => [
            'label' => 'Consecutive absence alert',
            'recipients' => 'guardians',
            'channels' => ['in_app', 'sms'],
            'vars' => ['student_name', 'days', 'since', 'class', 'branch'],
            'whatsapp_params' => ['student_name', 'days', 'since'],
            'templates' => [
                'bn' => [
                    'subject' => 'অনুপস্থিতি — {student_name}',
                    'sms' => '{student_name} {since} থেকে টানা {days} দিন বিদ্যালয়ে অনুপস্থিত। অনুগ্রহ করে যোগাযোগ করুন।'.$sign,
                    'body' => "{student_name} ({class}) {since} থেকে টানা {days} দিন অনুপস্থিত রয়েছে।\nঅনুগ্রহ করে শ্রেণি শিক্ষকের সাথে যোগাযোগ করুন।\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Absence — {student_name}',
                    'sms' => '{student_name} has been absent for {days} consecutive days since {since}. Please contact the school.'.$sign,
                    'body' => "{student_name} ({class}) has been absent for {days} consecutive days since {since}.\nPlease contact the class teacher.\n\n{branch}",
                ],
            ],
        ],

        'result.published' => [
            'label' => 'Examination result published',
            'recipients' => 'students_and_guardians',
            'channels' => ['in_app', 'sms'],
            'vars' => ['student_name', 'exam', 'gpa', 'grade', 'status', 'position', 'branch', 'url'],
            'whatsapp_params' => ['student_name', 'exam', 'gpa', 'grade'],
            'templates' => [
                'bn' => [
                    'subject' => '{exam}-এর ফল প্রকাশিত',
                    'sms' => '{student_name}-এর {exam}-এর ফল: জিপিএ {gpa}, গ্রেড {grade} ({status})। মেধাক্রম {position}।'.$sign,
                    'body' => "{exam}-এর ফল প্রকাশিত হয়েছে।\n{student_name}: জিপিএ {gpa}, গ্রেড {grade} ({status}), মেধাক্রম {position}।\nবিস্তারিত পোর্টালে দেখুন।\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Results of {exam} published',
                    'sms' => '{exam} result of {student_name}: GPA {gpa}, grade {grade} ({status}). Position {position}.'.$sign,
                    'body' => "The results of {exam} are published.\n{student_name}: GPA {gpa}, grade {grade} ({status}), position {position}.\nSee the portal for the mark sheet.\n\n{branch}",
                ],
            ],
        ],

        'notice.published' => [
            'label' => 'Notice published',
            'recipients' => 'audience',
            'channels' => ['in_app'],
            'vars' => ['title', 'excerpt', 'category', 'branch', 'url'],
            'whatsapp_params' => ['title', 'excerpt'],
            'templates' => [
                'bn' => [
                    'subject' => 'নোটিশ: {title}',
                    'sms' => 'নোটিশ: {title}। {excerpt}'.$sign,
                    'body' => "{title}\n\n{excerpt}\n\nপূর্ণ নোটিশ: {url}\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Notice: {title}',
                    'sms' => 'Notice: {title}. {excerpt}'.$sign,
                    'body' => "{title}\n\n{excerpt}\n\nFull notice: {url}\n\n{branch}",
                ],
            ],
        ],

        'assignment.published' => [
            'label' => 'Homework / assignment given',
            'recipients' => 'students and guardians of the class',
            'channels' => ['in_app'],
            'vars' => ['student_name', 'title', 'kind', 'subject', 'class', 'due', 'branch', 'url'],
            'whatsapp_params' => ['student_name', 'title', 'due'],
            'templates' => [
                'bn' => [
                    'subject' => 'নতুন {kind}: {title}',
                    'sms' => '{student_name}: {subject} — {title} ({kind})। জমার শেষ সময়: {due}।'.$sign,
                    'body' => "{student_name}-এর জন্য নতুন {kind}।\n\nবিষয়: {subject}\nশিরোনাম: {title}\nজমার শেষ সময়: {due}\n\nবিস্তারিত: {url}\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'New {kind}: {title}',
                    'sms' => '{student_name}: {subject} — {title} ({kind}). Due {due}.'.$sign,
                    'body' => "A new {kind} for {student_name}.\n\nSubject: {subject}\nTitle: {title}\nDue: {due}\n\nDetails: {url}\n\n{branch}",
                ],
            ],
        ],

        'assignment.evaluated' => [
            'label' => 'Homework / assignment evaluated',
            'recipients' => 'the student and guardians',
            'channels' => ['in_app'],
            'vars' => ['student_name', 'title', 'result', 'feedback', 'branch', 'url'],
            'whatsapp_params' => ['student_name', 'title', 'result'],
            'templates' => [
                'bn' => [
                    'subject' => 'মূল্যায়ন: {title}',
                    'sms' => '{student_name}: "{title}" — {result}।'.$sign,
                    'body' => "{student_name}-এর \"{title}\" মূল্যায়ন করা হয়েছে।\n\nফল: {result}\nশিক্ষকের মন্তব্য: {feedback}\n\nবিস্তারিত: {url}\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Evaluated: {title}',
                    'sms' => '{student_name}: "{title}" — {result}.'.$sign,
                    'body' => "\"{title}\" of {student_name} has been evaluated.\n\nResult: {result}\nTeacher's feedback: {feedback}\n\nDetails: {url}\n\n{branch}",
                ],
            ],
        ],

        'online_class.scheduled' => [
            'label' => 'Live class scheduled',
            'recipients' => 'students and guardians of the class',
            'channels' => ['in_app'],
            'vars' => ['student_name', 'title', 'subject', 'class', 'when', 'place', 'reason', 'branch', 'url'],
            'whatsapp_params' => ['student_name', 'title', 'when'],
            'templates' => [
                'bn' => [
                    'subject' => 'নতুন ক্লাস: {title}',
                    'sms' => '{student_name}: ক্লাস — {title}, {when} ({place})।'.$sign,
                    'body' => "{student_name}-এর জন্য ক্লাস নির্ধারিত হয়েছে।\n\nবিষয়: {subject}\nশিরোনাম: {title}\nসময়: {when}\nস্থান / মাধ্যম: {place}\n\nবিস্তারিত: {url}\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'New class: {title}',
                    'sms' => '{student_name}: class — {title}, {when} ({place}).'.$sign,
                    'body' => "A class has been scheduled for {student_name}.\n\nSubject: {subject}\nTitle: {title}\nTime: {when}\nWhere: {place}\n\nDetails: {url}\n\n{branch}",
                ],
            ],
        ],

        'online_class.rescheduled' => [
            'label' => 'Live class rescheduled',
            'recipients' => 'students and guardians of the class',
            'channels' => ['in_app'],
            'vars' => ['student_name', 'title', 'subject', 'class', 'when', 'place', 'reason', 'branch', 'url'],
            'whatsapp_params' => ['student_name', 'title', 'when'],
            'templates' => [
                'bn' => [
                    'subject' => 'ক্লাসের সময় পরিবর্তন: {title}',
                    'sms' => '{student_name}: ক্লাস পরিবর্তন — {title}, {when} ({place})।'.$sign,
                    'body' => "{student_name}-এর ক্লাসের সময় বা স্থান পরিবর্তন হয়েছে।\n\nবিষয়: {subject}\nশিরোনাম: {title}\nনতুন সময়: {when}\nস্থান / মাধ্যম: {place}\n\nবিস্তারিত: {url}\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Class rescheduled: {title}',
                    'sms' => '{student_name}: class changed — {title}, {when} ({place}).'.$sign,
                    'body' => "The time or place of a class for {student_name} has changed.\n\nSubject: {subject}\nTitle: {title}\nNew time: {when}\nWhere: {place}\n\nDetails: {url}\n\n{branch}",
                ],
            ],
        ],

        'online_class.cancelled' => [
            'label' => 'Live class cancelled',
            'recipients' => 'students and guardians of the class',
            'channels' => ['in_app'],
            'vars' => ['student_name', 'title', 'subject', 'class', 'when', 'place', 'reason', 'branch', 'url'],
            'whatsapp_params' => ['student_name', 'title', 'when'],
            'templates' => [
                'bn' => [
                    'subject' => 'ক্লাস বাতিল: {title}',
                    'sms' => '{student_name}: ক্লাস বাতিল — {title}, {when}।'.$sign,
                    'body' => "{student_name}-এর ক্লাস বাতিল করা হয়েছে।\n\nবিষয়: {subject}\nশিরোনাম: {title}\nনির্ধারিত সময় ছিল: {when}\nকারণ: {reason}\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Class cancelled: {title}',
                    'sms' => '{student_name}: class cancelled — {title}, {when}.'.$sign,
                    'body' => "A class for {student_name} has been cancelled.\n\nSubject: {subject}\nTitle: {title}\nWas scheduled: {when}\nReason: {reason}\n\n{branch}",
                ],
            ],
        ],

        'quiz.published' => [
            'label' => 'Quiz available',
            'recipients' => 'students and guardians of the class',
            'channels' => ['in_app'],
            'vars' => ['student_name', 'title', 'subject', 'class', 'until', 'time_limit', 'branch', 'url'],
            'whatsapp_params' => ['student_name', 'title', 'until'],
            'templates' => [
                'bn' => [
                    'subject' => 'নতুন কুইজ: {title}',
                    'sms' => '{student_name}: কুইজ — {title} ({subject})। শেষ সময়: {until}।'.$sign,
                    'body' => "{student_name}-এর জন্য নতুন কুইজ।\n\nবিষয়: {subject}\nশিরোনাম: {title}\nসময়সীমা: {time_limit}\nশেষ সময়: {until}\n\nকুইজ দিন: {url}\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'New quiz: {title}',
                    'sms' => '{student_name}: quiz — {title} ({subject}). Closes {until}.'.$sign,
                    'body' => "A new quiz for {student_name}.\n\nSubject: {subject}\nTitle: {title}\nTime limit: {time_limit}\nCloses: {until}\n\nTake the quiz: {url}\n\n{branch}",
                ],
            ],
        ],

        'library.reservation_ready' => [
            'label' => 'Reserved library book is ready',
            'recipients' => 'the member (student + guardians, or the employee)',
            'channels' => ['in_app', 'sms'],
            'vars' => ['student_name', 'title', 'until', 'branch', 'url'],
            'whatsapp_params' => ['student_name', 'title', 'until'],
            'templates' => [
                'bn' => [
                    'subject' => 'সংরক্ষিত বই প্রস্তুত: {title}',
                    'sms' => '{student_name}: সংরক্ষিত বই "{title}" লাইব্রেরিতে প্রস্তুত। {until} পর্যন্ত রাখা হবে।'.$sign,
                    'body' => "আপনার সংরক্ষিত বই \"{title}\" এখন লাইব্রেরিতে নেওয়ার জন্য প্রস্তুত।\nএটি {until} তারিখ পর্যন্ত আপনার জন্য রাখা হবে।\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Reserved book ready: {title}',
                    'sms' => '{student_name}: your reserved book "{title}" is ready at the library until {until}.'.$sign,
                    'body' => "Your reserved book \"{title}\" is ready to collect from the library.\nIt will be held for you until {until}.\n\n{branch}",
                ],
            ],
        ],

        'library.overdue' => [
            'label' => 'Library book overdue',
            'recipients' => 'the member (student + guardians, or the employee)',
            'channels' => ['in_app', 'sms'],
            'vars' => ['student_name', 'title', 'due_date', 'days', 'fine', 'branch', 'url'],
            'whatsapp_params' => ['student_name', 'title', 'due_date'],
            'templates' => [
                'bn' => [
                    'subject' => 'লাইব্রেরির বই ফেরত দিন: {title}',
                    'sms' => '{student_name}: "{title}" ফেরতের তারিখ {due_date} পার হয়েছে ({days} দিন)। জরিমানা এখন {fine} টাকা।'.$sign,
                    'body' => "\"{title}\" বইটি ফেরত দেওয়ার তারিখ ছিল {due_date}; {days} দিন পার হয়েছে।\nএ পর্যন্ত জরিমানা: {fine} টাকা। অনুগ্রহ করে দ্রুত ফেরত দিন।\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Please return the library book: {title}',
                    'sms' => '{student_name}: "{title}" was due {due_date} ({days} days late). Fine so far Tk {fine}.'.$sign,
                    'body' => "The book \"{title}\" was due on {due_date} and is {days} day(s) late.\nFine so far: Tk {fine}. Please return it as soon as possible.\n\n{branch}",
                ],
            ],
        ],

        'assets.maintenance_due' => [
            'label' => 'Asset maintenance or calibration due',
            'recipients' => 'the employee responsible for the task',
            'channels' => ['in_app'],
            'vars' => ['name', 'asset', 'task', 'due_date', 'when', 'branch', 'url'],
            'whatsapp_params' => ['name', 'asset', 'task'],
            'templates' => [
                'bn' => [
                    'subject' => 'রক্ষণাবেক্ষণের সময়: {asset}',
                    'sms' => '{asset}: "{task}" — {when} (নির্ধারিত তারিখ {due_date})।'.$sign,
                    'body' => "{name}, সম্পদ {asset}-এর \"{task}\" কাজটি {when}।\nনির্ধারিত তারিখ: {due_date}\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Maintenance due: {asset}',
                    'sms' => '{asset}: "{task}" — {when} (due {due_date}).'.$sign,
                    'body' => "{name}, the task \"{task}\" for {asset} is {when}.\nDue date: {due_date}\n\n{branch}",
                ],
            ],
        ],

        'reports.export_ready' => [
            'label' => 'Report export is ready',
            'recipients' => 'the user who asked for the export',
            'channels' => ['in_app'],
            'vars' => ['name', 'report', 'format', 'rows', 'branch', 'url'],
            'whatsapp_params' => ['name', 'report'],
            'templates' => [
                'bn' => [
                    'subject' => 'রিপোর্ট প্রস্তুত: {report}',
                    'sms' => '{report} ({format}) প্রস্তুত — {rows} সারি। ডাউনলোড: {url}'.$sign,
                    'body' => "{name}, আপনার \"{report}\" রিপোর্ট ({format}, {rows} সারি) প্রস্তুত হয়েছে।\nডাউনলোড করুন (কয়েকদিন পর ফাইল মুছে যাবে): {url}\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Report ready: {report}',
                    'sms' => '{report} ({format}) is ready — {rows} rows. Download: {url}'.$sign,
                    'body' => "{name}, your \"{report}\" report ({format}, {rows} rows) is ready.\nDownload it here (the file is deleted after a few days): {url}\n\n{branch}",
                ],
            ],
        ],

        'reports.export_failed' => [
            'label' => 'Report export failed',
            'recipients' => 'the user who asked for the export',
            'channels' => ['in_app'],
            'vars' => ['name', 'report', 'format', 'error', 'branch', 'url'],
            'whatsapp_params' => ['name', 'report'],
            'templates' => [
                'bn' => [
                    'subject' => 'রিপোর্ট তৈরি হয়নি: {report}',
                    'sms' => '{report} ({format}) তৈরি করা যায়নি: {error}'.$sign,
                    'body' => "{name}, আপনার \"{report}\" রিপোর্ট ({format}) তৈরি করা যায়নি।\nকারণ: {error}\nফিল্টার ছোট করে আবার চেষ্টা করুন: {url}\n\n{branch}",
                ],
                'en' => [
                    'subject' => 'Report failed: {report}',
                    'sms' => '{report} ({format}) could not be prepared: {error}'.$sign,
                    'body' => "{name}, your \"{report}\" report ({format}) could not be prepared.\nReason: {error}\nNarrow the filters and try again: {url}\n\n{branch}",
                ],
            ],
        ],

        'message.general' => [
            'label' => 'Message from the school',
            'recipients' => 'audience',
            'channels' => ['in_app', 'sms', 'email'],
            'vars' => ['body', 'name', 'branch'],
            'whatsapp_params' => ['body'],
            'templates' => [
                'bn' => ['subject' => '{branch} থেকে বার্তা', 'sms' => '{body}'.$sign, 'body' => "{body}\n\n{branch}"],
                'en' => ['subject' => 'Message from {branch}', 'sms' => '{body}'.$sign, 'body' => "{body}\n\n{branch}"],
            ],
        ],
    ],
];
