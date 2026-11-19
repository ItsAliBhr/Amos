# راهنمای نصب AMOS روی هاست اشتراکی cPanel

این راهنما برای فایل `AMOS-cPanel-Deployment.zip` نوشته شده است. در Production هیچ‌کدام از Node.js، npm، Docker، Redis، Worker دائمی یا `php artisan serve` لازم نیست.

## ۱. پیش‌نیازها

| مورد | مقدار |
|---|---|
| PHP | **8.3 یا بالاتر** (Laravel 13 به PHP 8.3+ نیاز دارد) |
| افزونه‌های PHP | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `dom`, `ctype`, `fileinfo`, `filter`, `session` (اختیاری ولی توصیه‌شده: `bcmath` یا `gmp` برای سرعت محاسبات اعشاری) |
| دیتابیس | MySQL 8.0+ یا MariaDB 10.6+ |
| وب‌سرور | Apache با `mod_rewrite` (حالت پیش‌فرض cPanel) یا LiteSpeed |
| SSL | فعال (AutoSSL یا Let's Encrypt) |

> نسخه‌های واقعاً تست‌شده در این تحویل: PHP 8.3.6 و MariaDB 10.11 روی Apache 2.4 با `.htaccess`. فایل CI برای MySQL 8.0 هم تعریف شده است. ادعای سازگاری با همه هاست‌ها نمی‌شود؛ پیش‌نیازهای بالا را بررسی کنید.

نسخه PHP را در cPanel از بخش **MultiPHP Manager** یا **Select PHP Version** روی 8.3 (یا بالاتر) تنظیم کنید و افزونه‌های بالا را در **Select PHP Version › Extensions** فعال کنید.

## ۲. ساخت دیتابیس

1. cPanel › **MySQL® Databases**
2. یک دیتابیس بسازید (مثلاً `cpuser_amos`) — Collation پیش‌فرض `utf8mb4` مناسب است.
3. یک کاربر بسازید با رمز قوی (مثلاً `cpuser_amos`).
4. در بخش **Add User To Database** کاربر را با **ALL PRIVILEGES** به دیتابیس اضافه کنید.

## ۳. آپلود و استخراج فایل‌ها (ساختار امن پیشنهادی)

ZIP شامل دو پوشه است:

```text
amos_app/      کل برنامه Laravel (سورس، vendor، storage) — نباید از وب قابل دسترسی باشد
public_html/   فقط index.php، .htaccess و فایل‌های عمومی (CSS/JS/فونت)
```

1. cPanel › **File Manager** › پوشه Home حساب (مثلاً `/home/cpuser/`)، نه داخل `public_html`.
2. ZIP را در همین مسیر Upload و سپس **Extract** کنید. نتیجه:

```text
/home/cpuser/amos_app/
/home/cpuser/public_html/index.php   (و .htaccess، static/، vendor/، favicon.ico)
```

> اگر `public_html` از قبل فایل‌هایی دارد (مثلاً `index.html` پیش‌فرض)، آن‌ها را حذف یا جابه‌جا کنید.

### روش جایگزین (بهتر، در صورت امکان): تغییر Document Root
اگر دامنه/ساب‌دامنه را در cPanel › **Domains** می‌سازید و امکان تعیین Document Root دارید، آن را روی `amos_app/public` قرار دهید و پوشه `public_html` داخل ZIP را نادیده بگیرید.

### اگر برنامه را جای دیگری گذاشتید
در `public_html/index.php` مقدار `AMOS_APP_PATH` را به مسیر پوشه برنامه تغییر دهید.

## ۴. مجوز پوشه‌ها

در File Manager (Permissions) یا SSH:

```bash
chmod -R 775 ~/amos_app/storage ~/amos_app/bootstrap/cache
chmod 755 ~/amos_app
```

پوشه `amos_app` باید برای PHP قابل نوشتن باشد تا Installer بتواند فایل `.env` را بسازد (در هاست‌های cPanel معمولاً PHP با کاربر خود حساب اجرا می‌شود و نیاز به تغییری نیست).

## ۵. نصب

### روش الف: Installer تحت وب (بدون SSH)
1. آدرس دامنه را باز کنید؛ به‌طور خودکار به `/install` هدایت می‌شوید (فایل `.env` و `APP_KEY` امن در اولین اجرا خودکار ساخته می‌شود).
2. بخش «بررسی پیش‌نیازها» باید همه موارد را سبز نشان دهد.
3. اطلاعات دیتابیس، آدرس سامانه (`https://...`)، نام صندوق و نام کاربری/رمز مدیر اولیه را وارد کنید.
   رمز مدیر: حداقل ۱۰ کاراکتر شامل حرف بزرگ، کوچک و عدد.
4. «نصب AMOS» را بزنید. جداول ساخته می‌شوند، داده پایه (انواع واریزی، دسته‌بندی‌ها، تنظیمات، سبد Pool) ثبت و مدیر ساخته می‌شود.
5. Installer با فایل `amos_app/storage/app/installed.lock` قفل می‌شود و دیگر در دسترس نیست. نصب مجدد هرگز دیتابیس دارای داده را بازنویسی نمی‌کند.

### روش ب: با SSH / Terminal
```bash
cd ~/amos_app
cp .env.example .env
php artisan key:generate
# فایل .env را ویرایش کنید: APP_URL و DB_DATABASE / DB_USERNAME / DB_PASSWORD
php artisan amos:install --admin-name="نام مدیر" --admin-username=admin --fund-name="صندوق ما"
```
(رمز مدیر به‌صورت مخفی پرسیده می‌شود.) اگر Composer روی هاست دارید و از سورس GitHub نصب می‌کنید، قبل از آن `composer install --no-dev --optimize-autoloader` را اجرا کنید؛ ZIP آماده نیازی به Composer ندارد چون `vendor` داخل آن است.

## ۶. HTTPS
1. cPanel › **SSL/TLS Status** › AutoSSL را برای دامنه اجرا کنید.
2. در `.env` مقدارها را بررسی کنید: `APP_URL=https://...`، `FORCE_HTTPS=true`، `SESSION_SECURE_COOKIE=true` (Installer در صورت وارد کردن آدرس https این‌ها را خودکار تنظیم می‌کند).
3. اگر هنوز SSL ندارید، موقتاً `FORCE_HTTPS=false` و `SESSION_SECURE_COOKIE=false` بگذارید؛ در غیر این صورت ورود کار نمی‌کند. **در Production حتماً HTTPS را فعال کنید.**

## ۷. بعد از نصب
1. با حساب مدیر وارد شوید.
2. **اطلاعات پایه و تنظیمات**: نام صندوق، واحد نمایش (تومان/ریال)، تعهد ماهانه پیش‌فرض، سقف ماهانه (پیش‌فرض ۱۰ میلیون تومان)، سیاست برداشت.
3. **حساب‌های نقدی**: حداقل یک حساب (مثلاً حساب بانکی مشترک) بسازید. موجودی افتتاحیه به Pool تخصیص می‌یابد؛ اگر پول متعلق به اعضاست، آن را صفر بگذارید و به‌صورت واریزی اعضا ثبت کنید.
4. **مدیریت کاربران**: اعضا را بسازید. رمز موقت فقط یک بار نمایش داده می‌شود و تغییر آن در اولین ورود اجباری است.
5. **دارایی‌ها**: دارایی‌ها (طلا، ارز، سهام، ...) را تعریف کنید.

## ۸. بررسی امنیت پس از نصب
این آدرس‌ها باید **قابل دانلود نباشند** (404 یا 403):
- `https://your-domain/.env`
- `https://your-domain/../amos_app/.env` (در ساختار پیشنهادی اصلاً از وب در دسترس نیست)
- `https://your-domain/install` (پس از نصب: 404)

## ۹. کارهای زمان‌بندی‌شده (اختیاری)
هیچ Cron اجباری نیست. برای ثبت روزانه تاریخچه NAV حتی در روزهای بدون تراکنش، می‌توانید در cPanel › **Cron Jobs** این دستور را روزانه اجرا کنید:
```bash
/usr/local/bin/php /home/cpuser/amos_app/artisan amos:verify-ledger --snapshot > /dev/null 2>&1
```
(مسیر PHP ممکن است در هاست شما متفاوت باشد، مثلاً `/opt/cpanel/ea-php83/root/usr/bin/php`.)

## ۱۰. عیب‌یابی

| مشکل | راه‌حل |
|---|---|
| خطا هنگام نصب | نصب‌کننده علت را بالای فرم نشان می‌دهد. پس از رفع (مثلاً دادن ALL PRIVILEGES به کاربر دیتابیس) دوباره «نصب» را بزنید؛ جداول نیمه‌کاره نصب ناموفق قبلی خودکار پاک می‌شوند. |
| صفحه سفید یا خطای 500 | `amos_app/storage/logs/` را بررسی کنید؛ مجوز `storage` و `bootstrap/cache` را 775 کنید. |
| «نسخه PHP پشتیبانی نمی‌شود» | نسخه PHP دامنه را در MultiPHP Manager روی 8.3+ بگذارید. |
| «پوشه برنامه AMOS پیدا نشد» | `amos_app` باید کنار `public_html` باشد یا `AMOS_APP_PATH` را در `public_html/index.php` اصلاح کنید. |
| خطای 419 هنگام ورود | HTTPS فعال نیست ولی `SESSION_SECURE_COOKIE=true` است؛ یا کوکی‌ها مسدودند. |
| خطای اتصال دیتابیس | نام دیتابیس/کاربر در cPanel پیشوند حساب دارد (`cpuser_`)؛ کاربر باید به دیتابیس اضافه شده باشد. |
| CSS/JS بارگذاری نمی‌شود | پوشه‌های `static/` و `vendor/` باید داخل `public_html` باشند. |
| رمز مدیر فراموش شده | با SSH: `php artisan amos:create-admin admin` (رمز جدید پرسیده می‌شود). |
| فعال‌سازی مجدد Installer | فقط برای نصب روی دیتابیس **خالی جدید**: فایل `storage/app/installed.lock` را حذف کنید. Installer دیتابیس دارای داده را بازنویسی نمی‌کند. |

## ۱۱. به‌روزرسانی نسخه
1. از دیتابیس و پوشه `amos_app/storage/app` پشتیبان بگیرید (راهنمای پشتیبان‌گیری).
2. فایل‌های نسخه جدید را جایگزین کنید **به‌جز** `amos_app/.env` و `amos_app/storage/`.
3. Migration نسخه جدید: با SSH `php artisan migrate --force`.
