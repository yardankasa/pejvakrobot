<?php
/**
 * فایل کانفیگ مرکزی پروژه PejvakRobot
 * تمام تنظیمات پروژه در این فایل متمرکز شده است
 */

// ============================================
// بخش 1: بارگذاری متغیرهای محیطی (.env)
// ============================================
// این تابع فایل .env را خوانده و متغیرهای محیطی را تنظیم می‌کند
function loadEnv($envFile = __DIR__ . '/.env') {
    if (!file_exists($envFile)) {
        return; // اگر فایل .env وجود ندارد، ادامه می‌دهد
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // نادیده گرفتن کامنت‌ها
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // جدا کردن key و value
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // حذف کوتیشن‌ها
            $value = trim($value, '"\'');
            
            // تنظیم متغیر محیطی
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// بارگذاری فایل .env
loadEnv();

// تابع کمکی برای خواندن از .env با مقدار پیش‌فرض
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}

// ============================================
// بخش 2: تنظیمات دیتابیس اصلی
// ============================================
// این تنظیمات برای دیتابیس اصلی ربات استفاده می‌شود
define('DB_MAIN_HOST', env('DB_MAIN_HOST', 'localhost'));
define('DB_MAIN_NAME', env('DB_MAIN_NAME', 'codezedi_pejvaksource'));
define('DB_MAIN_USER', env('DB_MAIN_USER', 'codezedi_botuser'));
define('DB_MAIN_PASS', env('DB_MAIN_PASS', '6zOd&LPTOS1w'));

// ============================================
// بخش 3: تنظیمات دیتابیس تیکت
// ============================================
// این تنظیمات برای دیتابیس سیستم تیکت استفاده می‌شود
define('DB_TICKET_HOST', env('DB_TICKET_HOST', 'localhost'));
define('DB_TICKET_NAME', env('DB_TICKET_NAME', 'codezedi_pejvakticket'));
define('DB_TICKET_USER', env('DB_TICKET_USER', 'codezedi_botuser'));
define('DB_TICKET_PASS', env('DB_TICKET_PASS', '6zOd&LPTOS1w'));

// برای سازگاری با کدهای قدیمی
define('DB_HOST', DB_TICKET_HOST);
define('DB_NAME', DB_TICKET_NAME);
define('DB_USER', DB_TICKET_USER);
define('DB_PASS', DB_TICKET_PASS);

// ============================================
// بخش 4: تنظیمات ربات تلگرام
// ============================================
// توکن ربات تلگرام - از .env خوانده می‌شود
define('TOKEN_POKER', env('TELEGRAM_BOT_TOKEN', '1749556463:AAHYxE--3DhRZHA3aeaolY6I8JDpu0FJ6pc'));

// برای سازگاری با سیستم تیکت
define('BOT_TOKEN', TOKEN_POKER);

// آیدی کانال ZM (عضوگیری)
define('ZM_CHANNEL_ID', env('ZM_CHANNEL_ID', -1001333270529));

// ============================================
// بخش 5: تنظیمات برند
// ============================================
// نام برند برای استفاده در تمام پیام‌های کاربری
// این مقدار در تمام پیام‌های ربات، پنل مدیریت، و پاسخ‌های تیکت استفاده می‌شود
$brand_name = env('BRAND_NAME', 'پژواک سورس');

// نام کاربری کانال تلگرام (باید شامل @ باشد)
// این مقدار برای ارسال/ویرایش پیام‌ها در کانال و لینک‌های کانال استفاده می‌شود
$brand_username = env('BRAND_USERNAME', '@Pejvaksource');

// ============================================
// بخش 6: تنظیمات کانال و چنل
// ============================================
$date_en = date("Y/m/d H:i:s");

// تنظیمات کانال اصلی
$channel = [
    'domin' => env('CHANNEL_DOMAIN'),
    'MerchantID' => env('PAYMENT_MERCHANT_ID', 'zibal'),
    'username' => env('CHANNEL_USERNAME', 'PejvakSource'),
    'ch_logs' => env('CHANNEL_LOGS_ID', -1001511214347),
    'bot_id' => env('BOT_ID', 'PejvakRobot'),
    'tariff' => env('PAYMENT_TARIFF', 28900), // مبلغ به ریال
    
    'id' => [
        env('CHANNEL_ID_1', '@PejvakSource'),
        env('CHANNEL_ID_2', '@PejvakSource')
    ],
    'link' => [
        env('CHANNEL_LINK_1', 'https://t.me/PejvakSource'),
        env('CHANNEL_LINK_2', 'https://t.me/PejvakSource')
    ],
    'subset_coin' => env('SUBSET_COIN_REWARD', 1),
    'like_coin' => env('LIKE_COIN_REWARD', 0.2),
];

$toman_tariff = $channel['tariff'] / 10;

// ============================================
// بخش 7: تنظیمات سیستم تیکت
// ============================================
// آیدی‌های سوپر ادمین (می‌توانند به همه بخش‌ها دسترسی داشته باشند)
$superAdminIds = env('SUPER_ADMIN_IDS', '1604140942');
define('SUPER_ADMIN_IDS', array_map('intval', explode(',', $superAdminIds)));

// آدرس Web App سیستم تیکت
define('WEB_APP_URL', env('TICKET_WEB_APP_URL', 'https://codezed.ir/Bots/Pejvak-MEO/ticket_system/'));

// ============================================
// بخش 8: تنظیمات پرداخت
// ============================================
// کلید مرچنت Zibal
define('ZIBAL_MERCHANT_KEY', env('ZIBAL_MERCHANT_KEY', 'zibal'));

// ============================================
// بخش 9: تنظیمات کش
// ============================================
define('CACHE_ENABLED', env('CACHE_ENABLED', true));
define('CACHE_DIR', __DIR__ . '/cache/');
define('CACHE_TIME', env('CACHE_TIME', 300)); // مدت زمان کش به ثانیه

// ایجاد پوشه کش در صورت عدم وجود
if (CACHE_ENABLED && !is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

// ============================================
// بخش 10: اتصال به دیتابیس اصلی
// ============================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_MAIN_HOST . ";dbname=" . DB_MAIN_NAME . ";charset=utf8mb4",
        DB_MAIN_USER,
        DB_MAIN_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_persian_ci"
        ]
    );
} catch(PDOException $e) {
    die("خطا در اتصال به دیتابیس اصلی: " . $e->getMessage());
}

// ============================================
// بخش 11: تنظیمات زمان‌بندی
// ============================================
date_default_timezone_set(env('TIMEZONE', 'Asia/Tehran'));

// ============================================
// بخش 12: تنظیمات لاگ
// ============================================
// اگر می‌خواهید error reporting را فعال کنید، این خط را uncomment کنید
// error_reporting(E_ALL);
// ini_set('display_errors', 0);
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// ============================================
// بخش 13: بارگذاری لاگ‌های JSON (اگر وجود دارد)
// ============================================
$logs = [];
$logsFile = __DIR__ . '/data/logs.json';
if (file_exists($logsFile)) {
    $logsContent = file_get_contents($logsFile);
    $logs = json_decode($logsContent, true) ?: [];
}
