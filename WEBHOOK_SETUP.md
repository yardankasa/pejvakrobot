# راهنمای تنظیم Webhook برای ربات تلگرام

## 📋 مراحل راه‌اندازی

### 1. آماده‌سازی فایل‌ها

قبل از تنظیم webhook، مطمئن شوید:

- ✅ فایل `.env` ایجاد شده و مقادیر صحیح در آن قرار دارد
- ✅ فایل `database_migration.sql` در پوشه اصلی پروژه است
- ✅ دسترسی‌های فایل‌ها صحیح است (معمولاً 644 برای فایل‌ها و 755 برای پوشه‌ها)

### 2. اجرای Migration (اولین بار)

**روش 1: استفاده از فایل setup.php (توصیه می‌شود)**

```bash
# از طریق مرورگر:
https://yourdomain.com/setup.php

# یا از طریق curl:
curl https://yourdomain.com/setup.php
```

این فایل:
- اتصال دیتابیس را بررسی می‌کند
- جداول را به صورت خودکار ایجاد می‌کند
- وضعیت webhook را نمایش می‌دهد

**روش 2: اجرای دستی Migration**

اگر می‌خواهید migration را دستی اجرا کنید:

```bash
# از طریق phpMyAdmin یا MySQL CLI:
mysql -u username -p database_name < database_migration.sql
```

### 3. تنظیم Webhook

**روش 1: استفاده از curl (توصیه می‌شود)**

```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://yourdomain.com/bot.php"
```

**روش 2: استفاده از مرورگر**

```
https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://yourdomain.com/bot.php
```

**مثال واقعی:**

```
https://api.telegram.org/bot1749556463:AAHYxE--3DhRZHA3aeaolY6I8JDpu0FJ6pc/setWebhook?url=https://codezed.ir/Bots/Pejvak-MEO/bot.php
```

### 4. بررسی وضعیت Webhook

```bash
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
```

یا از طریق مرورگر:
```
https://api.telegram.org/bot<TOKEN>/getWebhookInfo
```

**پاسخ موفق:**

```json
{
  "ok": true,
  "result": {
    "url": "https://yourdomain.com/bot.php",
    "has_custom_certificate": false,
    "pending_update_count": 0
  }
}
```

### 5. حذف Webhook (در صورت نیاز)

```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/deleteWebhook"
```

## 🔍 عیب‌یابی

### مشکل: ربات پاسخ نمی‌دهد

**بررسی 1: Webhook تنظیم شده است؟**

```bash
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
```

**بررسی 2: فایل bot.php قابل دسترسی است؟**

```bash
curl https://yourdomain.com/bot.php
```

اگر خطای 404 یا 500 می‌دهد، مسیر را بررسی کنید.

**بررسی 3: خطاهای PHP**

- بررسی فایل `migration_log.txt` در پوشه اصلی
- بررسی error log های سرور (معمولاً در `/var/log/apache2/error.log` یا `/var/log/nginx/error.log`)
- فعال کردن نمایش خطاها در `config.php` (فقط برای تست):

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

**بررسی 4: جداول دیتابیس ایجاد شده‌اند؟**

از فایل `setup.php` استفاده کنید یا مستقیماً در phpMyAdmin بررسی کنید.

**بررسی 5: فایل .env موجود است؟**

```bash
ls -la .env
```

اگر وجود ندارد، از `.env-sample` کپی کنید:

```bash
cp .env-sample .env
# سپس مقادیر را ویرایش کنید
```

### مشکل: Migration اجرا نمی‌شود

**راه‌حل 1: اجرای دستی**

```bash
mysql -u username -p database_name < database_migration.sql
```

**راه‌حل 2: بررسی دسترسی فایل**

```bash
chmod 644 database_migration.sql
```

**راه‌حل 3: بررسی لاگ**

فایل `migration_log.txt` را بررسی کنید.

### مشکل: خطای 500 Internal Server Error

1. بررسی error log های سرور
2. بررسی دسترسی‌های فایل‌ها
3. بررسی اینکه PHP extension های مورد نیاز نصب هستند (PDO, curl, json)
4. بررسی محدودیت‌های memory_limit و max_execution_time

## 📝 نکات مهم

1. **HTTPS الزامی است:** Telegram فقط webhook های HTTPS را می‌پذیرد
2. **مسیر صحیح:** مطمئن شوید URL webhook به فایل `bot.php` اشاره می‌کند
3. **پارامتر امنیتی:** در `bot.php` خط 3، یک چک امنیتی وجود دارد که برای webhook غیرفعال شده است
4. **فایل setup.php:** بعد از اطمینان از صحت همه موارد، این فایل را حذف یا محافظت کنید

## ✅ چک‌لیست نهایی

- [ ] فایل `.env` ایجاد و تنظیم شده
- [ ] Migration اجرا شده و جداول ایجاد شده‌اند
- [ ] Webhook تنظیم شده و وضعیت آن OK است
- [ ] فایل `bot.php` قابل دسترسی است
- [ ] ربات به پیام‌ها پاسخ می‌دهد
- [ ] فایل `setup.php` حذف یا محافظت شده است

## 🔗 لینک‌های مفید

- [Telegram Bot API Documentation](https://core.telegram.org/bots/api)
- [Webhook Guide](https://core.telegram.org/bots/api#setwebhook)


