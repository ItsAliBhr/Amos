# AMOS — سامانه مدیریت صندوق دوستانه سرمایه‌گذاری

AMOS یک سامانه خصوصی، فارسی و راست‌به‌چپ برای مدیریت صندوق سرمایه‌گذاری دوستانه است: واریزی اعضا، سرمایه مشترک اهدایی (Pool)، دارایی‌ها، خرید و فروش، ارزش‌گذاری روز، NAV و واحدهای سرمایه‌گذاری، سود و زیان، ایده‌های سرمایه‌گذاری، برداشت و تأیید عملیات توسط مدیر. نصب روی هاست اشتراکی cPanel بدون نیاز به Node.js، Docker، Redis یا Worker انجام می‌شود.

## امکانات
- **ورود امن** (صفحه اول دامنه)، بدون ثبت‌نام عمومی؛ ایجاد کاربر فقط توسط مدیر؛ تغییر رمز اجباری در اولین ورود؛ محدودیت تلاش ورود.
- **نقش‌ها:** مدیر (Admin) و عضو (Member) با کنترل دسترسی در Backend برای هر مسیر و رکورد. مدیر هم‌زمان سبد اختصاصی خود را دارد.
- **واریزی‌ها:** ثابت ماهانه، اضافه، اهدایی (Pool) و سند اصلاحی؛ بارگذاری رسید؛ پیش‌نویس/در انتظار/تأیید/رد/برگشت؛ سقف ماهانه با تأیید ویژه؛ تعهد ماهانه، معافیت و گزارش معوقات؛ خروجی CSV سازگار با Excel.
- **دفتر کل دوطرفه** به‌عنوان منبع حقیقت، اسناد غیرقابل ویرایش، اصلاح با سند برگشتی، کلید یکتایی ضد ثبت تکراری.
- **مالکیت با واحد سرمایه‌گذاری:** سبد اختصاصی هر عضو و سبد مستقل Pool در یک پرتفوی تجمیعی؛ `NAV per Unit` و تخصیص خودکار سود/زیان.
- **سرمایه‌گذاری:** خرید، فروش (میانگین موزون)، هزینه، درآمد، انتقال وجه؛ ارزش‌گذاری با حفظ تاریخچه و هشدار ارزش‌گذاری قدیمی.
- **ایده‌ها:** ثبت، نظر موافق/مخالف/ممتنع (یک نظر جاری + تاریخچه)، تصمیم مدیر با دلیل، اتصال به عملیات اجرا.
- **برداشت:** درخواست، تأیید، در انتظار نقدینگی، پرداخت با ابطال واحد؛ برداشت Pool فقط توسط مدیر با دلیل.
- **داشبورد و گزارش‌ها:** داشبورد مدیر (کارت‌ها، نمودارها، صف‌ها)، داشبورد عضو، گزارش کل، اعضا، Pool، دارایی‌ها و تعهد ماهانه.
- **اعلان داخلی، Audit Log فقط‌خواندنی، مستندات داخل برنامه.**
- تاریخ شمسی در UI و میلادی در دیتابیس؛ مبالغ `DECIMAL` به ریال، نمایش تومان/ریال.

## فناوری
PHP 8.3+ · Laravel 13 · MySQL 8 / MariaDB 10.6+ · Blade + Bootstrap 5.3 RTL · Chart.js 4 · فونت وزیرمتن · PHPUnit 12
همه فایل‌های Frontend از پیش آماده و داخل `public/vendor` و `public/static` قرار دارند.

## نصب روی cPanel
راهنمای کامل گام‌به‌گام: [docs/INSTALL-cPanel.md](docs/INSTALL-cPanel.md)

خلاصه:
1. در cPanel یک دیتابیس MySQL و کاربر با ALL PRIVILEGES بسازید.
2. `AMOS-cPanel-Deployment.zip` را در پوشه Home حساب (کنار `public_html`) آپلود و Extract کنید.
3. دامنه را باز کنید ← نصب‌کننده امن (`/install`) ← اطلاعات دیتابیس و مدیر اولیه.
4. نصب‌کننده قفل می‌شود و دامنه صفحه ورود AMOS را نمایش می‌دهد.
5. HTTPS را فعال کنید (AutoSSL).

## توسعه محلی
```bash
composer install
cp .env.example .env && php artisan key:generate
# .env: APP_ENV=local, APP_DEBUG=true, FORCE_HTTPS=false, SESSION_SECURE_COOKIE=false, DB_* , AMOS_INSTALLED=true
php artisan migrate --seed
php artisan amos:create-admin admin
php artisan serve        # فقط برای توسعه
```
داده نمونه **فقط در محیط توسعه**: `php artisan db:seed --class=DemoDataSeeder` (در Production اجرا نمی‌شود).

به‌روزرسانی فایل‌های Frontend (فقط توسعه؛ خروجی commit می‌شود): `npm install && npm run build`.

## تست
تست‌ها روی MySQL/MariaDB واقعی اجرا می‌شوند تا رفتار `DECIMAL` دقیقاً مانند Production باشد. دیتابیس‌های `amos_test` و `amos_install_test` (کاربر `amos` / رمز `amos_dev_pw` در `phpunit.xml`) لازم است:
```bash
php artisan test
```
پوشش: محاسبات مالی (واحد عضو/Pool، Pending/Rejected بی‌اثر، سقف و استثنای Donation، خرید/فروش/هزینه، میانگین موزون، تاریخچه ارزش‌گذاری، تخصیص سود، برداشت و نقدینگی، برگشت، idempotency، بازسازی از دفتر کل)، امنیت (دسترسی‌ها، IDOR، CSRF، آپلود، کاربر غیرفعال، Rate limit، Audit)، رندر همه صفحات و نصب‌کننده.

## ساخت ZIP استقرار
```bash
deployment/build-zip.sh            # خروجی: dist/AMOS-cPanel-Deployment.zip
```
ZIP شامل `amos_app/` (سورس + `vendor` بدون پکیج‌های توسعه) و `public_html/` است و فاقد `.env`، داده عملیاتی، رمز و `node_modules` است. Workflow گیت‌هاب (`.github/workflows/ci.yml`) تست‌ها را روی MySQL 8 اجرا و ZIP را به‌عنوان Artifact می‌سازد.

## ساختار پروژه
```text
app/Domain/{Accounting,Contributions,Investments,Ideas,Members,Valuations,Withdrawals}   منطق مالی و کسب‌وکار
app/Http/{Controllers,Middleware}   کنترلرهای نازک + میان‌افزارهای امنیتی
app/Models  app/Policies  app/Services  app/Support (Money, Jalali)
database/{migrations,seeders}   resources/views   routes/web.php
tests/{Feature,Unit}   docs/   deployment/
```

## مستندات
- [راهنمای نصب cPanel](docs/INSTALL-cPanel.md)
- [مدل حسابداری، NAV و واحدها](docs/ACCOUNTING.md)
- [تصمیم‌ها، فرض‌ها و TODO](docs/DECISIONS.md)
- [پشتیبان‌گیری و بازیابی](docs/BACKUP.md)
- [نکات امنیتی](docs/SECURITY.md)

> این سامانه ابزار مدیریت داخلی است و جایگزین مشاوره سرمایه‌گذاری، حسابداری رسمی یا اسناد حقوقی مالکیت نیست.
