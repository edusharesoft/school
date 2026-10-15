<?php

/*
|--------------------------------------------------------------------------
| Public website defaults (central domain)
|--------------------------------------------------------------------------
| Defaults for the home page blocks. Everything here can be changed per institution
| from Super Admin → Website → Settings; the saved values live in
| `institutions.settings->website` and win section by section.
|
| Image values are storage paths (public disk, e.g. "website/hero/x.jpg"), paths relative to
| /public (e.g. "images/website/hero.jpg") or absolute URLs. A missing image renders a clean
| placeholder instead of a broken picture.
*/

return [
    'hero' => [
        'image' => 'images/website/hero.jpg',
        'features' => [
            ['icon' => 'fa-pencil', 'title' => 'Special Education', 'text' => 'Individual attention, structured teaching and a strong academic foundation for every learner.', 'tone' => 'teal'],
            ['icon' => 'fa-book-open', 'title' => 'Full Day Session', 'text' => 'A balanced school day with classes, co-curricular activities and guided study time.', 'tone' => 'orange'],
            ['icon' => 'fa-user-graduate', 'title' => 'Qualified Teachers', 'text' => 'Experienced, trained and dedicated teachers who guide every student to succeed.', 'tone' => 'teal'],
        ],
    ],

    // Affiliation / partner logos strip (hidden when empty).
    'partners' => [],

    'social' => ['facebook' => null, 'youtube' => null, 'instagram' => null, 'linkedin' => null],

    'footer' => [
        'text' => null,     // short description under the footer logo
        'text_bn' => null,
    ],

    /*
    | Branch websites (every branch has its own public site on its own address). Everything can be changed by the
    | branch administrator under Website → Site settings; saved values live in branches.settings->website and win
    | section by section. A branch without its own hero picture, partner logos, social links or footer text shows
    | the institution's.
    */
    'branch' => [
        'hero' => [
            'image' => null,
            'features' => [
                ['icon' => 'fa-pencil', 'title' => 'Special Education', 'text' => 'Individual attention, structured teaching and a strong academic foundation for every learner.', 'tone' => 'teal'],
                ['icon' => 'fa-book-open', 'title' => 'Full Day Session', 'text' => 'A balanced school day with classes, co-curricular activities and guided study time.', 'tone' => 'orange'],
                ['icon' => 'fa-user-graduate', 'title' => 'Qualified Teachers', 'text' => 'Experienced, trained and dedicated teachers who guide every student to succeed.', 'tone' => 'teal'],
            ],
        ],
        // "Discover our School" block: two pictures, the years badge, three tick points and a button.
        'about' => [
            'title' => null, 'subtitle' => null, 'text' => null,
            'image_main' => null, 'image_second' => null,
            'years' => null, 'years_label' => null,
            'points' => [],
            'button_text' => null, 'button_url' => null,
        ],
        // Each list item: name, designation, photo (public-disk path) and — for messages — text; people also have note.
        'messages' => ['items' => []],
        'team' => ['items' => []],
        'teachers' => ['items' => []],
        'partners' => [],
        'social' => ['facebook' => null, 'youtube' => null, 'instagram' => null, 'linkedin' => null],
        'footer' => ['text' => null, 'text_bn' => null],
    ],

    // Icons offered for the three feature boxes (Font Awesome 6 solid).
    'feature_icons' => [
        'fa-pencil' => 'Pencil', 'fa-book-open' => 'Open book', 'fa-user-graduate' => 'Graduate', 'fa-chalkboard-user' => 'Teacher',
        'fa-flask' => 'Science lab', 'fa-computer' => 'Computer', 'fa-futbol' => 'Sports', 'fa-bus' => 'Transport',
        'fa-award' => 'Award', 'fa-shield-halved' => 'Safety', 'fa-heart' => 'Care', 'fa-star' => 'Star',
    ],

    // Pages created by `php artisan db:seed --class=WebsiteDefaultsSeeder` (idempotent).
    'default_pages' => [
        ['slug' => 'about-us', 'title' => 'About Us', 'title_bn' => 'আমাদের সম্পর্কে', 'placement' => 'menu', 'sort_order' => 10],
        ['slug' => 'academic', 'title' => 'Academic', 'title_bn' => 'একাডেমিক', 'placement' => 'menu', 'sort_order' => 20],
        ['slug' => 'regulations', 'title' => 'Regulations', 'title_bn' => 'নিয়মাবলি', 'placement' => 'menu', 'sort_order' => 30],
        ['slug' => 'contact', 'title' => 'Contact', 'title_bn' => 'যোগাযোগ', 'placement' => 'menu', 'sort_order' => 50],
        ['slug' => 'faq', 'title' => 'FAQ', 'title_bn' => 'সচরাচর জিজ্ঞাসা', 'placement' => 'footer_quick', 'sort_order' => 10],
        ['slug' => 'testimonials', 'title' => 'Testimonials', 'title_bn' => 'মতামত', 'placement' => 'footer_quick', 'sort_order' => 20],
        ['slug' => 'privacy-policy', 'title' => 'Privacy Policy', 'title_bn' => 'গোপনীয়তা নীতি', 'placement' => 'footer_quick', 'sort_order' => 30],
        ['slug' => 'terms-of-service', 'title' => 'Terms of Service', 'title_bn' => 'শর্তাবলি', 'placement' => 'footer_quick', 'sort_order' => 40],
        ['slug' => 'academic-department', 'title' => 'Academic Department', 'title_bn' => 'একাডেমিক বিভাগ', 'placement' => 'footer_campus', 'sort_order' => 10],
        ['slug' => 'health-care', 'title' => 'Health Care', 'title_bn' => 'স্বাস্থ্যসেবা', 'placement' => 'footer_campus', 'sort_order' => 20],
        ['slug' => 'our-facilities', 'title' => 'Our Facilities', 'title_bn' => 'আমাদের সুবিধা', 'placement' => 'footer_campus', 'sort_order' => 30],
        ['slug' => 'student-activities', 'title' => 'Student Activities', 'title_bn' => 'শিক্ষার্থী কার্যক্রম', 'placement' => 'footer_campus', 'sort_order' => 40],
        ['slug' => 'scholarships', 'title' => 'Scholarships', 'title_bn' => 'বৃত্তি', 'placement' => 'footer_campus', 'sort_order' => 50],
    ],
];
