<?php
/**
 * فایل راه‌اندازی و تست ربات
 * این فایل برای بررسی تنظیمات، اجرای migration و بررسی webhook استفاده می‌شود
 */

// بررسی اینکه آیا مرحله 2 (تایید) انجام شده یا نه
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$confirmed = isset($_GET['confirmed']) && $_GET['confirmed'] === 'yes';

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>راه‌اندازی ربات PejvakRobot</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #34495e;
            margin-top: 30px;
            padding: 10px;
            background: #ecf0f1;
            border-right: 4px solid #3498db;
        }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .warning { color: #f39c12; }
        .info { color: #3498db; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 12px;
            text-align: right;
            border: 1px solid #ddd;
        }
        table th {
            background: #3498db;
            color: white;
        }
        table tr:nth-child(even) {
            background: #f9f9f9;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            direction: ltr;
            text-align: left;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-danger {
            background: #e74c3c;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .btn-success {
            background: #27ae60;
        }
        .btn-success:hover {
            background: #229954;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-right: 4px solid;
        }
        .alert-info {
            background: #d1ecf1;
            border-color: #3498db;
            color: #0c5460;
        }
        .alert-warning {
            background: #fff3cd;
            border-color: #f39c12;
            color: #856404;
        }
        .alert-success {
            background: #d4edda;
            border-color: #27ae60;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            border-color: #e74c3c;
            color: #721c24;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            padding: 0;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 15px;
            background: #ecf0f1;
            margin: 0 5px;
            border-radius: 5px;
            position: relative;
        }
        .step.active {
            background: #3498db;
            color: white;
        }
        .step.completed {
            background: #27ae60;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 راه‌اندازی ربات PejvakRobot</h1>
        
        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                <strong>مرحله 1:</strong> بررسی تنظیمات
            </div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                <strong>مرحله 2:</strong> تایید و بررسی
            </div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                <strong>مرحله 3:</strong> ساخت جداول
            </div>
            <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">
                <strong>مرحله 4:</strong> بررسی Webhook
            </div>
        </div>

        <hr>

        <?php if ($step == 1): ?>
            <!-- ============================================ -->
            <!-- مرحله 1: نمایش اطلاعات .env -->
            <!-- ============================================ -->
            <h2>📋 مرحله 1: بررسی تنظیمات (.env)</h2>

            <?php
            $envFile = __DIR__ . '/.env';
            $envSampleFile = __DIR__ . '/.env-sample';
            
            if (!file_exists($envFile)) {
                echo '<div class="alert alert-danger">';
                echo '❌ فایل <code>.env</code> یافت نشد!<br>';
                echo 'لطفاً فایل <code>.env-sample</code> را کپی کرده و نام آن را به <code>.env</code> تغییر دهید.';
                echo '</div>';
                exit;
            }
            
            // خواندن فایل .env
            $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $envVars = [];
            
            foreach ($envLines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    $value = trim($value, '"\'');
                    $envVars[$key] = $value;
                }
            }
            
            // دسته‌بندی متغیرها
            $categories = [
                'دیتابیس اصلی' => ['DB_MAIN_HOST', 'DB_MAIN_NAME', 'DB_MAIN_USER', 'DB_MAIN_PASS'],
                'دیتابیس تیکت' => ['DB_TICKET_HOST', 'DB_TICKET_NAME', 'DB_TICKET_USER', 'DB_TICKET_PASS'],
                'ربات تلگرام' => ['TELEGRAM_BOT_TOKEN', 'ZM_CHANNEL_ID', 'SUPER_ADMIN_IDS'],
                'تنظیمات برند' => ['BRAND_NAME', 'BRAND_USERNAME'],
                'تنظیمات کانال' => ['CHANNEL_USERNAME', 'CHANNEL_LOGS_ID', 'BOT_ID', 'CHANNEL_ID_1', 'CHANNEL_ID_2', 'CHANNEL_LINK_1', 'CHANNEL_LINK_2'],
                'تنظیمات پرداخت' => ['PAYMENT_MERCHANT_ID', 'PAYMENT_TARIFF', 'ZIBAL_MERCHANT_KEY'],
                'تنظیمات پاداش' => ['SUBSET_COIN_REWARD', 'LIKE_COIN_REWARD'],
                'سیستم تیکت' => ['TICKET_WEB_APP_URL'],
                'سایر' => []
            ];
            
            // پیدا کردن متغیرهای دسته "سایر"
            $allCategorized = [];
            foreach ($categories as $cat => $vars) {
                if ($cat !== 'سایر') {
                    $allCategorized = array_merge($allCategorized, $vars);
                }
            }
            foreach ($envVars as $key => $value) {
                if (!in_array($key, $allCategorized)) {
                    $categories['سایر'][] = $key;
                }
            }
            ?>
            
            <div class="alert alert-info">
                <strong>ℹ️ نکته:</strong> مقادیر حساس (رمز عبور، توکن) به صورت مخفی نمایش داده می‌شوند.
            </div>

            <?php foreach ($categories as $category => $vars): ?>
                <?php if (empty($vars)) continue; ?>
                <h3 style="color: #34495e; margin-top: 20px;"><?php echo $category; ?></h3>
                <table>
                    <thead>
                        <tr>
                            <th>متغیر</th>
                            <th>مقدار</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vars as $var): ?>
                            <?php if (!isset($envVars[$var])) continue; ?>
                            <?php 
                            $value = $envVars[$var];
                            $isSensitive = in_array($var, ['DB_MAIN_PASS', 'DB_TICKET_PASS', 'TELEGRAM_BOT_TOKEN', 'ZIBAL_MERCHANT_KEY']);
                            $displayValue = $isSensitive ? str_repeat('*', min(strlen($value), 20)) : $value;
                            $isEmpty = empty($value) || $value === '0' || $value === '';
                            ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($var); ?></code></td>
                                <td>
                                    <?php if ($isSensitive): ?>
                                        <span style="color: #7f8c8d;"><?php echo htmlspecialchars($displayValue); ?></span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($displayValue); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isEmpty): ?>
                                        <span class="warning">⚠️ خالی</span>
                                    <?php else: ?>
                                        <span class="success">✅ تنظیم شده</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>

            <div style="margin-top: 30px; text-align: center;">
                <a href="?step=2" class="btn btn-success">ادامه به مرحله 2 →</a>
            </div>

        <?php elseif ($step == 2): ?>
            <!-- ============================================ -->
            <!-- مرحله 2: تایید و بررسی -->
            <!-- ============================================ -->
            <h2>🔍 مرحله 2: بررسی و تایید</h2>

            <?php
            // بررسی اتصال دیتابیس اصلی
            echo '<h3>بررسی اتصال دیتابیس اصلی</h3>';
            try {
                $test = $pdo->query("SELECT 1");
                echo '<div class="alert alert-success">';
                echo '✅ اتصال به دیتابیس اصلی موفق: <strong>' . DB_MAIN_NAME . '</strong>';
                echo '</div>';
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger">';
                echo '❌ خطا در اتصال به دیتابیس اصلی: ' . htmlspecialchars($e->getMessage());
                echo '</div>';
                exit;
            }

            // بررسی جداول دیتابیس اصلی
            echo '<h3>بررسی جداول دیتابیس اصلی</h3>';
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (empty($tables)) {
                echo '<div class="alert alert-warning">';
                echo '⚠️ هیچ جدولی در دیتابیس اصلی وجود ندارد.';
                echo '</div>';
            } else {
                echo '<div class="alert alert-success">';
                echo '✅ جداول موجود در دیتابیس اصلی: <strong>' . count($tables) . '</strong> جدول<br>';
                echo '<ul>';
                foreach ($tables as $table) {
                    echo '<li><code>' . htmlspecialchars($table) . '</code></li>';
                }
                echo '</ul>';
                echo '</div>';
            }

            // بررسی اتصال دیتابیس تیکت
            echo '<h3>بررسی اتصال دیتابیس تیکت</h3>';
            try {
                $pdoTicket = new PDO(
                    "mysql:host=" . DB_TICKET_HOST . ";dbname=" . DB_TICKET_NAME . ";charset=utf8mb4",
                    DB_TICKET_USER,
                    DB_TICKET_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_persian_ci"
                    ]
                );
                echo '<div class="alert alert-success">';
                echo '✅ اتصال به دیتابیس تیکت موفق: <strong>' . DB_TICKET_NAME . '</strong>';
                echo '</div>';
                
                $ticketTables = $pdoTicket->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                if (empty($ticketTables)) {
                    echo '<div class="alert alert-warning">';
                    echo '⚠️ هیچ جدولی در دیتابیس تیکت وجود ندارد.';
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-success">';
                    echo '✅ جداول موجود در دیتابیس تیکت: <strong>' . count($ticketTables) . '</strong> جدول<br>';
                    echo '<ul>';
                    foreach ($ticketTables as $table) {
                        echo '<li><code>' . htmlspecialchars($table) . '</code></li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
            } catch (PDOException $e) {
                echo '<div class="alert alert-warning">';
                echo '⚠️ خطا در اتصال به دیتابیس تیکت: ' . htmlspecialchars($e->getMessage());
                echo '<br><small>این خطا در صورتی که دیتابیس تیکت جداگانه نباشد، طبیعی است.</small>';
                echo '</div>';
            }

            // بررسی فایل migration
            echo '<h3>بررسی فایل Migration</h3>';
            $migrationFile = __DIR__ . '/database_migration.sql';
            if (file_exists($migrationFile)) {
                echo '<div class="alert alert-success">';
                echo '✅ فایل migration موجود است<br>';
                echo '📁 مسیر: <code>' . htmlspecialchars($migrationFile) . '</code><br>';
                $fileSize = filesize($migrationFile);
                echo '📊 حجم فایل: <strong>' . number_format($fileSize) . '</strong> بایت';
                echo '</div>';
            } else {
                echo '<div class="alert alert-danger">';
                echo '❌ فایل migration یافت نشد: <code>' . htmlspecialchars($migrationFile) . '</code>';
                echo '</div>';
            }
            ?>

            <div class="alert alert-warning" style="margin-top: 30px;">
                <strong>⚠️ توجه:</strong> در مرحله بعد، جداول دیتابیس ایجاد خواهند شد. 
                اگر جداولی از قبل وجود دارند، migration فقط جداول جدید را اضافه می‌کند.
            </div>

            <div style="margin-top: 30px; text-align: center;">
                <a href="?step=1" class="btn">← بازگشت</a>
                <a href="?step=3&confirmed=yes" class="btn btn-success">ادامه به مرحله 3 →</a>
            </div>

        <?php elseif ($step == 3 && $confirmed): ?>
            <!-- ============================================ -->
            <!-- مرحله 3: ساخت جداول -->
            <!-- ============================================ -->
            <h2>🔨 مرحله 3: ساخت جداول (Migration)</h2>

            <?php
            // افزایش timeout برای اجرای migration
            set_time_limit(300); // 5 دقیقه
            ini_set('max_execution_time', 300);
            
            // تعریف constant برای نشان دادن اینکه از setup.php فراخوانی شده
            // این باعث می‌شود چک امنیتی در bot.php اجرا نشود
            if (!defined('SETUP_MODE')) {
                define('SETUP_MODE', true);
            }
            
            // فعال کردن output buffering برای نمایش خطاها
            ob_start();
            
            // بررسی اینکه $pdo تعریف شده باشد
            if (!isset($pdo) || $pdo === null) {
                echo '<div class="alert alert-danger">';
                echo '❌ خطا: اتصال به دیتابیس برقرار نشده است. لطفاً config.php را بررسی کنید.';
                echo '</div>';
                ob_end_flush();
                exit;
            }
            
            // تابع checkAndMigrateDatabase از bot.php
            // با SETUP_MODE تعریف شده، bot.php فقط تابع migration را لود می‌کند و بقیه کد را اجرا نمی‌کند
            if (!function_exists('checkAndMigrateDatabase')) {
                require_once 'bot.php';
            }
            
            // اجرای migration برای دیتابیس اصلی
            echo '<h3>اجرای Migration برای دیتابیس اصلی</h3>';
            echo '<p>در حال بررسی و اجرای migration... لطفاً صبر کنید.</p>';
            ob_flush();
            flush();
            
            try {
                $mainResult = checkAndMigrateDatabase($pdo, DB_MAIN_NAME);
                ob_flush();
                flush();
                
                if ($mainResult) {
                    echo '<div class="alert alert-success">';
                    echo '✅ Migration دیتابیس اصلی با موفقیت انجام شد';
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-warning">';
                    echo 'ℹ️ Migration دیتابیس اصلی انجام نشد (احتمالاً جداول از قبل وجود دارند)';
                    echo '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">';
                echo '❌ خطا در اجرای Migration دیتابیس اصلی: ' . htmlspecialchars($e->getMessage());
                echo '<br><small>جزئیات: ' . htmlspecialchars($e->getFile() . ':' . $e->getLine()) . '</small>';
                echo '</div>';
            }

            // بررسی جداول بعد از migration
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo '<div class="alert alert-info">';
            echo '📊 تعداد جداول در دیتابیس اصلی: <strong>' . count($tables) . '</strong><br>';
            if (!empty($tables)) {
                echo '<details><summary>لیست جداول</summary><ul>';
                foreach ($tables as $table) {
                    echo '<li><code>' . htmlspecialchars($table) . '</code></li>';
                }
                echo '</ul></details>';
            }
            echo '</div>';

            // اجرای migration برای دیتابیس تیکت
            echo '<h3>اجرای Migration برای دیتابیس تیکت</h3>';
            echo '<p>در حال بررسی و اجرای migration... لطفاً صبر کنید.</p>';
            ob_flush();
            flush();
            
            try {
                $pdoTicket = new PDO(
                    "mysql:host=" . DB_TICKET_HOST . ";dbname=" . DB_TICKET_NAME . ";charset=utf8mb4",
                    DB_TICKET_USER,
                    DB_TICKET_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_persian_ci"
                    ]
                );
                $ticketResult = checkAndMigrateDatabase($pdoTicket, DB_TICKET_NAME);
                ob_flush();
                flush();
                if ($ticketResult) {
                    echo '<div class="alert alert-success">';
                    echo '✅ Migration دیتابیس تیکت با موفقیت انجام شد';
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-warning">';
                    echo 'ℹ️ Migration دیتابیس تیکت انجام نشد (احتمالاً جداول از قبل وجود دارند)';
                    echo '</div>';
                }

                $ticketTables = $pdoTicket->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                echo '<div class="alert alert-info">';
                echo '📊 تعداد جداول در دیتابیس تیکت: <strong>' . count($ticketTables) . '</strong><br>';
                if (!empty($ticketTables)) {
                    echo '<details><summary>لیست جداول</summary><ul>';
                    foreach ($ticketTables as $table) {
                        echo '<li><code>' . htmlspecialchars($table) . '</code></li>';
                    }
                    echo '</ul></details>';
                }
                echo '</div>';
            } catch (PDOException $e) {
                echo '<div class="alert alert-warning">';
                echo '⚠️ خطا در اتصال یا اجرای Migration دیتابیس تیکت: ' . htmlspecialchars($e->getMessage());
                echo '</div>';
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">';
                echo '❌ خطای عمومی در Migration دیتابیس تیکت: ' . htmlspecialchars($e->getMessage());
                echo '</div>';
            }
            
            ob_end_flush();

            // نمایش لاگ migration
            echo '<h3>لاگ Migration</h3>';
            $logFile = __DIR__ . '/migration_log.txt';
            if (file_exists($logFile)) {
                echo '<div class="alert alert-info">';
                echo '<pre>' . htmlspecialchars(file_get_contents($logFile)) . '</pre>';
                echo '</div>';
            } else {
                echo '<div class="alert alert-warning">';
                echo 'ℹ️ هنوز لاگی ثبت نشده است';
                echo '</div>';
            }
            ?>

            <div style="margin-top: 30px; text-align: center;">
                <a href="?step=2" class="btn">← بازگشت</a>
                <a href="?step=4" class="btn btn-success">ادامه به مرحله 4 →</a>
            </div>

        <?php elseif ($step == 4): ?>
            <!-- ============================================ -->
            <!-- مرحله 4: بررسی Webhook -->
            <!-- ============================================ -->
            <h2>🌐 مرحله 4: بررسی وضعیت Webhook</h2>

            <?php
            // دریافت اطلاعات Webhook
            $webhookUrl = "https://api.telegram.org/bot" . TOKEN_POKER . "/getWebhookInfo";
            $webhookInfo = @file_get_contents($webhookUrl);
            
            if ($webhookInfo === false) {
                echo '<div class="alert alert-danger">';
                echo '❌ خطا در دریافت اطلاعات Webhook از Telegram API';
                echo '</div>';
            } else {
                $webhookData = json_decode($webhookInfo, true);
                
                if ($webhookData && $webhookData['ok']) {
                    $info = $webhookData['result'];
                    ?>
                    <table>
                        <tr>
                            <th>مشخصه</th>
                            <th>مقدار</th>
                            <th>وضعیت</th>
                        </tr>
                        <tr>
                            <td><strong>URL Webhook</strong></td>
                            <td><code><?php echo htmlspecialchars($info['url'] ?? 'تنظیم نشده'); ?></code></td>
                            <td>
                                <?php if (!empty($info['url'])): ?>
                                    <span class="success">✅ تنظیم شده</span>
                                <?php else: ?>
                                    <span class="warning">⚠️ تنظیم نشده</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>تعداد Update های Pending</strong></td>
                            <td><?php echo $info['pending_update_count'] ?? 0; ?></td>
                            <td>
                                <?php if (($info['pending_update_count'] ?? 0) > 0): ?>
                                    <span class="warning">⚠️ <?php echo $info['pending_update_count']; ?> Update در انتظار</span>
                                <?php else: ?>
                                    <span class="success">✅ هیچ Update در انتظار نیست</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>آخرین خطا (تاریخ)</strong></td>
                            <td>
                                <?php if ($info['last_error_date']): ?>
                                    <?php echo date('Y-m-d H:i:s', $info['last_error_date']); ?>
                                <?php else: ?>
                                    هیچ خطایی وجود ندارد
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($info['last_error_date']): ?>
                                    <span class="error">❌ خطا وجود دارد</span>
                                <?php else: ?>
                                    <span class="success">✅ بدون خطا</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>پیام آخرین خطا</strong></td>
                            <td>
                                <?php if ($info['last_error_message']): ?>
                                    <code><?php echo htmlspecialchars($info['last_error_message']); ?></code>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td><strong>حداکثر اتصالات همزمان</strong></td>
                            <td><?php echo $info['max_connections'] ?? 'نامشخص'; ?></td>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td><strong>گواهینامه SSL سفارشی</strong></td>
                            <td><?php echo ($info['has_custom_certificate'] ?? false) ? 'بله' : 'خیر'; ?></td>
                            <td>-</td>
                        </tr>
                    </table>

                    <?php if (!empty($info['url'])): ?>
                        <div class="alert alert-success" style="margin-top: 20px;">
                            <strong>✅ Webhook تنظیم شده است!</strong><br>
                            ربات شما آماده دریافت Update از Telegram است.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning" style="margin-top: 20px;">
                            <strong>⚠️ Webhook تنظیم نشده است!</strong><br>
                            برای تنظیم Webhook از دستور زیر استفاده کنید:<br><br>
                            <code style="display: block; padding: 10px; background: #2c3e50; color: white; border-radius: 5px;">
                                curl -X POST "https://api.telegram.org/bot<?php echo TOKEN_POKER; ?>/setWebhook?url=https://yourdomain.com/bot.php"
                            </code>
                        </div>
                    <?php endif; ?>

                    <?php if ($info['last_error_date']): ?>
                        <div class="alert alert-danger" style="margin-top: 20px;">
                            <strong>❌ خطا در Webhook:</strong><br>
                            <strong>تاریخ:</strong> <?php echo date('Y-m-d H:i:s', $info['last_error_date']); ?><br>
                            <strong>پیام:</strong> <?php echo htmlspecialchars($info['last_error_message']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (($info['pending_update_count'] ?? 0) > 0): ?>
                        <div class="alert alert-warning" style="margin-top: 20px;">
                            <strong>⚠️ <?php echo $info['pending_update_count']; ?> Update در انتظار پردازش هستند.</strong><br>
                            این ممکن است به دلیل مشکل در ربات یا سرور باشد.
                        </div>
                    <?php endif; ?>
                    <?php
                } else {
                    echo '<div class="alert alert-danger">';
                    echo '❌ خطا در دریافت اطلاعات Webhook';
                    if (isset($webhookData['description'])) {
                        echo '<br>پیام خطا: ' . htmlspecialchars($webhookData['description']);
                    }
                    echo '</div>';
                }
            }
            ?>

            <div style="margin-top: 30px; text-align: center;">
                <a href="?step=3&confirmed=yes" class="btn">← بازگشت</a>
                <a href="?step=4" class="btn btn-success">🔄 بروزرسانی اطلاعات</a>
            </div>

        <?php else: ?>
            <div class="alert alert-danger">
                <strong>❌ خطا:</strong> مرحله نامعتبر یا دسترسی غیرمجاز
            </div>
            <div style="margin-top: 30px; text-align: center;">
                <a href="?step=1" class="btn">شروع از ابتدا</a>
            </div>
        <?php endif; ?>

        <hr style="margin-top: 50px;">
        <div style="text-align: center; color: #7f8c8d; font-size: 14px;">
            <p><strong>⚠️ نکته امنیتی:</strong> بعد از اطمینان از صحت همه موارد، این فایل را حذف یا محافظت کنید.</p>
            <p>برای محافظت، می‌توانید یک فایل <code>.htaccess</code> در پوشه اصلی ایجاد کنید:</p>
            <pre style="text-align: left; display: inline-block; background: #ecf0f1; padding: 10px; border-radius: 5px;">
&lt;Files "setup.php"&gt;
    Require ip YOUR_IP_ADDRESS
&lt;/Files&gt;
</pre>
        </div>
    </div>
</body>
</html>
