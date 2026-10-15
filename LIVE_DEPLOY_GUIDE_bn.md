# scm.edushare.online-এ লাইভ করার গাইড

## কেন "Page Not Found" আসছিল
এই সফটওয়্যার মাল্টি-টেন্যান্ট — ব্রাউজারের ডোমেইন দেখে ঠিক করে কোন পেইজ দেখাবে।
পুরনো `.env`-এ শুধু `localhost` ছিল, তাই `scm.edushare.online` এলে অ্যাপ বলত
"This branch address is not registered" → 404। এখন `.env.production`-এ লাইভ ডোমেইন বসানো আছে।

## ধাপ ১ — ডাটাবেস (cPanel)
1. MySQL Databases → নতুন ডাটাবেস + ইউজার বানান, ইউজারকে ALL PRIVILEGES দিন।
2. phpMyAdmin → ঐ ডাটাবেস → Import → `school_saas.sql` (বা `school_saas.zip`)।

## ধাপ ২ — ফাইল আপলোড
পুরো `school` ফোল্ডার জিপ করে আপলোড করুন — `vendor` ফোল্ডারসহ। বাদ দিন:
`node_modules`, `Claude outputs`, `school_saas.sql/.zip`, `ziJIprxf`, `.phpunit.result.cache`,
এবং `public/storage` (এটা Windows শর্টকাট, সার্ভারে কাজ করে না — ধাপ ৪-এ নতুন করে হবে)।

**ভালো উপায় (Document Root = public):**
cPanel → Domains → `scm.edushare.online` → Document Root দিন
`/home/<আপনার-ইউজার>/school/public` (ফাইলগুলো `/home/<ইউজার>/school/`-এ রাখুন)।

**যদি Document Root বদলানো না যায়:** ফাইলগুলো ঐ সাবডোমেইনের ফোল্ডারে রাখলেই চলবে —
প্রজেক্টের মূল ফোল্ডারের নতুন `.htaccess` সব রিকোয়েস্ট `public/`-এ পাঠিয়ে দেবে।

## ধাপ ৩ — .env
1. সার্ভারে পুরনো `.env` মুছে `.env.production`-এর নাম বদলে `.env` করুন।
2. এই তিনটি বদলান: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (cPanel-এর পূর্ণ নাম, যেমন `edushare_school`)।
3. `DEPLOY_SETUP_TOKEN=` এর পরে একটা লম্বা র‍্যান্ডম লেখা দিন (যেমন `xK29fhQ7pLm4Zr81vT`)।

## ধাপ ৪ — সেটআপ চালানো
- **Terminal থাকলে:** প্রজেক্ট ফোল্ডারে
  `php artisan optimize:clear && php artisan storage:link && php artisan config:cache && php artisan route:cache && php artisan view:cache`
- **Terminal না থাকলে:** ব্রাউজারে খুলুন
  `https://scm.edushare.online/deploy-setup.php?token=<আপনার-টোকেন>`
  সব কমান্ড চলবে এবং "Database connection: OK" দেখাবে।
  **শেষে `public/deploy-setup.php` সার্ভার থেকে মুছে দিন।**

## ধাপ ৫ — পরীক্ষা
- `https://scm.edushare.online` → পাবলিক ওয়েবসাইট
- `https://scm.edushare.online/super-admin` → সুপার অ্যাডমিন

## শাখার (branch) লিংক
শাখাগুলো চলে সাব-ডোমেইনে: `bafd.scm.edushare.online`, `branch7.scm.edushare.online` ইত্যাদি।
এর জন্য cPanel → Domains-এ **wildcard সাবডোমেইন** `*.scm.edushare.online` বানান
(Document Root একই `public` ফোল্ডার) এবং DNS-এ `*.scm` এর A রেকর্ড দিন। SSL-ও wildcard লাগবে।
হোস্টিং wildcard সাপোর্ট না করলে, প্রতিটি শাখার সাবডোমেইন আলাদা করে একই ফোল্ডারে বানাতে হবে।

## এখনো সমস্যা হলে
- PHP ভার্সন ৮.২ বা তার বেশি হতে হবে (cPanel → Select PHP Version / MultiPHP)।
- `storage` ও `bootstrap/cache` ফোল্ডারের permission 775।
- ভুল দেখতে: সাময়িকভাবে `.env`-এ `APP_DEBUG=true` দিয়ে পেইজ রিলোড করুন, পরে আবার `false` করুন।
- `.env` বদলালে আবার ধাপ ৪ চালান (কারণ config ক্যাশ হয়ে থাকে)।
