# راهنمای پشتیبان‌گیری و بازیابی

اطلاعات مالی AMOS در **دیتابیس** و فایل‌های رسید/مستندات در **`amos_app/storage/app/private`** نگهداری می‌شوند. هر دو باید پشتیبان‌گیری شوند. کلید رمزنگاری `APP_KEY` در فایل `amos_app/.env` نیز حیاتی است: شماره حساب‌های بانکی با آن رمزنگاری شده‌اند و بدون آن قابل بازیابی نیستند.

## پشتیبان‌گیری دوره‌ای (پیشنهاد: روزانه دیتابیس، هفتگی فایل‌ها)

### روش ۱: cPanel
1. cPanel › **Backup** › Partial Backups › **Download a MySQL Database Backup** (دیتابیس AMOS).
2. cPanel › **File Manager** › پوشه `amos_app/storage/app/private` را Compress و دانلود کنید.
3. یک نسخه از `amos_app/.env` را در محل امن (خارج از هاست، رمزنگاری‌شده) نگه دارید.

### روش ۲: Cron Job خودکار
در cPanel › **Cron Jobs** (مسیرها را اصلاح کنید؛ رمز را در فایل `~/.my.cnf` با مجوز 600 نگه دارید، نه در خود دستور):

```bash
mysqldump --single-transaction --routines cpuser_amos | gzip > ~/amos_backups/db-$(date +\%F).sql.gz
tar -czf ~/amos_backups/files-$(date +\%F).tgz -C ~/amos_app/storage/app private
find ~/amos_backups -type f -mtime +30 -delete
```

نمونه `~/.my.cnf`:
```ini
[client]
user=cpuser_amos
password=YOUR_DB_PASSWORD
```

پشتیبان‌ها را به‌طور منظم به خارج از هاست منتقل کنید.

## بازیابی
1. دیتابیس خالی بسازید و فایل SQL را از cPanel › **phpMyAdmin › Import** بارگذاری کنید.
2. پوشه `private` را به `amos_app/storage/app/` برگردانید.
3. فایل `.env` را با همان `APP_KEY` قبلی برگردانید و اطلاعات دیتابیس جدید را وارد کنید.
4. فایل `amos_app/storage/app/installed.lock` باید وجود داشته باشد (در غیر این صورت یک فایل خالی با همین نام بسازید).
5. سلامت دفتر کل را بررسی کنید: منوی **دفتر کل** (باید پیام «همه اسناد متوازن هستند» نمایش داده شود) یا با SSH: `php artisan amos:verify-ledger`.

## نکات
- هرگز فایل پشتیبان را داخل `public_html` قرار ندهید.
- پشتیبان‌ها حاوی اطلاعات مالی اعضا هستند؛ آن‌ها را رمزنگاری و با دسترسی محدود نگه دارید.
- مانده‌ها همیشه از اسناد تأییدشده دفتر کل قابل بازسازی هستند؛ هیچ مانده‌ای جداگانه ذخیره نمی‌شود که بتواند ناسازگار شود (به‌جز تاریخچه NAV برای نمودار).
