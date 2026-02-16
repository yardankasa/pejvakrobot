-- ============================================
-- فایل مهاجرت دیتابیس برای پروژه PejvakRobot
-- ============================================
-- این فایل شامل تمام جداول و داده‌های اولیه مورد نیاز است
-- سازگار با MySQL و MariaDB
-- ============================================
-- تاریخ ایجاد: 2024
-- ============================================

-- ============================================
-- بخش 1: ایجاد دیتابیس‌ها (اختیاری)
-- ============================================
-- اگر دیتابیس‌ها از قبل وجود دارند، این بخش را کامنت کنید
-- ============================================

-- CREATE DATABASE IF NOT EXISTS `codezedi_pejvaksource` 
--     CHARACTER SET utf8mb4 
--     COLLATE utf8mb4_persian_ci;

-- CREATE DATABASE IF NOT EXISTS `codezedi_pejvakticket` 
--     CHARACTER SET utf8mb4 
--     COLLATE utf8mb4_persian_ci;

-- USE `codezedi_pejvaksource`;

-- ============================================
-- بخش 2: جداول دیتابیس اصلی (codezedi_pejvaksource)
-- ============================================

-- ============================================
-- جدول users: اطلاعات کاربران ربات
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT(20) NOT NULL COMMENT 'آیدی عددی کاربر در تلگرام',
    `inviter` BIGINT(20) DEFAULT 0 COMMENT 'آیدی کاربر دعوت‌کننده',
    `timer` DATE DEFAULT NULL COMMENT 'تاریخ ثبت نام',
    `step` VARCHAR(255) DEFAULT 'NULL' COMMENT 'مرحله فعلی کاربر در ربات',
    `coin` INT(11) DEFAULT 0 COMMENT 'تعداد سکه کاربر',
    `silver` INT(11) DEFAULT 0 COMMENT 'تعداد نقره کاربر',
    `subset` INT(11) DEFAULT 0 COMMENT 'تعداد زیرمجموعه',
    `down_count` INT(11) DEFAULT 0 COMMENT 'تعداد دانلود انجام شده',
    `like_count` INT(11) DEFAULT 0 COMMENT 'تعداد لایک انجام شده',
    `buy_count` INT(11) DEFAULT 0 COMMENT 'تعداد خرید انجام شده',
    `block` TINYINT(1) DEFAULT 0 COMMENT 'وضعیت بلاک: 0=عادی, 1=بلاک, 2=تعلیق',
    `flood` INT(11) DEFAULT 0 COMMENT 'زمان آخرین فعالیت (timestamp)',
    `phone_number` VARCHAR(20) DEFAULT '0' COMMENT 'شماره تلفن کاربر',
    `zm_penalty_count` INT(11) DEFAULT 0 COMMENT 'تعداد جریمه سیستم ZM',
    `last_subset` DATE DEFAULT NULL COMMENT 'تاریخ آخرین زیرمجموعه',
    `daily_subset` INT(11) DEFAULT 0 COMMENT 'تعداد زیرمجموعه روزانه',
    `last_spin_time` INT(11) DEFAULT 0 COMMENT 'زمان آخرین چرخش گردونه شانس (timestamp)',
    PRIMARY KEY (`id`),
    INDEX `idx_inviter` (`inviter`),
    INDEX `idx_block` (`block`),
    INDEX `idx_flood` (`flood`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='جدول کاربران ربات';

-- ============================================
-- جدول files: فایل‌های سورس قابل دانلود
-- ============================================
CREATE TABLE IF NOT EXISTS `files` (
    `id` BIGINT(20) NOT NULL COMMENT 'آیدی پیام در کانال (Primary Key)',
    `cover` VARCHAR(255) DEFAULT NULL COMMENT 'آیدی فایل عکس بنر در تلگرام',
    `title` VARCHAR(300) NOT NULL COMMENT 'عنوان سورس',
    `lang` VARCHAR(100) DEFAULT NULL COMMENT 'زبان توسعه',
    `caption` TEXT COMMENT 'توضیحات سورس',
    `ads_type` ENUM('free', 'vip', 'coin', 'zm', 'stars') DEFAULT 'free' COMMENT 'نوع سورس',
    `limits` INT(11) DEFAULT 0 COMMENT 'محدودیت تعداد دانلود رایگان',
    `amount` INT(11) DEFAULT 0 COMMENT 'قیمت (ریال یا سکه یا ستاره)',
    `file_id` VARCHAR(255) NOT NULL COMMENT 'آیدی فایل در تلگرام',
    `down_count` INT(11) DEFAULT 0 COMMENT 'تعداد دانلود',
    `like_count` INT(11) DEFAULT 0 COMMENT 'تعداد لایک',
    `buy_count` INT(11) DEFAULT 0 COMMENT 'تعداد خرید',
    PRIMARY KEY (`id`),
    INDEX `idx_ads_type` (`ads_type`),
    INDEX `idx_title` (`title`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='جدول فایل‌های سورس';

-- ============================================
-- جدول download: تاریخچه دانلودها
-- ============================================
CREATE TABLE IF NOT EXISTS `download` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT(20) NOT NULL COMMENT 'آیدی کاربر',
    `file_id` BIGINT(20) NOT NULL COMMENT 'آیدی فایل',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_file` (`user_id`, `file_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_file_id` (`file_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='تاریخچه دانلودها';

-- ============================================
-- جدول buy: تاریخچه خریدها
-- ============================================
CREATE TABLE IF NOT EXISTS `buy` (
    `id` VARCHAR(255) NOT NULL COMMENT 'کد رهگیری پرداخت',
    `owner` BIGINT(20) NOT NULL COMMENT 'آیدی خریدار',
    `amount` INT(11) NOT NULL COMMENT 'مبلغ پرداخت شده (ریال)',
    `date` DATE NOT NULL COMMENT 'تاریخ خرید',
    `time` TIME NOT NULL COMMENT 'زمان خرید',
    `product` VARCHAR(255) DEFAULT NULL COMMENT 'محصول خریداری شده (file_id یا تعداد سکه)',
    PRIMARY KEY (`id`),
    INDEX `idx_owner` (`owner`),
    INDEX `idx_date` (`date`),
    FOREIGN KEY (`owner`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='تاریخچه خریدها';

-- ============================================
-- جدول likes: لایک‌های کاربران
-- ============================================
CREATE TABLE IF NOT EXISTS `likes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `like_gift` TINYINT(1) DEFAULT 0 COMMENT 'وضعیت هدیه لایک: 0=نداده, 1=داده',
    `user_id` BIGINT(20) NOT NULL COMMENT 'آیدی کاربر',
    `file_id` BIGINT(20) NOT NULL COMMENT 'آیدی فایل',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_file_like` (`user_id`, `file_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_file_id` (`file_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='لایک‌های کاربران';

-- ============================================
-- جدول panel: تنظیمات پنل مدیریت
-- ============================================
CREATE TABLE IF NOT EXISTS `panel` (
    `id` INT(11) NOT NULL DEFAULT 85 COMMENT 'همیشه 85',
    `admins` TEXT COMMENT 'لیست مدیران (^ جدا شده)',
    `power` TINYINT(1) DEFAULT 1 COMMENT 'وضعیت ربات: 0=خاموش, 1=روشن',
    `luckwheel_status` TINYINT(1) DEFAULT 1 COMMENT 'وضعیت گردونه شانس: 0=خاموش, 1=روشن',
    `time` INT(11) DEFAULT 0 COMMENT 'زمان ارسال سورس رایگان بعدی (timestamp)',
    `time_vip` INT(11) DEFAULT 0 COMMENT 'زمان ارسال سورس VIP بعدی (timestamp)',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='تنظیمات پنل مدیریت';

-- ============================================
-- جدول Admins: اطلاعات مدیران
-- ============================================
CREATE TABLE IF NOT EXISTS `Admins` (
    `id` BIGINT(20) NOT NULL COMMENT 'آیدی مدیر',
    `type` TINYINT(1) DEFAULT 2 COMMENT 'نوع مدیر: 1=مدیر, 2=همکار',
    `grower` BIGINT(20) DEFAULT NULL COMMENT 'آیدی کسی که این مدیر را اضافه کرده',
    `posts` TEXT DEFAULT NULL COMMENT 'پست‌های مدیر',
    `timeup` VARCHAR(255) DEFAULT NULL COMMENT 'زمان اضافه شدن',
    PRIMARY KEY (`id`),
    INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='اطلاعات مدیران';

-- ============================================
-- جدول send_all: تنظیمات ارسال همگانی
-- ============================================
CREATE TABLE IF NOT EXISTS `send_all` (
    `id` INT(11) NOT NULL DEFAULT 85 COMMENT 'همیشه 85',
    `type` VARCHAR(50) DEFAULT NULL COMMENT 'نوع ارسال: forward, send, ehda',
    `count` INT(11) DEFAULT 0 COMMENT 'تعداد ارسال شده',
    `from_id` BIGINT(20) DEFAULT NULL COMMENT 'آیدی فرستنده',
    `msg_id` BIGINT(20) DEFAULT NULL COMMENT 'آیدی پیام برای فوروارد',
    `sendtype` VARCHAR(50) DEFAULT NULL COMMENT 'نوع محتوا: text, photo, video, document',
    `text` TEXT DEFAULT NULL COMMENT 'متن پیام',
    `media` VARCHAR(255) DEFAULT NULL COMMENT 'آیدی رسانه',
    `caption` TEXT DEFAULT NULL COMMENT 'کپشن',
    `value` INT(11) DEFAULT NULL COMMENT 'مقدار (برای اهدای سکه)',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='تنظیمات ارسال همگانی';

-- ============================================
-- جدول re_payments: پرداخت‌ها
-- ============================================
CREATE TABLE IF NOT EXISTS `re_payments` (
    `id` VARCHAR(255) NOT NULL COMMENT 'کد یکتای پرداخت',
    `file` BIGINT(20) DEFAULT 0 COMMENT 'آیدی فایل (0 برای خرید سکه)',
    `amount` INT(11) NOT NULL COMMENT 'مبلغ (ریال)',
    `desc` VARCHAR(500) DEFAULT NULL COMMENT 'توضیحات',
    `type` ENUM('source', 'coin') NOT NULL COMMENT 'نوع پرداخت',
    `fromid` BIGINT(20) NOT NULL COMMENT 'آیدی پرداخت‌کننده',
    `time` INT(11) NOT NULL COMMENT 'زمان ایجاد (timestamp)',
    `status` VARCHAR(50) DEFAULT 'nopay' COMMENT 'وضعیت: nopay, yespay',
    PRIMARY KEY (`id`),
    INDEX `idx_fromid` (`fromid`),
    INDEX `idx_file` (`file`),
    INDEX `idx_status` (`status`),
    INDEX `idx_time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='پرداخت‌ها';

-- ============================================
-- جدول reactions: واکنش‌های کاربران به پیام‌ها
-- ============================================
CREATE TABLE IF NOT EXISTS `reactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT(20) NOT NULL COMMENT 'آیدی کاربر',
    `message_id` BIGINT(20) NOT NULL COMMENT 'آیدی پیام در کانال',
    `emoji` VARCHAR(10) DEFAULT NULL COMMENT 'ایموجی واکنش',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان ایجاد',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_message` (`user_id`, `message_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='واکنش‌های کاربران';

-- ============================================
-- جدول zm_invites: دعوت‌های سیستم ZM (عضوگیری)
-- ============================================
CREATE TABLE IF NOT EXISTS `zm_invites` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT(20) NOT NULL COMMENT 'آیدی کاربر دعوت‌کننده',
    `file_id` BIGINT(20) NOT NULL COMMENT 'آیدی فایل مورد نظر',
    `invite_link` VARCHAR(500) NOT NULL COMMENT 'لینک دعوت',
    `required_members` INT(11) NOT NULL COMMENT 'تعداد اعضای مورد نیاز',
    `current_members` INT(11) DEFAULT 0 COMMENT 'تعداد اعضای فعلی',
    `status` ENUM('pending', 'completed') DEFAULT 'pending' COMMENT 'وضعیت: pending, completed',
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_file_id` (`file_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_invite_link` (`invite_link`(255)),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='دعوت‌های سیستم ZM';

-- ============================================
-- جدول zm_joins: عضویت‌های سیستم ZM
-- ============================================
CREATE TABLE IF NOT EXISTS `zm_joins` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `joiner_id` BIGINT(20) NOT NULL COMMENT 'آیدی کاربری که عضو شده',
    `inviter_id` BIGINT(20) NOT NULL COMMENT 'آیدی کاربر دعوت‌کننده',
    `channel_id` BIGINT(20) NOT NULL COMMENT 'آیدی کانال',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_joiner_channel` (`joiner_id`, `channel_id`),
    INDEX `idx_joiner_id` (`joiner_id`),
    INDEX `idx_inviter_id` (`inviter_id`),
    INDEX `idx_channel_id` (`channel_id`),
    FOREIGN KEY (`joiner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`inviter_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='عضویت‌های سیستم ZM';

-- ============================================
-- جدول luckwheel_stats: آمار گردونه شانس
-- ============================================
CREATE TABLE IF NOT EXISTS `luckwheel_stats` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT(20) NOT NULL COMMENT 'آیدی کاربر',
    `prize_type` ENUM('coins', 'silver', 'source', 'nothing') NOT NULL COMMENT 'نوع جایزه',
    `prize_value` INT(11) DEFAULT 0 COMMENT 'مقدار جایزه',
    `spin_time` INT(11) NOT NULL COMMENT 'زمان چرخش (timestamp)',
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_prize_type` (`prize_type`),
    INDEX `idx_spin_time` (`spin_time`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='آمار گردونه شانس';

-- ============================================
-- جدول sends: سورس‌های ارسالی توسط کاربران (در انتظار تایید)
-- ============================================
CREATE TABLE IF NOT EXISTS `sends` (
    `id` INT(11) NOT NULL COMMENT 'کد یکتای سورس',
    `cover` VARCHAR(255) DEFAULT NULL COMMENT 'آیدی عکس بنر',
    `title` VARCHAR(300) NOT NULL COMMENT 'عنوان',
    `lang` VARCHAR(100) DEFAULT NULL COMMENT 'زبان',
    `caption` TEXT COMMENT 'توضیحات',
    `ads_type` VARCHAR(50) DEFAULT 'free' COMMENT 'نوع',
    `limits` INT(11) DEFAULT 0 COMMENT 'محدودیت',
    `amount` INT(11) DEFAULT 0 COMMENT 'قیمت',
    `file_id` VARCHAR(255) NOT NULL COMMENT 'آیدی فایل',
    `sender` BIGINT(20) NOT NULL COMMENT 'آیدی فرستنده',
    PRIMARY KEY (`id`),
    INDEX `idx_sender` (`sender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='سورس‌های در انتظار تایید';

-- ============================================
-- بخش 3: جداول دیتابیس تیکت (codezedi_pejvakticket)
-- ============================================

-- USE `codezedi_pejvakticket`;

-- ============================================
-- جدول users: کاربران سیستم تیکت
-- ============================================
-- توجه: این جدول در دیتابیس جداگانه ticket است و با جدول users دیتابیس اصلی تداخلی ندارد
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` BIGINT(20) NOT NULL COMMENT 'آیدی کاربر در تلگرام',
    `user_name` VARCHAR(255) DEFAULT NULL COMMENT 'نام کاربر',
    `username` VARCHAR(255) DEFAULT NULL COMMENT 'یوزرنیم تلگرام',
    `first_name` VARCHAR(255) DEFAULT NULL COMMENT 'نام از تلگرام',
    `last_name` VARCHAR(255) DEFAULT NULL COMMENT 'نام خانوادگی از تلگرام',
    `photo_url` VARCHAR(500) DEFAULT NULL COMMENT 'آدرس عکس پروفایل',
    `admin_display_name` VARCHAR(100) DEFAULT NULL COMMENT 'نام نمایشی ادمین در پاسخ‌ها',
    `role` ENUM('user', 'admin', 'super_admin') DEFAULT 'user' COMMENT 'نقش کاربر',
    `is_banned` TINYINT(1) DEFAULT 0 COMMENT 'وضعیت بلاک: 0=عادی, 1=بلاک',
    `first_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان اولین ورود',
    PRIMARY KEY (`user_id`),
    INDEX `idx_role` (`role`),
    INDEX `idx_is_banned` (`is_banned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='کاربران سیستم تیکت';

-- ============================================
-- جدول tickets: تیکت‌های پشتیبانی
-- ============================================
CREATE TABLE IF NOT EXISTS `tickets` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT(20) NOT NULL COMMENT 'آیدی کاربر',
    `user_name` VARCHAR(255) DEFAULT NULL COMMENT 'نام کاربر',
    `title` VARCHAR(500) NOT NULL COMMENT 'عنوان تیکت',
    `department` VARCHAR(100) DEFAULT NULL COMMENT 'دپارتمان',
    `status` ENUM('open', 'answered', 'closed') DEFAULT 'open' COMMENT 'وضعیت تیکت',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان ایجاد',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'زمان آخرین بروزرسانی',
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_department` (`department`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='تیکت‌های پشتیبانی';

-- ============================================
-- جدول ticket_messages: پیام‌های تیکت
-- ============================================
CREATE TABLE IF NOT EXISTS `ticket_messages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` INT(11) NOT NULL COMMENT 'آیدی تیکت',
    `sender_id` BIGINT(20) NOT NULL COMMENT 'آیدی فرستنده',
    `sender_type` ENUM('user', 'admin') NOT NULL COMMENT 'نوع فرستنده',
    `admin_display_name` VARCHAR(100) DEFAULT NULL COMMENT 'نام ادمین هنگام ارسال پاسخ',
    `message` TEXT NOT NULL COMMENT 'متن پیام',
    `has_attachment` TINYINT(1) DEFAULT 0 COMMENT 'آیا پیوست دارد',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان ارسال',
    PRIMARY KEY (`id`),
    INDEX `idx_ticket_id` (`ticket_id`),
    INDEX `idx_sender_id` (`sender_id`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='پیام‌های تیکت';

-- ============================================
-- جدول ticket_attachments: فایل‌های پیوست تیکت
-- ============================================
CREATE TABLE IF NOT EXISTS `ticket_attachments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `message_id` INT(11) NOT NULL COMMENT 'آیدی پیام',
    `file_path` VARCHAR(500) NOT NULL COMMENT 'مسیر فایل',
    `original_name` VARCHAR(255) DEFAULT NULL COMMENT 'نام اصلی فایل',
    `file_type` VARCHAR(100) DEFAULT NULL COMMENT 'نوع فایل (MIME)',
    PRIMARY KEY (`id`),
    INDEX `idx_message_id` (`message_id`),
    FOREIGN KEY (`message_id`) REFERENCES `ticket_messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci COMMENT='فایل‌های پیوست تیکت';

-- ============================================
-- بخش 4: داده‌های اولیه (Seed Data)
-- ============================================

-- ============================================
-- تنظیمات اولیه پنل مدیریت
-- ============================================
-- USE `codezedi_pejvaksource`;

INSERT INTO `panel` (`id`, `admins`, `power`, `luckwheel_status`) 
VALUES (85, '1604140942^', 1, 1)
ON DUPLICATE KEY UPDATE 
    `admins` = VALUES(`admins`),
    `power` = VALUES(`power`),
    `luckwheel_status` = VALUES(`luckwheel_status`);

-- ============================================
-- تنظیمات اولیه ارسال همگانی
-- ============================================
INSERT INTO `send_all` (`id`, `type`, `count`, `from_id`, `msg_id`, `sendtype`, `text`, `media`, `caption`, `value`) 
VALUES (85, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE `id` = 85;

INSERT INTO `send_all` (`id`, `type`, `count`, `from_id`, `msg_id`, `sendtype`, `text`, `media`, `caption`, `value`) 
VALUES (86, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE `id` = 86;

-- ============================================
-- مهاجرت: افزودن ستون‌های time و time_vip به panel (برای دیتابیس‌های موجود)
-- ============================================
-- در صورت خطای "Unknown column 'time'" این دستورات را اجرا کنید:
-- ALTER TABLE `panel` ADD COLUMN IF NOT EXISTS `time` INT(11) DEFAULT 0 COMMENT 'زمان ارسال سورس رایگان بعدی (timestamp)';
-- ALTER TABLE `panel` ADD COLUMN IF NOT EXISTS `time_vip` INT(11) DEFAULT 0 COMMENT 'زمان ارسال سورس VIP بعدی (timestamp)';
-- توجه: MySQL قدیمی‌تر IF NOT EXISTS را برای ADD COLUMN پشتیبانی نمی‌کند. در آن صورت:
-- ALTER TABLE `panel` ADD COLUMN `time` INT(11) DEFAULT 0;
-- ALTER TABLE `panel` ADD COLUMN `time_vip` INT(11) DEFAULT 0;

-- ============================================
-- نکات مهم:
-- ============================================
-- 1. قبل از اجرای این فایل، دیتابیس‌ها را ایجاد کنید
-- 2. اطلاعات حساس (مثل آیدی مدیر) را تغییر دهید
-- 3. این فایل idempotent است و می‌توانید چند بار اجرا کنید
-- 4. Foreign Key ها برای یکپارچگی داده‌ها تعریف شده‌اند
-- 5. Index ها برای بهبود عملکرد کوئری‌ها اضافه شده‌اند
-- ============================================

