<?php

// بررسی امنیتی: فقط در صورت وجود پارامتر GET (برای دسترسی مستقیم)
// در webhook این پارامتر وجود ندارد، پس این چک را فقط برای دسترسی مستقیم فعال می‌کنیم
// همچنین اگر از setup.php یا فایل دیگری require شده، اجازه می‌دهیم
if (!defined('SETUP_MODE') && !isset($GLOBALS['_SETUP_MODE'])) {
    $isIncluded = (basename($_SERVER['PHP_SELF']) !== 'bot.php');
    $input = @file_get_contents('php://input');
    $isWebhook = !empty($input);
    $isCLI = (php_sapi_name() === 'cli');
    $hasSecurityParam = isset($_GET['x0cossher']);
    
    if (!$isCLI && !$isIncluded && !$hasSecurityParam && !$isWebhook) {
        // اگر نه CLI است، نه include شده، نه پارامتر امنیتی دارد، و نه webhook update دارد، دسترسی را مسدود کن
        die('Access Denied');
    }
}
set_time_limit(0);
date_default_timezone_set('Asia/Tehran');

//---------------------------------


// ticket_bot_handler.php
// این فایل به صورت مستقل برای مدیریت پاسخ‌های ادمین و نوتیفیکیشن‌ها استفاده می‌شود

// require __DIR__.'/ticket_system/config_ticket.php';
require_once __DIR__.'/ticket_system/config_ticket.php';
require_once __DIR__.'/ticket_system/ticket_bot_handler_integrated.php';


require_once 'config.php';

// ============================================
// بخش: مقداردهی اولیه متغیرهای سراسری
// ============================================
// این متغیرها ممکن است در برخی شرایط تعریف نشوند
if (!isset($chat_type)) {
    $chat_type = null;
}
if (!isset($from_id)) {
    $from_id = null;
}
if (!isset($message)) {
    $message = null;
}
if (!isset($message_id)) {
    $message_id = null;
}
if (!isset($first_name)) {
    $first_name = null;
}

// ============================================
// بخش: بررسی و ایجاد خودکار جداول دیتابیس
// ============================================
/**
 * این تابع بررسی می‌کند که آیا جداول مورد نیاز وجود دارند یا نه
 * اگر جداول وجود نداشته باشند، فایل migration را اجرا می‌کند
 * 
 * @param PDO $pdo اتصال دیتابیس
 * @param string $dbName نام دیتابیس
 * @return bool موفقیت یا عدم موفقیت
 */
function checkAndMigrateDatabase($pdo, $dbName) {
    // بررسی اینکه $pdo null نباشد
    if ($pdo === null) {
        error_log("خطا: \$pdo null است در checkAndMigrateDatabase");
        return false;
    }
    
    try {
        // بررسی وجود جداول: برای هر دو دیتابیس جدول users (اما در دیتابیس‌های جداگانه)
        $checkTable = 'users';
        $stmt = $pdo->query("SHOW TABLES LIKE '$checkTable'");
        $tableExists = $stmt->rowCount() > 0;
        
        // اگر جدول وجود دارد، بررسی کن که آیا ستون last_spin_time وجود دارد یا نه
        if ($tableExists && $dbName !== DB_TICKET_NAME) {
            try {
                $pdo->query("SELECT last_spin_time FROM users LIMIT 1");
            } catch (PDOException $e) {
                // ستون وجود ندارد، باید اضافه شود
                try {
                    $pdo->exec("ALTER TABLE `users` ADD COLUMN `last_spin_time` INT(11) DEFAULT 0 COMMENT 'زمان آخرین چرخش گردونه شانس (timestamp)' AFTER `daily_subset`");
                    error_log("ستون last_spin_time به جدول users اضافه شد");
                } catch (PDOException $alterError) {
                    error_log("خطا در افزودن ستون last_spin_time: " . $alterError->getMessage());
                }
            }
        }
        
        if (!$tableExists) {
            // جداول وجود ندارند، باید migration اجرا شود
            $migrationFile = __DIR__ . '/database_migration.sql';
            
            if (!file_exists($migrationFile)) {
                error_log("فایل migration یافت نشد: $migrationFile");
                return false;
            }
            
            // خواندن محتوای فایل SQL
            $sql = file_get_contents($migrationFile);
            
            if ($sql === false) {
                error_log("خطا در خواندن فایل migration");
                return false;
            }
            
            // حذف دستورات CREATE DATABASE و USE (چون دیتابیس از قبل انتخاب شده)
            $sql = preg_replace('/CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS.*?;/is', '', $sql);
            $sql = preg_replace('/CREATE\s+DATABASE.*?;/is', '', $sql);
            $sql = preg_replace('/USE\s+[^;]+;/i', '', $sql);
            
            // حذف کامنت‌های SQL (خطوطی که با -- شروع می‌شوند)
            $lines = explode("\n", $sql);
            $cleanLines = [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                // نادیده گرفتن خطوط کامنت و خالی
                if (!empty($trimmed) && strpos($trimmed, '--') !== 0) {
                    $cleanLines[] = $line;
                }
            }
            $sql = implode("\n", $cleanLines);
            
            // حذف کامنت‌های چندخطی
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
            
            // تقسیم به دستورات جداگانه بر اساس semicolon
            // استفاده از regex برای تقسیم صحیح (با در نظر گیری semicolon در رشته‌ها)
            $statements = [];
            $current = '';
            $inString = false;
            $quoteChar = '';
            
            $len = strlen($sql);
            for ($i = 0; $i < $len; $i++) {
                $char = $sql[$i];
                $prevChar = $i > 0 ? $sql[$i-1] : '';
                
                // تشخیص شروع/پایان رشته
                if (($char === '"' || $char === "'") && $prevChar !== '\\') {
                    if (!$inString) {
                        $inString = true;
                        $quoteChar = $char;
                    } elseif ($char === $quoteChar) {
                        $inString = false;
                        $quoteChar = '';
                    }
                }
                
                $current .= $char;
                
                // اگر semicolon خارج از رشته دیدیم، دستور را جدا می‌کنیم
                if (!$inString && $char === ';') {
                    $stmt = trim($current);
                    if (!empty($stmt) && 
                        (stripos($stmt, 'CREATE') === 0 || 
                         stripos($stmt, 'INSERT') === 0 ||
                         stripos($stmt, 'ALTER') === 0)) {
                        $statements[] = $stmt;
                    }
                    $current = '';
                }
            }
            
            // اجرای هر دستور در یک transaction
            $pdo->beginTransaction();
            try {
                $executed = 0;
                $skipped = 0;
                foreach ($statements as $index => $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        $shouldExecute = false;
                        
                        // برای دیتابیس تیکت، فقط جداول مربوط به تیکت را اجرا کن
                        if ($dbName === DB_TICKET_NAME) {
                            // فقط جداول ticket_* و users (دیتابیس تیکت) را اجرا کن
                            if (stripos($statement, 'CREATE TABLE') !== false) {
                                // جداول ticket_* یا users (که در بخش تیکت است و user_id دارد)
                                if (stripos($statement, 'ticket_') !== false || 
                                    (stripos($statement, '`users`') !== false && stripos($statement, 'user_id') !== false)) {
                                    $shouldExecute = true;
                                }
                            } elseif (stripos($statement, 'INSERT') === 0) {
                                // INSERT ها را هم اجرا کن
                                $shouldExecute = true;
                            }
                        } else {
                            // برای دیتابیس اصلی، فقط جداول غیر تیکت را اجرا کن
                            if (stripos($statement, 'CREATE TABLE') !== false && 
                                stripos($statement, 'ticket_') === false) {
                                // اگر users است، باید id داشته باشد (نه user_id) - این users دیتابیس اصلی است
                                if (stripos($statement, '`users`') !== false) {
                                    // بررسی کن که آیا این users دیتابیس اصلی است (id دارد) یا تیکت (user_id دارد)
                                    if (stripos($statement, '`id`') !== false && stripos($statement, '`user_id`') === false) {
                                        $shouldExecute = true;
                                    }
                                } else {
                                    $shouldExecute = true;
                                }
                            } elseif (stripos($statement, 'INSERT') === 0 && 
                                     stripos($statement, 'ticket_') === false) {
                                $shouldExecute = true;
                            }
                        }
                        
                        if ($shouldExecute) {
                            try {
                                $pdo->exec($statement);
                                $executed++;
                            } catch (PDOException $execError) {
                                // اگر خطای "table already exists" باشد، نادیده بگیر
                                if (stripos($execError->getMessage(), 'already exists') === false && 
                                    stripos($execError->getMessage(), 'Duplicate') === false) {
                                    throw $execError; // خطای جدی را دوباره throw کن
                                }
                                $skipped++;
                            }
                        } else {
                            $skipped++;
                        }
                    }
                }
                $pdo->commit();
                // نوشتن لاگ در فایل برای بررسی
                $logFile = __DIR__ . '/migration_log.txt';
                $logMsg = date('Y-m-d H:i:s') . " - Migration با موفقیت اجرا شد برای دیتابیس: $dbName (اجرا شده: $executed, رد شده: $skipped)\n";
                file_put_contents($logFile, $logMsg, FILE_APPEND);
                error_log("Migration با موفقیت اجرا شد برای دیتابیس: $dbName (تعداد دستورات: $executed)");
                return true;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $logFile = __DIR__ . '/migration_log.txt';
                $errorMsg = "خطا در اجرای migration برای دیتابیس $dbName: " . $e->getMessage();
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $errorMsg\n", FILE_APPEND);
                error_log($errorMsg);
                // در setup mode، خطا را throw کن تا نمایش داده شود
                if (defined('SETUP_MODE') && SETUP_MODE) {
                    throw $e;
                }
                return false;
            }
        }
        
        // جداول از قبل وجود دارند
        return true;
    } catch (PDOException $e) {
        error_log("خطا در بررسی جداول دیتابیس $dbName: " . $e->getMessage());
        return false;
    }
}

// بررسی و migration دیتابیس اصلی
checkAndMigrateDatabase($pdo, DB_MAIN_NAME);

// بررسی و migration دیتابیس تیکت (اگر اتصال جداگانه نیاز باشد)
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
    checkAndMigrateDatabase($pdoTicket, DB_TICKET_NAME);
} catch(PDOException $e) {
    // اگر دیتابیس تیکت جداگانه نیست، خطا را نادیده می‌گیریم
    error_log("نکته: دیتابیس تیکت جداگانه نیست یا خطا در اتصال: " . $e->getMessage());
}

//-----------------------------------------------
function bot($method, $data=[]){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot'.TOKEN_POKER.'/'.$method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    return json_decode(curl_exec($ch));
}

function SM($id, $text, $reply=null, $key=null, $parse='html', $disable=true){
    return bot('sendMessage', [
        'chat_id'=>$id,
        'text'=>$text,
        'parse_mode'=>$parse,
        'disable_web_page_preview'=>$disable,
        'reply_markup'=>$key
    ]);
}
$update   = json_decode(file_get_contents('php://input'));
handle_ticket_update($update);
// ======================= START: HANDLERS FOR STARS PAYMENT =======================
// این بخش‌ها برای پردازش پرداخت با استارز اضافه شده‌اند

// STEP 1: Handle Pre-Checkout Query
// تلگرام قبل از نهایی کردن پرداخت، یک درخواست برای تایید به ربات ارسال می‌کند
if (isset($update->pre_checkout_query)) {
    $query_id = $update->pre_checkout_query->id;
    $payload = $update->pre_checkout_query->invoice_payload;

    // بررسی می‌کنیم که این درخواست برای خرید سورس ستاره‌ای است
    if (strpos($payload, 'buy_stars_') !== false) {
        bot("answerPreCheckoutQuery", [
            "pre_checkout_query_id" => $query_id,
            "ok" => true
        ]);
    } else {
        bot("answerPreCheckoutQuery", [
            "pre_checkout_query_id" => $query_id,
            "ok" => false,
            "error_message" => "متاسفانه مشکلی در پرداخت پیش آمده است."
        ]);
    }
    exit; // مهم: بعد از پاسخ به این کوئری، اسکریپت باید متوقف شود
}
// STEP 2: Handle Successful Payment
// این بخش در پیام عادی می‌آید و پس از پرداخت موفق اجرا می‌شود
if (isset($update->message->successful_payment)) {
    $payment = $update->message->successful_payment;
    $payload = $payment->invoice_payload;

    if (strpos($payload, 'buy_stars_') !== false) {
        $from_id_payment = $update->message->from->id;
        $first_name_payment = $update->message->from->first_name;
        $file_id = str_replace('buy_stars_', '', $payload);
        $file = $pdo->query("SELECT * FROM files WHERE id = '$file_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            // افزایش شمارنده دانلود یا خرید
            $dc = $file['down_count'] + 1;
            $pdo->exec("UPDATE files SET down_count = '$dc' WHERE id = '$file_id' LIMIT 1");
            $pdo->exec("INSERT INTO download (user_id, file_id) VALUES ('$from_id_payment', '$file_id')");
            
            SM($from_id_payment, "✅ پرداخت شما با موفقیت انجام شد. از خرید شما سپاسگزاریم!");
            bot('sendDocument', [
                'chat_id'  => $from_id_payment,
                'document' => $file['file_id'],
                'caption'  => '✅ این هم فایل شما: ' . $file['title']
            ]);
            
            // لاگ برای ادمین
             $settings_for_log = $pdo->query("SELECT * FROM panel WHERE id = '85' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            sm($channel['ch_logs'], "کاربر <a href='tg://user?id=$from_id_payment'>$first_name_payment</a> | <a href='t.me/".str_replace('@', '', $brand_username)."/$file_id'>{$file['title']}</a> را با {$payment->total_amount} ستاره خریداری کرد.");
            
            // ارسال گزارش کامل پرداخت به کانال لاگ
            $payment_details = json_encode($payment, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            sm($channel['ch_logs'], "
جزئیات کامل پرداخت (Stars):
<pre>" . htmlspecialchars($payment_details) . "</pre>
            ");
        }
    }
    exit; // مهم: بعد از ارسال فایل، اسکریپت متوقف شود
}




// --- MESSAGE REACTION HANDLER (NEW FEATURE) ---
// [FIX] این بلوک به اینجا منتقل شد تا قبل از کدهای دیگر اجرا شود و از خطا جلوگیری کند.
if (isset($update->message_reaction)) {
    $reaction = $update->message_reaction;
    $user_id = $reaction->user->id;
    $chat = $reaction->chat;

    // 1. بررسی اینکه ری‌اکشن فقط در کانال مشخص شده کار کند
    $target_channel_username = str_replace('@', '', $brand_username);
    if ($chat->type !== 'channel' || ($chat->username ?? '') !== $target_channel_username) {
        exit;
    }

    // 2. بررسی اینکه کاربر عضو ربات است
    $stmt_check_user = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $stmt_check_user->execute([$user_id]);
    if ($stmt_check_user->fetchColumn() === false) {
        exit;
    }

    $message_id = $reaction->message_id;
    $new_reaction_emoji = !empty($reaction->new_reaction) ? $reaction->new_reaction[0]->emoji : null;
    $old_reaction_emoji = !empty($reaction->old_reaction) ? $reaction->old_reaction[0]->emoji : null;

    $silver_amount = 30;

    try {
        // حالت ۱: کاربر ری‌اکشن جدید اضافه کرده
        if ($new_reaction_emoji && !$old_reaction_emoji) {
            $stmt_check = $pdo->prepare("SELECT id FROM reactions WHERE user_id = ? AND message_id = ?");
            $stmt_check->execute([$user_id, $message_id]);
            if ($stmt_check->fetchColumn() === false) {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO reactions (user_id, message_id, emoji, created_at) VALUES (?, ?, ?, NOW())")->execute([$user_id, $message_id, $new_reaction_emoji]);
                $pdo->prepare("UPDATE users SET silver = silver + ? WHERE id = ?")->execute([$silver_amount, $user_id]);
                $pdo->commit();
                SM($user_id, "✅ ری‌اکشن شما ثبت شد و $silver_amount نقره به موجودی شما اضافه شد.");
            }
        }
        // حالت ۲: کاربر ری‌اکشن خود را حذف کرده
        elseif (!$new_reaction_emoji && $old_reaction_emoji) {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM reactions WHERE user_id = ? AND message_id = ?")->execute([$user_id, $message_id]);
            $pdo->prepare("UPDATE users SET silver = GREATEST(0, silver - ?) WHERE id = ?")->execute([$silver_amount, $user_id]);
            $pdo->commit();
            SM($user_id, "❌ ری‌اکشن شما حذف شد و $silver_amount نقره از موجودی شما کم شد.");
        }
        // حالت ۳: کاربر ری‌اکشن خود را تغییر داده
        elseif ($new_reaction_emoji && $old_reaction_emoji && $new_reaction_emoji !== $old_reaction_emoji) {
            $pdo->prepare("UPDATE reactions SET emoji = ? WHERE user_id = ? AND message_id = ?")->execute([$new_reaction_emoji, $user_id, $message_id]);
            SM($user_id, "🔄 ری‌اکشن شما تغییر کرد. موجودی نقره شما بدون تغییر باقی ماند.");
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        file_put_contents('reaction_error.log', $e->getMessage(), FILE_APPEND);
    }
    
    exit;
}


// ======================= END: HANDLERS FOR STARS PAYMENT =======================

// ======================= END: HANDLERS FOR STARS PAYMENT =======================

$hack     = $update->message->text;
$date= ("Y/m/d");
$inline_query    = $update->inline_query;
$inline_from_id  = $inline_query->from->id;
$inline_fn       = $inline_query->from->first_name;
$inline_query_id = $inline_query->id;
$inline_text     = $inline_query->query;
$inline_chat_type= $inline_query->chat_type;

$bot_name = bot('GetMe')->result->first_name;
$bot_user = bot('GetMe')->result->username;
$timering = time();
$settings = $pdo->query("SELECT * FROM panel WHERE id = '85' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$Devs = array_merge(explode('^', $settings['admins']));


// STEP 1: Handle Channel Member Updates First (if any)
// This is the most critical part. It now runs without stopping the rest of the script.
if (isset($update->chat_member)) {
    $chat_member = $update->chat_member;
    $channel_id = $chat_member->chat->id;

    if ($channel_id == ZM_CHANNEL_ID) {
        if (!isset($pdo) || !$pdo instanceof PDO) {
            bot('sendMessage', ['chat_id' => 1604140942, 'text' => 'FATAL ERROR: $pdo object not found in chat_member handler.']);
        } else {
            $joiner_id = $chat_member->new_chat_member->user->id;
            $new_status = $chat_member->new_chat_member->status;
            $old_status = $chat_member->old_chat_member->status;

            // A new user has joined
            if (in_array($new_status, ['member', 'administrator']) && !in_array($old_status, ['member', 'administrator', 'creator'])) {
                if (isset($chat_member->invite_link) && !empty($chat_member->invite_link->invite_link)) {
                    $invite_link_str = $chat_member->invite_link->invite_link;
                    $stmt = $pdo->prepare("SELECT user_id FROM zm_invites WHERE invite_link = ? LIMIT 1");
                    $stmt->execute([$invite_link_str]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($result) {
                        $inviter_id = $result['user_id'];
                        
                        $stmt_join = $pdo->prepare("INSERT INTO zm_joins (joiner_id, inviter_id, channel_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE inviter_id = ?");
                        $stmt_join->execute([$joiner_id, $inviter_id, $channel_id, $inviter_id]);

                        $stmt_update = $pdo->prepare("UPDATE zm_invites SET current_members = current_members + 1 WHERE user_id = ? AND status = 'pending'");
                        $stmt_update->execute([$inviter_id]);
                        
                        $progress_stmt = $pdo->prepare("SELECT * FROM zm_invites WHERE user_id = ? AND status = 'pending'");
                        $progress_stmt->execute([$inviter_id]);
                        $all_pending_invites = $progress_stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach($all_pending_invites as $invite_progress) {
                            $current_count = $invite_progress['current_members'];
                            $required_count = $invite_progress['required_members'];
                            $remaining = $required_count - $current_count;
                            
                            if ($remaining >= 0) {
                                $message_to_inviter = "✅ یک نفر با لینک شما عضو شد!\n\n" . "وضعیت فعلی برای سورس #{$invite_progress['file_id']}: {$current_count} از {$required_count} نفر.";
                                if($remaining > 0) {
                                   $message_to_inviter .= "\n$remaining نفر دیگر باقی مانده است.";
                                }
                                SM($inviter_id, $message_to_inviter);
                            }
                        }

                        $completed_stmt = $pdo->prepare("SELECT * FROM zm_invites WHERE user_id = ? AND current_members >= required_members AND status = 'pending'");
                        $completed_stmt->execute([$inviter_id]);
                        $completed_invites = $completed_stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($completed_invites as $invite) {
                            $pdo->exec("UPDATE zm_invites SET status = 'completed' WHERE id = '{$invite['id']}'");
                            $file = $pdo->query("SELECT * FROM files WHERE id = '{$invite['file_id']}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                            if ($file) {
                                bot('sendDocument', ['chat_id' => $inviter_id, 'document' => $file['file_id'], 'caption' => '✅ تبریک! شما با موفقیت '.$invite['required_members'].' نفر را به کانال دعوت کردید و سورس زیر برای شما باز شد:'."\n\n".'📂 '.$file['title']]);
                                SM($inviter_id, "از اینکه در این چالش شرکت کردید سپاسگزاریم!", null, $menu);
                            }
                        }
                    } else {
                        // bot('sendMessage', ['chat_id' => 1604140942, 'text' => "DEBUG: Inviter NOT FOUND in DB for link: $invite_link_str"]);
                    }
                } else {
                    // bot('sendMessage', ['chat_id' => 1604140942, 'text' => 'DEBUG: Invite link was NOT FOUND in the update object. This usually happens on a re-join. Cannot attribute this member.']);
                }
            }
            // A user has left
            elseif (in_array($new_status, ['left', 'kicked'])) {
                 $stmt = $pdo->prepare("SELECT inviter_id FROM zm_joins WHERE joiner_id = ? AND channel_id = ?");
                 $stmt->execute([$joiner_id, $channel_id]);
                 $join_info = $stmt->fetch(PDO::FETCH_ASSOC);

                 if ($join_info) {
                     $inviter_id = $join_info['inviter_id'];
                     $pdo->exec("UPDATE users SET zm_penalty_count = zm_penalty_count + 2 WHERE id = '$inviter_id'");
                     $pdo->exec("UPDATE zm_invites SET current_members = GREATEST(0, current_members - 1) WHERE user_id = '$inviter_id' AND status = 'pending'");
                     $pdo->exec("DELETE FROM zm_joins WHERE joiner_id = '$joiner_id' AND channel_id = '$channel_id'");
                     SM($inviter_id, "❗️ یکی از کاربرانی که دعوت کرده بودید کانال را ترک کرد. به عنوان جریمه، شما از دریافت 2 سورس رایگان بعدی محروم شدید.");
                 }
            }
        }
    }
}
// --- END: ZM Feature ---
if(isset($update->message)){
    $message = $update->message->text;
    
    $from_id = $update->message->from->id;
    
    $first_name = $update->message->from->first_name;
    $message_id = $update->message->message_id;
    $chat_type = $update->message->chat->type;
    $users = $pdo->query("SELECT * FROM users WHERE id = '$from_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $admins = $pdo->query("SELECT * FROM Admins WHERE id = '$from_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $ChannelLock_One=bot('getChatMember', ['chat_id'=>$channel['id'][0], 'user_id'=>$from_id])->result->status;
    $ChannelLock_Two=bot('getChatMember', ['chat_id'=>$channel['id'][1], 'user_id'=>$from_id])->result->status;
} elseif(isset($update->callback_query)){
    $message = $update->callback_query->data;
    $from_id = $update->callback_query->from->id;
    $first_name = $update->callback_query->from->first_name;
    $chat_type = $update->callback_query->message->chat->type;
    $message_id = $update->callback_query->message->message_id;
    $chat_type = $update->callback_query->message->chat->type;
    $users = $pdo->query("SELECT * FROM users WHERE id = '$from_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $admins = $pdo->query("SELECT * FROM Admins WHERE id = '$from_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $ChannelLock_One=bot('getChatMember', ['chat_id'=>$channel['id'][0], 'user_id'=>$from_id])->result->status;
    $ChannelLock_Two=bot('getChatMember', ['chat_id'=>$channel['id'][1], 'user_id'=>$from_id])->result->status;
}
if($from_id!=1604140942) {$message = str_replace(['"',"'",';','#'],null,$message);}
#---------------------------------------------------


// It's assumed $pdo is your database connection object.
// It's also assumed that variables like $inline_query_id, $inline_text, $inline_fn,
// $inline_from_id, and $channel are available from your bot's framework.
// <?php

// Assume $pdo, $inline_query_id, etc., are available.

// --- Cache Configuration ---
define('CACHE_ENABLED', true);
define('CACHE_DIR', __DIR__ . '/cache/'); // Create a 'cache' directory next to your script
define('CACHE_TIME', 300); // Cache duration in seconds (5 minutes)

// Create cache directory if it doesn't exist
if (CACHE_ENABLED && !is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR);
}


if (!empty($inline_text)) {
    $results = [];
    $cache_file = CACHE_DIR . md5($inline_text) . '.cache';

    if (CACHE_ENABLED && file_exists($cache_file) && (time() - filemtime($cache_file)) < CACHE_TIME) {
        $results = unserialize(file_get_contents($cache_file));
    } else {
        $search_term = '%' . $inline_text . '%';
        $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? OR title LIKE ? LIMIT 20");
        $stmt->execute([$inline_text, $search_term]);
        $found_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($found_files) {
            foreach ($found_files as $file_data) {
                // START: NEW LOGIC FOR INLINE KEYBOARD URL
                $url_prefix = '';
                switch ($file_data['ads_type']) {
                    case 'free':
                    case 'coin':
                        $url_prefix = 'file_';
                        break;
                    case 'vip':
                        $url_prefix = 'buy_';
                        break;
                    case 'zm':
                        $url_prefix = 'zm_';
                        break;
                    case 'stars':
                        $url_prefix = 'stars_';
                        break;
                }
                // END: NEW LOGIC FOR INLINE KEYBOARD URL

                $message_text = "🤚 کاربر [$inline_fn](tg://user?id=$inline_from_id) برای شما سورس \n« *" . htmlspecialchars($file_data['title']) . "* » \n را به اشتراک گذاشته است!\n\n"
                              . "👇 برای مشاهده و دانلود سورس از دکمه زیر استفاده کنید 👇";

                $results[] = [
                    'type' => "article",
                    'id' => $file_data['id'],
                    'title' => htmlspecialchars($file_data['title']),
                    'description' => "✅ آیدی: " . htmlspecialchars($file_data['id']) . "\nبرای اشتراک‌گذاری کلیک کنید.",
                    'thumb_url' => $channel['domin'] . "/data/banner.jpg",
                    'input_message_content' => [
                        'parse_mode' => 'MarkDown',
                        'message_text' => $message_text
                    ],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => '📥 ' . $file_data['down_count'], 'callback_data' => "QueryShow"],
                                ['text' => '❤️ ' . $file_data['like_count'], 'callback_data' => "QueryShow"],
                                ['text' => '💬 ' . $file_data['lang'], 'callback_data' => "QueryShow"]
                            ],
                            [['text' => "👈 دانلود سورس", 'url' => "https://telegram.me/$bot_user?start={$url_prefix}{$file_data['id']}"]],
                            [['text' => "👈 دیدن سورس در کانال", 'url' => "https://t.me/".str_replace('@', '', $brand_username)."/{$file_data['id']}"]]
                        ]
                    ]
                ];
            }
        } else {
            $results[] = [
                'type' => "article",
                'id' => 'not_found_404',
                'title' => "❌ نتیجه‌ای یافت نشد",
                'description' => "هیچ سورسی با عبارت '" . htmlspecialchars($inline_text) . "' مطابقت ندارد.",
                'input_message_content' => [
                    'message_text' => "عبارت وارد شده نتیجه‌ای در بر نداشت."
                ]
            ];
        }

        if (CACHE_ENABLED) {
            file_put_contents($cache_file, serialize($results));
        }
    }

    bot('answerInlineQuery', [
        'inline_query_id' => $inline_query_id,
        'results' => json_encode($results),
        'cache_time' => 10
    ]);

} else {
    bot('answerInlineQuery', [
        'inline_query_id' => $inline_query_id,
        'results' => json_encode([]),
        'switch_pm_text' => 'آیدی یا بخشی از عنوان سورس را وارد کنید...',
        'switch_pm_parameter' => 'inline_help'
    ]);
}
if($message=='QueryShow'){
    bot('answerCallbackQuery', [
        'callback_query_id'=> $update->callback_query->id,
        'text' => '🥋 این دکمه نمایشی است و کاربرد دیگری ندارد!',
        'show_alert' =>true
    ]);

}

#---------------------------------------------------
if(!in_array($from_id,$Devs)){
    if($chat_type =="private"){
    if(strpos($hack,"'") !== false or strpos($hack,'"') !== false or strpos($hack,'#') !== false or strpos($hack,",") !== false or strpos($hack,"}") !== false or strpos($hack,";") !== false or strpos($text,"{") !== false ){
        $pdo->exec("UPDATE users SET step = 'none' WHERE id = '$from_id' LIMIT 1");
        bot('sendMessage',[
            'chat_id'=>1604140942,
            'text'=>"
مدیریت گرامی 🌹
❌ ربات یک عامل نفوذی را شناسایی کرده است !
👇🏻 اطلاعات فرد 👇🏻
👤 نام : $first_name
[🗣 نمایش پروفایل](tg://user?id=$from_id)

🆔 آیدی عددی فرد : $from_id
🚫 کد استفاده شده : 🚫
[   $hack   ]
",
            'parse_mode'=>"MarkDown",
        ]);
        $pdo->exec("UPDATE users SET block = '2' WHERE id = '$from_id' LIMIT 1");
        bot('sendmessage',[
            'chat_id'=>$from_id,
            'text'=>"❌ هشدار ❌

❌ شما هنگام استفاده از عبارات ممنوعه شناسایی ، گزارش و تعلیق شدید.

👇 جهت رفع تعلیق دکمه زیر را بزنید 👇",
            'reply_markup'=>json_encode(['inline_keyboard'=>[
                [['text'=>"🔰 درخواست رفع تعلیق",'callback_data'=>"reqsus|".time()]]

            ]
            ])
        ]);
        exit ();
    }
}}
if(strpos($message,"reqsus|") !==false){
    if($users['block'] == 2){
        $time_rep = date("Y/m/d H:i:s",str_replace("reqsus|","",$message));

        bot('answercallbackquery',[
            'callback_query_id'=>$update->callback_query->id,
            'text'=>"✅ صبر کنید ...",
            'show_alert'=>false
        ]);
        for($i=0; $i< count($Devs); $i++){

            bot('sendmessage',[
                'chat_id'=>$Devs[$i],
                'text'=>"✅ مدیر گرامی [این کاربر](tg://user?id=$from_id) درخواست رفع تعلیق خود را دارد.

👇 از طریق گزینه های زیر به این درخواست رسیدگی کنید 👇",
                'parse_mode'=>"MarkDown",
                'reply_markup'=>json_encode(['inline_keyboard'=>[
                    [['text'=>'✅ رفع تعلیق','callback_data'=>"unsus|$from_id"],['text'=>'❌ عدم رفع','callback_data'=>"bansus|$from_id"]]

                ]
                ])
            ]);}

        bot('editmessagetext',[
            'chat_id'=>$from_id,
            'message_id'=>$message_id,
            'text'=>"🤚 کاربر گرامی :

👈 درخواست رفع تعلیق شما ارسال گردید.
👈 لطفا منتظر تایید مدیریت بمانید ، ربات شما را مطلع خواهد کرد.

👈 زمان تعلیق شما $time_rep."
        ]);
        exit();
    }else{
        bot('answercallbackquery',[
            'chat_id'=>$from_id,
            'callback_query_id'=>$update->callback_query->id,
            'text'=>"❌ شما تعلیق نیستید!",
            'show_alert'=>true
        ]);
    }
}
#-----------------------------------------------#
if(strpos($message,"unsus|") !==false and in_array($from_id,$Devs)){
    $USER    = str_replace("unsus|","",$message);
    $blocker = $pdo->query("SELECT * FROM users WHERE id = '$USER' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
//  sm($from_id,$USER);
    if($blocker['block']==2){
        $pdo->exec("UPDATE `users` SET `block`=0 WHERE `id`=$USER");

        SM($USER,"❌: هشدار :❌

⛔️ در صورت تکرار این امر حساب شما به طور دائم مسدود خواهد شد.",null,$menu);
        SM($USER,"✅ کاربر عزیز با درخواست رفع تعلیق شما موافقت شد است!

👈 ربات را /start کنید.",null,$menu);


        bot('editmessagetext',[
            'chat_id'=>$from_id,
            'message_id'=>$message_id,
            'text'=>"✅ [این کاربر](tg://user?id=$USER) با موفقیت از حالت تعلیقی خارج گردید!",
            'parse_mode'=>"MARKDOWN",
        ]);
    }else{
        bot('answercallbackquery',[
            'chat_id'=>$from_id,
            'callback_query_id'=>$update->callback_query->id,
            'text'=>"✅ این کاربر تعلیق نمیباشد.!",
            'show_alert'=>true
        ]);

    }
}
if(strpos($message,"bansus|") !==false and in_array($from_id,$Devs)){
    $USER    = str_replace("bansus|","",$message);
    $blocker = $pdo->query("SELECT * FROM users WHERE id = '$USER' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
//  sm($from_id,$USER);
    if($blocker['block']==2){

        SM($USER,"❌ با درخواست رفع تعلیقی شما موافقت نگردید!

👈 شما همچنان تعلیق هستید و مسدود نشده اید برای رفع تعلیق خود می توانید با پشتیبانی رسمی @PEJVAK_SUPPORT  ارتباط برقرار نمایید.",null,$menu);

        $err = bot('editmessagetext',[
            'chat_id'=>$from_id,
            'message_id'=>$message_id,
            'text'=>"❌ [این کاربر](tg://user?id=$USER) با درخواست رفع تعلیقی اش موافقت نشد!",
            'parse_mode'=>"MARKDOWN",
        ]);

    }else{
        bot('answercallbackquery',[
            'chat_id'=>$from_id,
            'callback_query_id'=>$update->callback_query->id,
            'text'=>"✅ این کاربر تعلیق نمیباشد.!",
            'show_alert'=>true
        ]);

    }
}


#-----------------------------------------------#
if($chat_type=='private'){
    if($users['block']==2 and !in_array($from_id, $Devs)){
        bot('sendmessage',[
            'chat_id'=>$from_id,
            'text'=>"❌ هشدار ❌

❌ شما هنگام استفاده از عبارات ممنوعه شناسایی ، گزارش و تعلیق شدید.

👇 جهت رفع تعلیق دکمه زیر را بزنید 👇",
            'reply_markup'=>json_encode(['inline_keyboard'=>[
                [['text'=>"🔰 درخواست رفع تعلیق",'callback_data'=>"reqsus|".time()]]

            ]
            ])
        ]);
        exit ();
    }}

if($chat_type=='private'){
    if($users['block']==1 and !in_array($from_id, $Devs)){
        $pdo = null;  exit();
    }
    if($users['flood'] < time()){
        $flood= time()+0.3;
        $pdo->exec("UPDATE users SET flood = '$flood' WHERE id = '$from_id' LIMIT 1");
    } else {
        $pdo = null;  exit();
    }
    $pay_1 = number_format($channel['tariff'] * 10);
    $pay_2 = number_format($channel['tariff'] * 25);
    $pay_3 = number_format($channel['tariff'] * 40);
    $paykey = json_encode(['inline_keyboard'=>[
        // [['text'=>"🎁🔥 ویژه ماه رمضان 129 سکه فقط 39,900 تومان 🔥🎁",'callback_data'=>'pay_event']],
        [['text'=>"🌟 10 سکه |  $pay_1 ریال",'callback_data'=>'pay_1']],
        [['text'=>"🌟 25 سکه | $pay_2 ریال",'callback_data'=>'pay_2']],
        [['text'=>"🌟 40 سکه | $pay_3 ریال",'callback_data'=>'pay_3']],
        [['text'=>'✨ سکه دلخواه (ورود تعداد دلخواه)','callback_data'=>'pay_select']],

    ]]);
    $toper_key = json_encode(['inline_keyboard'=>[
        [['text'=>"📊 دیدن 10 سورس اخیر ",'callback_data'=>"topterin_10"]],
        [['text'=>"📈 دیدن 5 سورس اخیر",'callback_data'=>"topterin_5"]]
    ]]);
 $pejvak_club = json_encode([
    'keyboard' => [
        [['text' => "❓ چگونه نقره بگیریم؟"]],
        [['text' => "💎 انتقال سکه"], ['text' => "📈 نقره‌های من"]],
        [['text' => "🔄 تبدیل نقره به سکه"]],
        [['text' => "بازگشت ↪️"]]
    ],
    'resize_keyboard' => true,
    'input_field_placeholder' => "پژواک پلاس ⭐"
]);
    $confirm_key = json_encode(['keyboard'=>[
        [['text'=>"بلـــــــی"]],
        [['text'=>'بازگشت ↪️']]
    ],'resize_keyboard'=>true]);

    $menu = json_encode(['keyboard'=>[
        
        [['text'=>'گردونه شانس 🎡']],
        [['text'=>'برترین سورس ها 📊'], ['text'=>'🗳 جدید ترین ها']],
        [['text'=>'برترین ها 🌟'],['text'=>'🗂 ارسال سورس']],
        [['text'=>'حساب من 👤'],['text'=>'💰 افزایش سکه']],
        [['text'=>"پژواک پلاس ➕"]],
        [['text'=>'پشتیبانی 🆘'],['text'=>'جستـجو 🔍'],['text'=>'📚 راهنـما']],
    ],'resize_keyboard'=>true,'input_field_placeholder'=>'👇 گزینه ها👇']);

    $key_srch = json_encode(['keyboard'=>[
        [['text'=>'‍🔥 جستجو با نام سورس'],['text'=>'🖌 جستجو با آیدی سورس']],
        [['text'=>'بازگشت ↪️']]
    ],'resize_keyboard'=>true,'input_field_placeholder'=>'👈 کدوم؟']);

    $key_best = json_encode(['keyboard'=>[
        [['text'=>'ویژه ها ⚜️'],['text'=>'💣 پرفروش ترین ها']],
        [['text'=>'بیشترین دانلود 👍'], ['text'=>'❤️ محبوب ترین']],
        [['text'=>'بازگشت ↪️']]
    ],'resize_keyboard'=>true,'input_field_placeholder'=>'👈 کدوم؟']);

    $key_coin = json_encode(['keyboard'=>[
        [['text'=>'زیرمجموعه گیری👥'], ['text'=>'خرید سکه 💸']],
        [['text'=>'بازگشت ↪️']]
    ],'resize_keyboard'=>true,'input_field_placeholder'=>"😁 سکه بخر"]);

    $back = json_encode(['keyboard'=>[
        [['text'=>'بازگشت ↪️']]
    ],'resize_keyboard'=>true]);

    $request = json_encode(['keyboard'=>[
        [['text'=>'تایید هویت 🔑','request_contact'=>true]],
        [['text'=>'بازگشت ↪️']]
    ],'resize_keyboard'=>true,'input_field_placeholder'=>"🙂 شماره خودتون"]);

    $back2 = json_encode(['keyboard'=>[
        [['text'=>'بازگشت 🔙']]
    ],'resize_keyboard'=>true]);

    $remove = json_encode(['KeyboardRemove'=>[
    ],'remove_keyboard'=>true]);
    $panel = json_encode(['keyboard'=>[
        [['text'=>'آمار 📈']],
           [['text'=>'مدیریت گردونه 🎡']], 
        [['text'=>'📍 حذف سورس'], ['text'=>'📍 ارسال سورس']],
        [['text'=>'🧰 مدیریت سورس'],['text'=>"👁‍🗨 ارسال گروهی سورس"]],
        [['text'=>"📮 اطلاعات کاربر"],['text'=>"💾 سورس به کاربر"]],
        [['text'=>'فوروارد 📤'],['text'=>'ارسال 📩']],
        [['text'=>'اهدا سکه 🌀'],['text'=>'ℹ️ کسر سکه']],
        [['text'=>'بلاک کردن ⚠️'],['text'=>'آنبلاک کردن 🌀']],
        [['text'=>'⚡️ جریمه کاربر متخلف']],
        [['text'=>"🗣 افزودن همکار"]],
        [['text'=>'افزودن مدیر 👨‍💻'],['text'=>'حذف مدیر 👨‍💻']],
        [['text'=>'تنظیمات ⚙️'], ['text'=>'سکه همگانی ⛓']],
        [['text'=>'بازگشت ↪️']]
    ],'resize_keyboard'=>true]);
    $partners = json_encode(['keyboard'=>[
        [['text'=>'آمار 📈']],
        [['text'=>'📍 ارسال سورس']],
        [['text'=>'بلاک کردن ⚠️'],['text'=>'آنبلاک کردن 🌀']],
        [['text'=>'تنظیمات ⚙'],['text'=>'بازگشت ↪️']],

    ],'resize_keyboard'=>true
    ]);
    $managment = json_encode(['keyboard'=>[
        [['text'=>'🔑 کلید پاور ['.str_replace([0,1],['OFF','ON'],$settings['power']).']']],
        [['text'=>'🪝 پاکسازی تعلیق ها']],
        [['text'=>"بازگشت 🔙"]]
    ],'resize_keyboard'=>true]);
    $topser_menu = json_encode(['keyboard'=>[
        [['text'=>"👮‍♀️ قانون اصلی"]],
        [['text'=>"بازگشت ↪️"]],


    ],'resize_keyboard'=>true]);

    if($settings['power']==0 and !in_array($from_id, $Devs)){
        SM($from_id, 'ربات خاموش میباشد 😴'."\n".'چند دقیقه بعد دوباره امتحان کنید ⏰', $message_id);
        $pdo = null;  exit();
    }
//---------------------------------------------------------------------------
// if (isset($update->chat_member)) {
//     $chat_member = $update->chat_member;
//     $channel_id = $chat_member->chat->id;

//     // فقط آپدیت‌های کانال عضوگیری را پردازش کن
//     if ($channel_id == ZM_CHANNEL_ID) {
//         $joiner_id = $chat_member->new_chat_member->user->id;
//         $new_status = $chat_member->new_chat_member->status;
//         $old_status = $chat_member->old_chat_member->status;

//         // 1. کاربر جدیدی عضو شده است
//         if (($new_status == 'member' || $new_status == 'administrator') && $old_status != 'member' && $old_status != 'administrator') {
//             // بررسی اینکه آیا از طریق لینک دعوت عضو شده
//             if (isset($chat_member->invite_link)) {
//                 $invite_link = $chat_member->invite_link->invite_link;
//                 $inviter_id = $chat_member->invite_link->creator->id;

//                 // ثبت عضویت برای اعمال جریمه در آینده
//                 $stmt = $pdo->prepare("INSERT INTO zm_joins (joiner_id, inviter_id, channel_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE inviter_id = ?");
//                 $stmt->execute([$joiner_id, $inviter_id, $channel_id, $inviter_id]);

//                 // آپدیت شمارنده کاربر دعوت کننده
//                 $stmt = $pdo->prepare("UPDATE zm_invites SET current_members = current_members + 1 WHERE user_id = ? AND status = 'pending'");
//                 $stmt->execute([$inviter_id]);

//                 // بررسی اینکه آیا تعداد مورد نیاز تکمیل شده است
//                 $stmt = $pdo->prepare("SELECT * FROM zm_invites WHERE user_id = ? AND current_members >= required_members AND status = 'pending'");
//                 $stmt->execute([$inviter_id]);
//                 $completed_invites = $stmt->fetchAll(PDO::FETCH_ASSOC);

//                 foreach ($completed_invites as $invite) {
//                     // آپدیت وضعیت به تکمیل شده
//                     $pdo->exec("UPDATE zm_invites SET status = 'completed' WHERE id = '{$invite['id']}'");

//                     // ارسال فایل به کاربر
//                     $file = $pdo->query("SELECT * FROM files WHERE id = '{$invite['file_id']}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
//                     if ($file) {
//                         bot('sendDocument', [
//                             'chat_id' => $inviter_id,
//                             'document' => $file['file_id'],
//                             'caption' => '✅ تبریک! شما با موفقیت '.$invite['required_members'].' نفر را به کانال دعوت کردید و سورس زیر برای شما باز شد:'."\n\n".'📂 '.$file['title'],
//                         ]);
//                         SM($inviter_id, "از اینکه در این چالش شرکت کردید سپاسگزاریم!", null, $menu);
//                     }
//                 }
//             }
//         }
//         // 2. کاربری از کانال خارج شده است
//         elseif ($new_status == 'left' || $new_status == 'kicked') {
//             // پیدا کردن اینکه چه کسی این کاربر را دعوت کرده بود
//             $stmt = $pdo->prepare("SELECT inviter_id FROM zm_joins WHERE joiner_id = ? AND channel_id = ?");
//             $stmt->execute([$joiner_id, $channel_id]);
//             $join_info = $stmt->fetch(PDO::FETCH_ASSOC);

//             if ($join_info) {
//                 $inviter_id = $join_info['inviter_id'];

//                 // اعمال جریمه
//                 $pdo->exec("UPDATE users SET zm_penalty_count = zm_penalty_count + 2 WHERE id = '$inviter_id'");
//                 // کم کردن شمارنده (اختیاری)
//                 $pdo->exec("UPDATE zm_invites SET current_members = current_members - 1 WHERE user_id = '$inviter_id' AND status = 'pending'");

//                 // حذف رکورد عضویت
//                 $pdo->exec("DELETE FROM zm_joins WHERE joiner_id = '$joiner_id' AND channel_id = '$channel_id'");

//                 // اطلاع رسانی به کاربر جریمه شده
//                 SM($inviter_id, "❗️ یکی از کاربرانی که دعوت کرده بودید کانال را ترک کرد. به عنوان جریمه، شما از دریافت 2 سورس رایگان بعدی محروم شدید.");
//             }
//         }
//     }
//     // پردازش آپدیت عضویت تمام شد، از ادامه اجرای اسکریپت جلوگیری کن
//     exit();
// }
//---------------------------------------------------------------------------
// ======================= START: HANDLER FOR `start stars_` =======================
// این بلاک برای مدیریت درخواست خرید با استارز اضافه شده است
elseif(preg_match('/^[\/\!\#\.]?start stars_(.*)/',$message,$match)){
    if(isset($update->callback_query->data)) bot('deletemessage',['chat_id'=>$from_id,'message_id'=>$message_id]);
    $id = $match[1];
    
    // چک کردن عضویت در کانال‌ها
    if($ChannelLock_One=='left' or $ChannelLock_Two=='left'){
        SM($from_id, '❗️ برای ادامه کار و استفاده از این بخش، شما باید عضو کانال‌های ما شوید.

👇 بعد از عضویت در کانال‌ها روی دکمه « ✅ عضو شدم » بزنید 👇', $message_id, json_encode(['inline_keyboard'=>[
            [['text'=>'📢 کانال اول', 'url'=>$channel['link'][0]], ['text'=>'📢 کانال دوم', 'url'=>$channel['link'][1]]],
            [['text'=>'✅ عضو شدم', 'callback_data'=>'start stars_'.$id]]
        ]]));
        exit;
    }

    $file = $pdo->query("SELECT * FROM files WHERE id = '$id' AND ads_type = 'stars' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$file) {
        SM($from_id, 'چنین سورس ستاره‌ای با این آیدی وجود ندارد ❗️', $message_id, $menu);
        exit;
    }
    
    if($pdo->query("SELECT * FROM download WHERE user_id = '$from_id' AND file_id = '$id'")->rowCount() > 0){
        SM($from_id, 'شما قبلاً این سورس را دریافت کرده‌اید. در حال ارسال مجدد...', $message_id);
        bot('sendDocument', ['chat_id'=>$from_id, 'document'=>$file['file_id'], 'caption'=>'📂 '.$file['title']]);
        exit;
    }

    // ارسال فاکتور برای پرداخت با استارز
    $invoice = [
        "chat_id"      => $from_id,
        "title"        => "📥 خرید سورس: " . $file['title'],
        "description"  => "برای دریافت این سورس باید {$file['amount']} استارز پرداخت کنید.",
        "payload"      => "buy_stars_".$file['id'],
        "provider_token"=> "",  // برای استارز باید خالی باشد
        "currency"     => "XTR", // واحد پول تلگرام استارز
        "prices"       => json_encode([
            ["label" => "دسترسی به سورس", "amount" => (int)$file['amount']]
        ])
    ];
    bot("sendInvoice", $invoice);
    exit;
}
// ======================= END: HANDLER FOR `start stars_` =======================
elseif(preg_match('/^[\/\!\#\.]?start zm_(.*)/',$message,$match)){
        if(isset($update->callback_query->data)) bot('deletemessage',['chat_id'=>$from_id,'message_id'=>$message_id]);
        $id = $match[1];

        if($ChannelLock_One=='left' or $ChannelLock_Two=='left'){
            SM($from_id, '❗️ برای ادامه کار و استفاده از این بخش، شما باید عضو کانال‌های ما شوید.

👇 بعد از عضویت در کانال‌ها روی دکمه « ✅ عضو شدم » بزنید 👇', $message_id, json_encode(['inline_keyboard'=>[
                [['text'=>'📢 کانال اول', 'url'=>$channel['link'][0]], ['text'=>'📢 کانال دوم', 'url'=>$channel['link'][1]]],
                [['text'=>'✅ عضو شدم', 'callback_data'=>'start zm_'.$id]]
            ]]));
            exit;
        }

        $file = $pdo->query("SELECT * FROM files WHERE id = '$id' AND ads_type = 'zm' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$file) {
            SM($from_id, 'چنین سورس عضوگیری با این آیدی وجود ندارد ❗️', $message_id, $menu);
            exit;
        }

        // بررسی اینکه آیا کاربر قبلا این سورس را دریافت کرده
        $is_completed = $pdo->query("SELECT id FROM zm_invites WHERE user_id = '$from_id' AND file_id = '$id' AND status = 'completed'")->rowCount();
        if ($is_completed > 0) {
            SM($from_id, 'شما قبلاً این سورس را دریافت کرده‌اید. در حال ارسال مجدد...', $message_id);
            bot('sendDocument', ['chat_id'=>$from_id, 'document'=>$file['file_id'], 'caption'=>'📂 '.$file['title']]);
            exit;
        }

        // دریافت یا ایجاد لینک دعوت
        $invite = $pdo->query("SELECT * FROM zm_invites WHERE user_id = '$from_id' AND file_id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$invite) {
            // ایجاد لینک دعوت جدید
            $link_name = "f{$id}u{$from_id}"; // یک نام یونیک برای لینک
            $invite_link_obj = bot('createChatInviteLink', [
                'chat_id' => ZM_CHANNEL_ID,
                'name' => $link_name,
                'creates_join_request' => false // اگر کانال عمومی است
            ]);

            if ($invite_link_obj->ok) {
                $invite_link = $invite_link_obj->result->invite_link;
                $required_members = $file['amount'];

                // ذخیره لینک در دیتابیس
                $stmt = $pdo->prepare("INSERT INTO zm_invites (user_id, file_id, invite_link, required_members) VALUES (?, ?, ?, ?)");
                $stmt->execute([$from_id, $id, $invite_link, $required_members]);
                $current_members = 0;
            } else {
                SM($from_id, 'خطا در ایجاد لینک دعوت. لطفاً به مدیر اطلاع دهید.', $message_id, $menu);
                // لاگ کردن خطا برای مدیر
                sm(end($Devs), "Error creating invite link: " . $invite_link_obj->description);
                exit;
            }
        } else {
            $invite_link = $invite['invite_link'];
            $required_members = $invite['required_members'];
            $current_members = $invite['current_members'];
        }

        $remaining = $required_members - $current_members;
        if ($remaining < 0) $remaining = 0;

        $text = "🎁 برای دریافت سورس «{$file['title']}» شما باید *$required_members* نفر را از طریق لینک اختصاصی خود به کانال ما دعوت کنید."."\n\n";
        $text .= "📈 وضعیت فعلی شما: *{$current_members}* از *{$required_members}* نفر ( *$remaining* نفر باقی مانده )"."\n\n";
        $text .= "👇 لینک اختصاصی شما:\n`$invite_link`"."\n\n".'(روی لینک بزنید تا کپی شود)';

        SM($from_id, $text, $message_id, $menu, 'MarkDown');
        exit;
    }
    // --- END: ZM Feature - Handle ZM start link ---
    
//---------------------------------------------------------------------------
 elseif(preg_match('/^[\/\!\#\.]?start file_(.*)/',$message,$match)){
        if(isset($update->callback_query->data)) bot('deletemessage',['chat_id'=>$from_id,'message_id'=>$message_id]);
        $id = $match[1];
        if($ChannelLock_One=='left' or $ChannelLock_Two=='left'){
            SM($from_id, '❗️ برای دریافت سورس ها ، اطلاعیه ها و گزارشات شما باید عضو کانال ربات شوید
        
👇 بعد از عضویت در کانال روی دکمه « ✅ تایید عضویت » بزنید �', $message_id, json_encode(['inline_keyboard'=>[
                [['text'=>'📢 کانال اول', 'url'=>$channel['link'][0]], ['text'=>'📢 کانال دوم', 'url'=>$channel['link'][1]]],
                [['text'=>'✅ تایید عضویت', 'callback_data'=>'start file_'.$id]]
            ]]));
            $pdo = null;
        } else {
            $rowCount = $pdo->query("SELECT id FROM files WHERE id = '$id'")->rowCount();
            if($rowCount < 1){
                SM($from_id, 'چنین فایلی با این آیدی وجود ندارد ❗️', $message_id, $menu);
                $pdo = null;
            } else {
                
                  $query = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                
                // START: NEW LOGIC FOR HANDLING DIFFERENT SOURCE TYPES
                if($query['ads_type'] == 'stars'){
                    sm($from_id,'این سورس را باید با استارز خریداری کنید.', $message_id, json_encode(['inline_keyboard'=>[
                        [['text'=>"⭐️ خرید با استارز",'url'=>"https://t.me/$bot_user?start=stars_$id"]]
                    ]]));
                    exit;
                }
                // END: NEW LOGIC FOR HANDLING DIFFERENT SOURCE TYPES


                $query = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if($query['ads_type'] == 'zm'){
                    sm($from_id,'این سورس را باید ممبر بکنید کنید.',$message_id,json_encode(['inline_keyboard'=>[
              [['text'=>"دریافت با ممبرجان",'url'=>"https://t.me/pejvakrobot?start=zm_$id"]]
        
         ]]));
                    exit;
                }
                $query = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if($query['ads_type'] == 'vip'){
                    sm($from_id,'این سورس را باید خریداری کنید. /down_'.$id.'');
                    exit;
                }
                if($query['ads_type'] == 'coin'){
                    if($users['coin'] >= $query['amount']){
                        if($pdo->query("SELECT * FROM download WHERE user_id = '$from_id' AND file_id = '$id'")->rowCount() < 1){
                            $dc = $query['down_count']+1;
                            $pdo->exec("UPDATE files SET down_count = '$dc' WHERE id = '$id' LIMIT 1");
                            $dncn = $users['down_count']+1;
                            $co = $users['coin'] - $query['amount'];
                            $pdo->exec("UPDATE users SET down_count = '$dncn',coin = '$co' WHERE id = '$from_id' LIMIT 1");
                            $pdo->exec("INSERT INTO download (user_id, file_id) VALUES ('$from_id', '$id')");
                            bot('sendDocument', [
                                'chat_id'=>$from_id,
                                'document'=>$query['file_id'],
                                'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : <code>'.$query['id'].'</code>
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر :
'.$query['caption'].'

🆔 @'.$channel['username'],
                                'parse_mode'=>'html',
                                'reply_markup'=>json_encode(['inline_keyboard'=>[
                                    [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$id"]],
                                    [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'cclike_'.$id], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]]
                                ],])
                            ]);
                            $users = $pdo->query("SELECT * FROM users WHERE id = '$from_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                            sm($channel['ch_logs'],"کاربر  <a href='tg://user?id=$from_id'>$first_name</a> | <a href='t.me/".str_replace('@', '', $brand_username)."/$id'>{$query['title']}</a> را با امتیاز دریافت کرد\n\nامتیازات جدید کاربر : {$users['coin']}");
                        } else {
                            bot('sendDocument', [
                                'chat_id'=>$from_id,
                                'document'=>$query['file_id'],
                                'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : <code>'.$query['id'].'</code>
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر :
'.$query['caption'].'

🆔 @'.$channel['username'],
                                'parse_mode'=>'html',
                                'reply_markup'=>json_encode(['inline_keyboard'=>[
                                    [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$id"]],
                                    [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'flike_'.$id], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]]
                                ],])
                            ]);
                        }
                    } else {
                        $am = $query['amount'] - $users['coin'];
                        sm($from_id,"کاربر گرامی ؛ شما به $am سکه دیگر جهت دریافت این سورس نیاز دارید.");
                    }
                    exit;
                } else { // ads_type is 'free'
                    // --- START: ZM Feature - Penalty Check ---
                    if ($users['zm_penalty_count'] > 0) {
                        $new_penalty_count = $users['zm_penalty_count'] - 1;
                        $pdo->exec("UPDATE users SET zm_penalty_count = '$new_penalty_count' WHERE id = '$from_id' LIMIT 1");
                        SM($from_id, "❌ شما به دلیل خروج اعضای دعوت شده جریمه شده‌اید و نمی‌توانید این سورس رایگان را دریافت کنید.\n\nتعداد محرومیت باقی‌مانده: $new_penalty_count بار", $message_id, $menu);
                        exit;
                    }
                    // --- END: ZM Feature - Penalty Check ---

                    if($pdo->query("SELECT * FROM download WHERE user_id = '$from_id' AND file_id = '$id'")->rowCount() < 1 && $query['ads_type'] == 'free'){
                        if($query['down_count'] < $query['limits'] && $query['ads_type'] == 'free'){
                            $pdo->exec("INSERT INTO download (user_id, file_id) VALUES ('$from_id', '$id')");
                            bot('sendDocument', [
                                'chat_id'=>$from_id,
                                'document'=>$query['file_id'],
                                'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : <code>'.$query['id'].'</code>
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر :
'.$query['caption'].'

🆔 @'.$channel['username'],
                                'parse_mode'=>'html',
                                'reply_markup'=>json_encode(['inline_keyboard'=>[
                                    [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$id"]],
                                    [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'flike_'.$id], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]],
                                ],])
                            ]);
                            $dc = $query['down_count']+1;
                            $pdo->exec("UPDATE files SET down_count = '$dc' WHERE id = '$id' LIMIT 1");
                            $dncn = $users['down_count']+1;
                            $pdo->exec("UPDATE users SET down_count = '$dncn' WHERE id = '$from_id' LIMIT 1");
                            sm($channel['ch_logs'],"کاربر  <a href='tg://user?id=$from_id'>$first_name</a> | <a href='t.me/".str_replace('@', '', $brand_username)."/$id'>{$query['title']}</a> را دریافت کرد");
                            $pdo = null;
                        } else {
                            if($users['coin'] >= 1){
                                $pdo->exec("INSERT INTO download (user_id, file_id) VALUES ('$from_id', '$id')");
                                bot('sendDocument', [
                                    'chat_id'=>$from_id,
                                    'document'=>$query['file_id'],
                                    'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : <code>'.$query['id'].'</code>
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر :
'.$query['caption'].'

🆔 @'.$channel['username'],
                                    'parse_mode'=>'html',
                                    'reply_markup'=>json_encode(['inline_keyboard'=>[
                                        [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$id"]],
                                        [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'flike_'.$id], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]],
                                    ],])
                                ]);
                                $cn = $users['coin']-1;
                                $dncn = $users['down_count']+1;
                                $pdo->exec("UPDATE users SET coin = '$cn', down_count = '$dncn' WHERE id = '$from_id' LIMIT 1");
                                $users = $pdo->query("SELECT * FROM users WHERE id = '$from_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                                sm($channel['ch_logs'],"کاربر  <a href='tg://user?id=$from_id'>$first_name</a> | <a href='t.me/".str_replace('@', '', $brand_username)."/$id'>{$query['title']}</a> را با امتیاز دریافت کرد\n\nامتیازات جدید کاربر : {$users['coin']}");
                                $pdo = null;
                            } else {
                                SM($from_id, '❗️ ظرفیت دانلود رایگان این سورس به پایان رسیده است و نمیتوانید سورس را رایگان دریافت کنید

☑️ شما میتوانید با افزایش سکه های حساب خود اقدام به دریافت سورس و فایل ها بدون محدودیت کنید
👈🏻 با دریافت هر سورس که ظرفیت دانلود رایگان آن به پایان رسیده است یک سکه از شما کسر خواهد شد', $message_id, $menu);
                                $pdo = null;
                            }
                        }
                    } else {
                        bot('sendDocument', [
                            'chat_id'=>$from_id,
                            'document'=>$query['file_id'],
                            'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : <code>'.$query['id'].'</code>
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر :
'.$query['caption'].'

🆔 @'.$channel['username'],
                            'parse_mode'=>'html',
                            'reply_markup'=>json_encode(['inline_keyboard'=>[
                                [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$id"]],
                                [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'flike_'.$id], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]]
                            ],])
                        ]);
                        $pdo = null;
                    }
                }
            }
        }
    }
    elseif(strpos($message, '/start buy_') !== false){
        $id = str_replace('/start buy_', null, $message);
        if($ChannelLock_One=='left' or $ChannelLock_Two=='left'){
            SM($from_id, '❗️ برای دریافت سورس ها ، اطلاعیه ها و گزارشات شما باید عضو کانال ربات شوید
        
👇 بعد از عضویت در کانال روی دکمه « ✅ تایید عضویت » بزنید 👇', $message_id, json_encode(['inline_keyboard'=>[
                [['text'=>'📢 کانال اول', 'url'=>$channel['link'][0]], ['text'=>'📢 کانال دوم', 'url'=>$channel['link'][1]]],
                [['text'=>'✅ تایید عضویت', 'callback_data'=>'isJoin']]
            ]]));
            $pdo = null;
        } else {
            $rowCount = $pdo->query("SELECT id FROM files WHERE id = '$id'")->rowCount();
            if($rowCount < 1){
                SM($from_id, 'چنین فایلی با این آیدی وجود ندارد ❗️', $message_id, $menu);
                $pdo = null;
            } else {
                $query = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if($query['ads_type'] != 'vip'){
                    sm($from_id,'این سورس فروشی نیست!');
                    exit;
                }
                if($users['phone_number']!=0){
                    $randomcode  =  uniqid().rand(1000,9999);
                    $pdo->exec("INSERT INTO re_payments (`file`,`id`,`amount`,`desc`,`type`,`fromid`,`time`) VALUES ('{$id}','$randomcode','{$query['amount']}','خرید سورس {$query['id']} در $bot_name','source','$from_id','$timering')");

                    bot('sendPhoto', [
                        'chat_id'=>$from_id,
                        'photo'=>$query['cover'],
                        'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : <code>'.$query['id'].'</code>
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر :
'.$query['caption'].'

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
                        'parse_mode'=>'html',
                        'reply_markup'=>json_encode(['inline_keyboard'=>[
                            [['text'=>'💸 خرید سورس '.number_format($query['amount'] / 10).' تومان'.' | '.number_format($query['amount']).' ریال', 'url'=>"{$channel['domin']}/PayLink/request.php?payment=$randomcode"]],
                                    
                        ],])
                    ]);
                    $pdo = null;
                }else{
                    $pdo->exec("UPDATE users SET step = 'share_number' WHERE id = '$from_id' LIMIT 1");
                    SM($from_id, 'کاربر گرامی جهت ادامه فعالیت شما در ربات و تایید هویت ایرانی لازم به اشتراک شماره شما میباشد ‼️
لطفا با کلیدبُرد زیر اقدام به تایید هویت خود کنید 👇👇', $message_id, $request);
                    $pdo = null;
                }
            }
        }
    }

    elseif(preg_match('/^\/(start)/', $message)){
        preg_match('/^\/(start inv_(.*))/', $message, $match);
        $id = str_replace([' ', PHP_EOL], null, $match[2]);
        $rowCount = $pdo->query("SELECT id FROM users WHERE id = '$id'")->rowCount();
        $rowCount2 = $pdo->query("SELECT id FROM users WHERE id = '$from_id'")->rowCount();
        if($id != null){
            if($id != $from_id and $rowCount > 0 and $rowCount2 < 1 and $id > 0){
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $pdo->exec("INSERT INTO users (id, inviter, timer) VALUES ('$from_id', '$id', '$yesterday')");
                if($ChannelLock_One=='left' or $ChannelLock_Two=='left'){
                    SM($id, 'کاربر '.$from_id.' توسط لینک اختصاصی شما وارد ربات شد ✔️
پس از عضویت کاربر مذکور در کانال های عضویت اجباری و تایید کردن عضویت خود {'.$channel['subset_coin'].'} سکه به حساب شما واریز میگردد🎈');
                    SM($from_id, '🎯  به '.$bot_name.' خوش آمدید!

اینجا می تونی انواع سورس ربات ، وب سرویس های کاربردی ، قالب های وردپرسی و سایت ، انواع اسکریپت ها رو پیدا  و دانلودشون کنی !

😎 همین حالا امتحان کنید.', $message_id, $menu);
                    $pdo = null;
                } else {
                    $query = $pdo->query("SELECT * FROM users WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    $cn = $query['coin']+$channel['subset_coin'];
                    $cn2 = $query['subset']+1;
                    $cn3 = $query['daily_subset']+1;
                    
                    $pdo->exec("UPDATE users SET coin = '$cn',subset = '$cn2' ,last_subset='$date', daily_subset='$cn3' WHERE id = '$id' LIMIT 1");


                    SM($id, 'کاربر '.$from_id.' توسط لینک اختصاصی شما وارد ربات شد و مقدار {'.$channel['subset_coin'].'} سکه دریافت کردید ✔️');
                    SM($from_id, '🎯  به '.$bot_name.' خوش آمدید!

اینجا می تونی انواع سورس ربات ، وب سرویس های کاربردی ، قالب های وردپرسی و سایت ، انواع اسکریپت ها رو پیدا  و دانلودشون کنی !

😎 همین حالا امتحان کنید.', $message_id, $menu);
// foreach($Devs as $userid){
                    sm($channel['ch_logs'],"کاربر <a href='tg://user?id=$from_id'>$from_id</a> زیرمجموعه کاربر <a href='tg://user?id=$id'>$id</a> شد.");
                    // }
                    $pdo = null;
                }
            } else {
                $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
                SM($from_id, '🎯  به '.$bot_name.' خوش آمدید!

اینجا می تونی انواع سورس ربات ، وب سرویس های کاربردی ، قالب های وردپرسی و سایت ، انواع اسکریپت ها رو پیدا  و دانلودشون کنی !

😎 همین حالا امتحان کنید.', $message_id, $menu);
                $pdo = null;
            }
        } else {
            if($rowCount2 < 1){
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $pdo->exec("INSERT INTO users (id, timer) VALUES ('$from_id', '$yesterday')");
                SM($from_id, '🎯  به '.$bot_name.' خوش آمدید!

اینجا می تونی انواع سورس ربات ، وب سرویس های کاربردی ، قالب های وردپرسی و سایت ، انواع اسکریپت ها رو پیدا  و دانلودشون کنی !

😎 همین حالا امتحان کنید.', $message_id, $menu);
                $pdo = null;
            } else {
                $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
                SM($from_id, '🎯  به '.$bot_name.' خوش آمدید!

اینجا می تونی انواع سورس ربات ، وب سرویس های کاربردی ، قالب های وردپرسی و سایت ، انواع اسکریپت ها رو پیدا  و دانلودشون کنی !

😎 همین حالا امتحان کنید.', $message_id, $menu);
                $pdo = null;
            }
        }
    }

    elseif(isset($update->message->text) and $ChannelLock_One=='left' or $ChannelLock_Two=='left'){
        SM($from_id, '❗️ برای دریافت سورس ها ، اطلاعیه ها و گزارشات شما باید عضو کانال ربات شوید
        
👇 بعد از عضویت در کانال روی دکمه « ✅ تایید عضویت » بزنید 👇', $message_id, json_encode(['inline_keyboard'=>[
            [['text'=>'📢 کانال اول', 'url'=>$channel['link'][0]], ['text'=>'📢 کانال دوم', 'url'=>$channel['link'][1]]],
            [['text'=>'✅ تایید عضویت', 'callback_data'=>'isJoin']]
        ]]));
        $pdo = null;
    }

    elseif(isset($update->callback_query->data) and $message=='isJoin'){
        if($ChannelLock_One=='left' or $ChannelLock_Two=='left'){
            bot('answerCallbackQuery',[
                'callback_query_id'=>$update->callback_query->id,
                'text'=>'⚠️ شما هنوز در کانال ها عضو نشدید ...',
                'show_alert'=>true
            ]);
            $pdo = null;
        } else {
            bot('deleteMessage',[
                'chat_id'=>$from_id,
                'message_id'=>$message_id
            ]);
            SM($from_id, 'عضویت شما با موفقیت تایید شد✔️', ($message_id-1), $menu);
            if($users['inviter']!=0){
                $id = $users['inviter'];
                $query = $pdo->query("SELECT * FROM users WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $cn = $query['coin']+$channel['subset_coin'];
                $cn2 = $query['subset']+1;
                $cn3 = $query['daily_subset']+1;
                $pdo->exec("UPDATE users SET coin = '$cn',subset = '$cn2' ,last_subset='$date',daily_subset='$cn3' WHERE id = '$id' LIMIT 1");
                $pdo->exec("UPDATE users SET inviter = '0' WHERE id = '$from_id' LIMIT 1");
                SM($id, 'کاربر '.$from_id.' عضویت خود را تایید کرد و شما مقدار {'.$channel['subset_coin'].'} سکه دریافت کردید ✔️️');
                // foreach($Devs as $userid){
                sm($channel['ch_logs'],"کاربر <a href='tg://user?id=$from_id'>$from_id</a> زیرمجموعه کاربر <a href='tg://user?id=$id'>$id</a> شد.");
                // }
                $pdo = null;
            }
            $pdo = null;
        }
    }
    elseif($message=='بازگشت ↪️'){
        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '🎯  به '.$bot_name.' خوش آمدید!

اینجا می تونی انواع سورس ربات ، وب سرویس های کاربردی ، قالب های وردپرسی و سایت ، انواع اسکریپت ها رو پیدا  و دانلودشون کنی !

😎 همین حالا امتحان کنید.', $message_id, $menu);
        $pdo = null;
    }
    elseif($message=='🗂 ارسال سورس'){
        $pdo->exec("UPDATE users SET step = 'userbanner' WHERE id = '$from_id' LIMIT 1");
        sm($from_id,"💎 به بخش ارسال سورس خوش آمدید!

	✈️ شما در این بخش برای ما سورس ارسال میکنید و پس از تایید ادمین ها ، سورس ارسالی شما در کانال قرار می گیرد ، همچنین به ازای هر تایید سورس ارسالی 3 سکه دریافت میکنید.
	
	✅ دقت کنید هیچ تگ یا نامی از دیگر مرجع ها نباید در موارد ارسالی باشد.
	
	🌄  حالا یک عکس بعنوان بنر سورس ارسال کنید : 
",null,$back);
        $pdo = null;
    }
    elseif($users['step']=='userbanner'){
        if(isset($update->message->photo)){
            $pdo->exec("UPDATE users SET step = 'sendtitleuser' WHERE id = '$from_id' LIMIT 1");
            $photo_id = end($update->message->photo)->file_id;
            $data = json_decode(file_get_contents('data/data'.$from_id.'.json'), true);
            $data['cover'] = $photo_id;
            $data['like_count']=0;
            $data['down_count']=0;
            $data['buy_count']=0;
            file_put_contents('data/data'.$from_id.'.json', json_encode($data, 448));
            $getfile = bot('getfile', ['file_id' => $photo_id])->result->file_path;
            file_put_contents('data/cover'.$from_id.'.jpg', file_get_contents('https://api.telegram.org/file/bot'.TOKEN_POKER.'/'.$getfile));
            SM($from_id, '📍 لطفا نام سورس را ارسال کنید :', $message_id, $back);
            $pdo = null;
        } else {
            SM($from_id, 'فقط ارسال عکس مجاز است !', $message_id, $back);
            $pdo = null;
        }
    }
    elseif($users['step']=='sendtitleuser'){
        if(mb_strlen($message) < 301){
            $pdo->exec("UPDATE users SET step = 'sendLanguser' WHERE id = '$from_id' LIMIT 1");
            $data = json_decode(file_get_contents('data/data'.$from_id.'.json'), true);
            $data['title'] = $message;
            file_put_contents('data/data'.$from_id.'.json', json_encode($data, 448));
            SM($from_id, '📍 لطفا زبان توسعه یافته سورس را ارسال کنید :', $message_id, $back);
            $pdo = null;
        } else {
            SM($from_id, 'متن وارد شده طولانی میباشد !', $message_id, $back);
            $pdo = null;
        }
    }
    elseif($users['step']=='sendLanguser'){
        if(mb_strlen($message) < 101){
            $pdo->exec("UPDATE users SET step = 'sendCaptionuser' WHERE id = '$from_id' LIMIT 1");
            $data = json_decode(file_get_contents('data/data'.$from_id.'.json'), true);
            $data['lang'] = $message;
            file_put_contents('data/data'.$from_id.'.json', json_encode($data, 448));
            SM($from_id, '📍 توضیحات مربوط به سورس را ارسال نمایید :', $message_id, $back);
            $pdo = null;
        } else {
            SM($from_id, 'متن وارد شده طولانی میباشد !', $message_id, $back);
            $pdo = null;
        }
    }
    elseif($users['step']=='sendCaptionuser'){
        $data = json_decode(file_get_contents('data/data'.$from_id.'.json'), true);
        $data['caption'] = $message;
        $data['type'] = 'free';
        file_put_contents('data/data'.$from_id.'.json', json_encode($data, 448));
        $pdo->exec("UPDATE users SET step = 'sendLimituser' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '📍 تعداد محدودیت دانلود بصورت رایگان را وارد کنید :', $message_id, $back);
        $pdo = null;
    }
    elseif($users['step']=='sendLimituser' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        if(is_numeric($message)){
            if($message > 4 && $message < 30){
                $pdo->exec("UPDATE users SET step = 'sendFileuser' WHERE id = '$from_id' LIMIT 1");
                $data = json_decode(file_get_contents('data/data'.$from_id.'.json'), true);
                $data['limit'] = $message;
                file_put_contents('data/data'.$from_id.'.json', json_encode($data, 448));
                SM($from_id, '📍 سورس را ارسال نمایید :', $message_id, $back);
                $pdo = null;
            } else {
                sm($from_id,'عدد وارد شده باید بیشتر از 5 و کمتر از 30 باشد!');
                $pdo = null;
            }
        } else {
            SM($from_id, 'فقط ارسال اعداد بصورت لاتین مجاز میباشد !', $message_id, $back);
            $pdo = null;
        }
    }
    elseif($users['step']=='sendFileuser'){
        sm($from_id,'در حال برسی کمی صبر کنید...');
        if(isset($update->message->document)){
            $file_id = $update->message->document->file_id;
            $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
            $data = json_decode(file_get_contents('data/data'.$from_id.'.json'), true);
            $id = rand(111111,999999);
            $stamp = imagecreatefrompng('data/mark.png');
            $im = imagecreatefromjpeg('data/cover'.$from_id.'.jpg');
            $marge_right = 10;
            $marge_bottom = 10;
            $sx = imagesx($stamp);
            $sy = imagesy($stamp);
            imagecopy($im, $stamp, imagesx($im) - $sx - $marge_right, imagesy($im) - $sy - $marge_bottom, 0, 0, imagesx($stamp), imagesy($stamp));
            imagepng($im , 'data/cover'.$from_id.'.png');
            imagedestroy($im);
            foreach($Devs as $userid){
                bot('senddocument',['chat_id'=>$userid,'document'=>$file_id]);
                bot('sendPhoto',['chat_id'=>$userid,'parse_mode'=>'html','photo'=>new CURLFile('data/cover'.$from_id.'.jpg'),'caption'=>'📂 '.$data['title'].PHP_EOL.'📝زبان توسعه دهنده  : '.PHP_EOL.$data['lang'].PHP_EOL.PHP_EOL.'📜 توضیحات بیشتر : '.PHP_EOL.$data['caption'].PHP_EOL.PHP_EOL.'ارسال شده توسط کاربر : <a href="tg://user?id='.$from_id.'">'.$from_id.'</a>'.PHP_EOL.'تعداد دانلود : '.$data['limit'].PHP_EOL.'🆔 @'.$channel['username'],
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
                        [['text'=>'ارسال به کانال' ,'callback_data'=>'sendfile_'.$id.'_'.$from_id]],
                    ]])
                ]);
            }
            $amount=$data['amount']?:0;
            @unlink('data/data'.$from_id.'.json');
            @unlink('data/cover'.$from_id.'.jpg');
            @unlink('data/cover'.$from_id.'.png');
            $pdo->exec("INSERT INTO sends (id, cover, title, lang, caption, ads_type, limits, amount, file_id,sender) VALUES ('$id', '{$data['cover']}', '{$data['title']}', '{$data['lang']}', '{$data['caption']}', '{$data['type']}', '{$data['limit']}', '$amount', '$file_id','$from_id')");
            SM($from_id, 'سورس شما برای ادمین های ربات ارسال شد. پس از تایید به کانال ارسال میشود!'.PHP_EOL.'توجه کنید : در سورس ارسالی شما نباید هیچ اشاره ای به شخص یا کانالی شده باشد !', $message_id, $back);
            $pdo = null;
        } else {
            SM($from_id, 'فقط ارسال فایل مجاز میباشد !', $message_id, $back2);
            $pdo = null;
        }
    }
    elseif(preg_match('/^sendfile_(.*)_(.*)/',$message,$match)){
        bot('deletemessage',['chat_id'=>$from_id,'message_id'=>$message_id]);
        sm($match[2],"✅ سورس شما با موفقیت تایید شد و 3 سکه به حساب شما اضافه شد.\nباتشکر از شما❤️");
        sm($from_id,"ارسال شد!");
        $uuu = $pdo->query("SELECT * FROM users WHERE id = '$match[2]' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $cn = $uuu['coin']+3;
        $pdo->exec("UPDATE users SET coin = '$cn' WHERE id = '$match[2]' LIMIT 1");
        $info = bot('getchat',['chat_id'=>$match[2]])->result;
        $data = $pdo->query("SELECT * FROM sends WHERE id = '$match[1]' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $msg_id = bot('sendPhoto',[
            'chat_id'=>$brand_username,
            'photo'=>$data['cover'],
            'caption'=>'📂 '.$data['title'].'
➰ ایدی سورس : id*
📝زبان توسعه دهنده  : '.$data['lang'].'

📜 توضیحات بیشتر :
'.$data['caption'].'

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
            'reply_markup'=>json_encode(['inline_keyboard'=>[
                [['text'=>'📮 دریافت سورس', 'url'=>'https://t.me/'.$bot_user.'?start=file_']],
                [['text'=>'📊 آمار دانلود بصورت رایگان : 0 از '.$data['limits'], 'callback_data'=>'pejvakSource']],
                [['text'=>'❤️ (0)', 'callback_data'=>'flike_'], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    

            ]])
        ])->result->message_id;
        try{
            $pdo->exec("INSERT INTO files (id, cover, title, lang, caption, ads_type, limits, amount, file_id) VALUES ('$msg_id', '{$data['cover']}', '{$data['title']}', '{$data['lang']}', '{$data['caption']}', 'free', '{$data['limits']}', '0', '{$data['file_id']}')");
        } catch(PDOException $e){
            file_put_contents('e.txt',$e->getMessage());
            die();
        }
        if(rand(1,4) == 2) $info->first_name = "<a href='tg://user?id=$match[2]'>{$info->first_name}</a>";
        $query = $pdo->query("SELECT * FROM files WHERE id = '$msg_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        bot('editMessageCaption', [
            'chat_id'=>$brand_username,
            'message_id'=>$msg_id,
            'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : '.$query['id'].'
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر : 
'.$query['caption'].'

🗞 ارسال شده توسط : '.$info->first_name.'

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
            'parse_mode'=>'html',
            'reply_markup'=>json_encode(['inline_keyboard'=>[
                [['text'=>'📮 دریافت سورس', 'url'=>'https://t.me/'.$bot_user.'?start=file_'.$query['id']]],
                [['text'=>'📊 آمار دانلود بصورت رایگان : 0 از '.$query['limits'], 'callback_data'=>'pejvakSource']],
                [['text'=>'❤️ (0)', 'callback_data'=>'flike_'.$query['id']], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    

            ]])
        ]);
        $pdo->exec("delete from sends where id = '$match[1]'");
    }
    elseif($pdo->query("SELECT id FROM users WHERE id = '$from_id'")->rowCount() < 1){
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $pdo->exec("INSERT INTO users (id, timer) VALUES ('$from_id', '$yesterday')");
    }
    elseif($message=='جستـجو 🔍'){
        $pdo->exec("UPDATE users SET step = 'select-search-item' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '🔍 خب چطور میخوای جستجو کنی ؟

👌 با نام : جستجو با اسم سورس(عبارت)
        
👌 با آیدی : جستجو با آیدی سورس(عدد)', $message_id, $key_srch);
        $pdo = null;
    }

    elseif($message=='🖌 جستجو با آیدی سورس'){
        $pdo->exec("UPDATE users SET step = 'search-src-with-id' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '👇🏻 جهت جست و جوی سورس مورد نظر خود لطفا  آیدی محصول را ارسال کنید :
👈🏻 جهت مشاهده لیست کامل سورس ها کافیست به « @'.$channel['username'].' » مراجعه کنید.', $message_id, $back);
        $pdo = null;
    }

    elseif($message=='‍🔥 جستجو با نام سورس'){
        $pdo->exec("UPDATE users SET step = 'search-src-with-name' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '👇🏻 جهت جست و جوی سورس مورد نظر خود لطفا  نام محصول را ارسال کنید :
     👈🏻 جهت مشاهده لیست کامل سورس ها کافیست به « @'.$channel['username'].' » مراجعه کنید.', $message_id, $back);
        $pdo = null;
    }

    elseif($users['step'] == 'search-src-with-name' and !in_array($message,['بازگشت ↪️','/start'])){
        //   $file = mysqli_query($connect,"SELECT * FROM `file` WHERE `caption` like '%$text%'");
        $file = $pdo->query("SELECT * FROM `files` WHERE `title` like '%$message%'");

        if($file->rowCount() > 0){


            while($row = $file->fetch(PDO::FETCH_ASSOC))
                // sm($from_id,"hi ".$row['id']);

                if($row['ads_type']=='free'){
                    // sm($from_id,"hi ".$row['id']);
                    bot('sendPhoto', [
                        'chat_id'=>$from_id,
                        'photo'=>$row['cover'],
                        'caption'=>'📂 '.$row['title'].'
➰ ایدی سورس : '.$row['id'].'
📝زبان توسعه دهنده  : '.$row['lang'].'
    
📜 توضیحات بیشتر : 
'.$row['caption'].'
    
🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.
    
🆔 @'.$channel['username'],
                        'parse_mode'=>'html',
                        'reply_markup'=>json_encode(['inline_keyboard'=>[
                            [['text'=>'📮 دریافت سورس', 'callback_data'=>'start file_'.$row['id']]],
                            [['text'=>'📊 آمار دانلود بصورت رایگان :  '.$row['down_count'].' از '.$row['limits'], 'callback_data'=>'flike_'.$row['id']]],
                            [['text'=>'❤️ ('.$row['like_count'].')', 'callback_data'=>'flike_'.$row['id']], ['text'=>'📢 '.$brand_name,'url'=>'https://t.me/'.str_replace('@', '', $brand_username)]],
                            [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>$row['id']]],
                                    
                        ]])
                    ]);
                }

                else if($row['ads_type']=='vip'){

                    if($users['phone_number']!=0){
                        $randomcode  =  uniqid().rand(1000,9999);
                        $pdo->exec("INSERT INTO re_payments (`file`,`id`,`amount`,`desc`,`type`,`fromid`,`time`) VALUES ('{$row['id']}','$randomcode','{$row['amount']}','خرید سورس {$row['id']} در $bot_name','source','$from_id','$timering')");

                        bot('sendPhoto', [
                            'chat_id'=>$from_id,
                            'photo'=>$row['cover'],
                            'caption'=>'📂 '.$row['title'].'
➰ ایدی سورس : <code>'.$row['id'].'</code>
📝زبان توسعه دهنده  : '.$row['lang'].'

📜 توضیحات بیشتر :
'.$row['caption'].'

🎁 با خرید می توانید این سورس را دریافت کنید.

🆔 @'.$channel['username'],
                            'parse_mode'=>'html',
                            'reply_markup'=>json_encode(['inline_keyboard'=>[
                                [['text'=>'💸 خرید سورس | '.number_format($row['amount']).' ریال','url'=>"{$channel['domin']}/PayLink/request.php?payment=$randomcode"]],
                                [['text'=>'❤️ ('.$row['like_count'].')', 'callback_data'=>'flike_'.$row['id']], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]],
                                [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>$row['id']]],
                                    

                            ]])
                        ]);
                    }else{
                        $pdo->exec("UPDATE users SET step = 'share_number' WHERE id = '$from_id' LIMIT 1");
                        SM($from_id, 'کاربر گرامی جهت ادامه فعالیت شما در ربات و تایید هویت ایرانی لازم به اشتراک شماره شما میباشد ‼️
لطفا با کلیدبُرد زیر اقدام به تایید هویت خود کنید 👇👇', $message_id, $request);
                        $pdo = null;
                    }
                }
                else  if($row['ads_type']=='coin'){

                    bot('sendPhoto', [
                        'chat_id'=>$from_id,
                        'photo'=>$row['cover'],
                        'caption'=>'📂 '.$row['title'].'
➰ ایدی سورس : <code>'.$row['id'].'</code>
📝زبان توسعه دهنده  : '.$row['lang'].'

📜 توضیحات بیشتر :
'.$row['caption'].'

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
                        'parse_mode'=>'html',
                        'reply_markup'=>json_encode(['inline_keyboard'=>[
                            [['text'=>'دریافت سورس', 'url'=>"https://t.me/{$channel['bot_id']}?start=file_".$row['id']]],
                            [['text'=>'قیمت '.$row['amount'].' سکه','callback_data'=>'BuyBTN']],
                            [['text'=>'❤️ ('.$row['like_count'].')', 'callback_data'=>'clike_'.$row['id']], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]],
                            [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>$row['id']]],
                                    

                        ]])
                    ]);
                }

            SM($from_id, '👆🏻 جست و جو به پایان رسید , نتایج مرتبط برای شما ارسال شد', $msg_id, $menu);
            $pdo->exec("UPDATE users SET step = 'none' WHERE id = '$from_id' LIMIT 1");
            $pdo = null;

            // bot('sendmessage',[
            // 'chat_id'=>$from_id,
            // 'text'=>'👆🏻 جست و جو به پایان رسید , نتایج مرتبط برای شما ارسال شد',
            // 'reply_markup'=>$menu
            // ]);


        }else
            bot('sendmessage',[
                'chat_id'=>$from_id,
                'text'=>'❗️ خطا ، محصول مرتبطی با عبارت مورد نظر شما یافت نشد',
                'reply_to_message_id'=>$message_id,
                'reply_markup'=>$back
            ]);
    }

    elseif($users['step'] == 'search-src-with-id' and !in_array($message,['بازگشت ↪️','/start'])){
        if(is_numeric($message)){
            $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
            $query = $pdo->query("SELECT * FROM files WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if(isset($query['id'])){
                if($query['ads_type']=='free'){
                    $msg_id = bot('sendPhoto', [
                        'chat_id'=>$from_id,
                        'photo'=>$query['cover'],
                        'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : <code>'.$query['id'].'</code>
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر :
'.$query['caption'].'

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
                        'parse_mode'=>'html',
                        'reply_markup'=>json_encode(['inline_keyboard'=>[
                            [['text'=>'📮 دریافت سورس', 'url'=>'https://t.me/'.$bot_user.'?start=file_'.$message]],
                            [['text'=>'📊 آمار دانلود بصورت رایگان : '.$query['down_count'].' از '.$query['limits'], 'callback_data'=>'DNLoad']],
                            [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'flike_'.$message], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]],
                            [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$message"]],
                                    

                        ]])
                    ])->result->message_id;
                }
                if($query['ads_type']=='vip'){
                    if($users['phone_number']!=0){
                        $randomcode  =  uniqid().rand(1000,9999);
                        $pdo->exec("INSERT INTO re_payments (`file`,`id`,`amount`,`desc`,`type`,`fromid`,`time`) VALUES ('{$query['id']}','$randomcode','{$query['amount']}','خرید سورس {$query['id']} در $bot_name','source','$from_id','$timering')");

                        $msg_id = bot('sendPhoto', [
                            'chat_id'=>$from_id,
                            'photo'=>$query['cover'],
                            'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : <code>'.$query['id'].'</code>
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر :
'.$query['caption'].'

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
                            'parse_mode'=>'html',
                            'reply_markup'=>json_encode(['inline_keyboard'=>[
                                [['text'=>'💸 خرید سورس | '.number_format($query['amount']).' ریال','url'=>"{$channel['domin']}/PayLink/request.php?payment=$randomcode"]],
                                [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'vlike_'.$message], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]],
                                [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$message"]],
                                    

                            ],])
                        ])->result->message_id;
                    }else{
                        $pdo->exec("UPDATE users SET step = 'share_number' WHERE id = '$from_id' LIMIT 1");
                        SM($from_id, 'کاربر گرامی جهت ادامه فعالیت شما در ربات و تایید هویت ایرانی لازم به اشتراک شماره شما میباشد ‼️
لطفا با کلیدبُرد زیر اقدام به تایید هویت خود کنید 👇👇', $message_id, $request);
                        $pdo = null;
                    }
                }
                if($query['ads_type']=='coin'){
                    $msg_id = bot('sendPhoto', [
                        'chat_id'=>$from_id,
                        'photo'=>$query['cover'],
                        'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : <code>'.$query['id'].'</code>
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر :
'.$query['caption'].'

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
                        'parse_mode'=>'html',
                        'reply_markup'=>json_encode(['inline_keyboard'=>[
                            [['text'=>'دریافت سورس', 'url'=>"https://t.me/{$channel['bot_id']}?start=file_".$query['id']]],
                            [['text'=>'قیمت '.$query['amount'].' سکه','callback_data'=>'BuyBTN']],
                            [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'clike_'.$message], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]],
                            [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$message"]],
                                    

                        ],])
                    ])->result;
                }
                SM($from_id, '👆🏻 جست و جو به پایان رسید , نتایج مرتبط برای شما ارسال شد', $msg_id, $menu);
                $pdo = null;
            } else {
                SM($from_id, '❗️ خطا ، محصول مرتبطی با عبارت مورد نظر شما یافت نشد', $message_id, $menu);
                $pdo = null;
            }
        } else {
            sm($from_id,"لطفا فقط عدد وارد کنید.");
        }
    }
    elseif($message=='برترین سورس ها 📊'){
        SM($from_id, '🔖 در این بخش میتوانید برترین سورس هارا در 4 دسته بندی مختلف مشاهده نمایید :', $message_id, $key_best);

        $pdo = null;
    }elseif($message == 'گردونه شانس 🎡'){
    // First, check if the admin has enabled this feature
    if($settings['luckwheel_status'] == 0){
        SM($from_id, "🔧 متاسفانه قابلیت گردونه شانس در حال حاضر توسط مدیریت غیرفعال شده است.", $message_id, $menu);
        $pdo = null;  exit();
    }
    
    $wheel_keyboard = json_encode(['inline_keyboard' => [
        [['text' => 'چرخاندن گردونه 🎲', 'callback_data' => 'spin_the_wheel']]
    ]]);

    $prizes_text = "🎁 *جوایز گردونه شانس $brand_name:*\n\n" .
                   "1️⃣ *شانس مجدد:* یه فرصت دیگه برای چرخوندن!\n" .
                   "2️⃣ *پوچ:* هیچی! فردا دوباره تلاش کن.\n" .
                   "3️⃣ *سکه:* بین 1 تا 2 سکه برای دانلود سورس‌ها.\n" .
                   "4️⃣ *سورس رایگان:* یک سورس از دسته‌بندی رایگان به صورت شانسی.\n" .
                   "5️⃣ *نقره:* بین 50 تا 100 نقره برای تبدیل به سکه در پژواک پلاس.\n\n" .
                   "آماده‌ای شانست رو امتحان کنی؟ دکمه زیر رو بزن 👇";

    SM($from_id, $prizes_text, $message_id, $wheel_keyboard, 'MarkDown');
    $pdo = null; exit();
}

// This block handles the callback when the user clicks the "spin" button
elseif($message == 'spin_the_wheel'){
    // --- NEW: Check for channel membership ---
    $channel_event_lock = bot('getChatMember', ['chat_id' => '@BlueOceanPro', 'user_id' => $from_id])->result->status;
    if($channel_event_lock == 'left' || $channel_event_lock == 'kicked'){
        bot('answerCallbackQuery', [
            'callback_query_id' => $update->callback_query->id
        ]); // Answer the callback to remove the loading icon
        
        SM($from_id, '❗️ برای چرخاندن گردونه شانس، اول باید در کانال رویدادهای ما عضو بشی تا از برنده‌ها باخبر بشی! 😉', $message_id, json_encode(['inline_keyboard'=>[
            [['text'=>'📢 عضویت در کانال رویدادها', 'url'=>'https://t.me/BlueOceanPro']],
            [['text'=>'✅ عضو شدم، دوباره امتحان کن', 'callback_data'=>'spin_the_wheel']]
        ]]));
        $pdo = null; exit();
    }
    // --- END: Check for channel membership ---

    // Load user data
    $users = $pdo->query("SELECT * FROM users WHERE id = '$from_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$users) {
        bot('answerCallbackQuery', [
            'callback_query_id' => $update->callback_query->id,
            'text' => '❌ خطا: اطلاعات کاربر یافت نشد',
            'show_alert' => true
        ]);
        $pdo = null; exit();
    }

    // Get the current time as a timestamp
    $currentTime = time();
    $lastSpinTime = isset($users['last_spin_time']) ? (int)$users['last_spin_time'] : 0;

    // Check if 24 hours (86400 seconds) have passed since the last spin
    if($currentTime - $lastSpinTime < 86400){
        $remainingTime = 86400 - ($currentTime - $lastSpinTime);
        $hours = floor($remainingTime / 3600);
        $minutes = floor(($remainingTime % 3600) / 60);
        
        bot('answerCallbackQuery', [
            'callback_query_id' => $update->callback_query->id,
            'text' => "⏳ هنوز وقتش نشده! $hours ساعت و $minutes دقیقه دیگه دوباره امتحان کن.",
            'show_alert' => true
        ]);
        $pdo = null; exit();
    }

    // It's time to spin!
    bot('editMessageText', [
        'chat_id' => $from_id,
        'message_id' => $message_id,
        'text' => "🎡 گردونه شانس در حال چرخشه، ببینیم چی برات میاد... 🎲"
    ]);
    sleep(2);
    bot('editMessageText', ['chat_id' => $from_id, 'message_id' => $message_id, 'text' => "💣💥"]);
    sleep(1);

    // This loop handles the "reroll" prize. It continues until a final prize is won.
    $isFinalPrize = false;
    while(!$isFinalPrize){
        // Define the prizes and their weights (chances)
        $prizes = [
            'reroll'  => 10,  // 10% chance
            'nothing' => 25,  // 25% chance
            'coins'   => 35,  // 35% chance
            'silver'  => 20,  // 20% chance
            'source'  => 10   // 10% chance
        ];

        // A simple weighted random function
        $rand = mt_rand(1, (int) array_sum($prizes));
        foreach ($prizes as $prize => $weight) {
            if ($rand <= $weight) {
                $result = $prize;
                break;
            }
            $rand -= $weight;
        }

        switch ($result) {
            case 'reroll':
                bot('editMessageText', ['chat_id' => $from_id, 'message_id' => $message_id, 'text' => "🤩 شانس مجدد برنده شدی! دوباره می‌چرخونیم..."]);
                sleep(2);
                // The loop will continue, so isFinalPrize remains false
                break;

            case 'nothing':
                bot('editMessageText', ['chat_id' => $from_id, 'message_id' => $message_id, 'text' => "🥲 اوخ! پوچ درومد!\n\nعیب نداره، فردا دوباره شانست رو امتحان کن. شاید برنده شدی! 😉"]);
                $pdo->exec("UPDATE users SET last_spin_time = '$currentTime' WHERE id = '$from_id' LIMIT 1");
                $pdo->exec("INSERT INTO luckwheel_stats (user_id, prize_type, prize_value, spin_time) VALUES ('$from_id', 'nothing', '0', '$currentTime')");
                $isFinalPrize = true;
                break;

            case 'coins':
                $wonCoins = rand(1, 1.5);
                $pdo->exec("UPDATE users SET coin = coin + $wonCoins, last_spin_time = '$currentTime' WHERE id = '$from_id' LIMIT 1");
                $newCoinBalance = $users['coin'] + $wonCoins;
                
                $responseText = "🎉 تبریک! تو برنده *{$wonCoins} سکه* شدی! 🎉\n\n" .
                                "💡 *کاربرد سکه:* با سکه‌هات می‌تونی سورس‌های رایگان (بعد از اتمام ظرفیت) و سورس‌های سکه‌ای رو دانلود کنی.\n\n" .
                                "💰 موجودی جدیدت: *{$newCoinBalance} سکه*";
                bot('editMessageText', ['chat_id' => $from_id, 'message_id' => $message_id, 'text' => $responseText, 'parse_mode' => 'MarkDown']);
                
                $pdo->exec("INSERT INTO luckwheel_stats (user_id, prize_type, prize_value, spin_time) VALUES ('$from_id', 'coins', '$wonCoins', '$currentTime')");
                
                $channel_message = "🥳 کاربر خوش‌شانس با آیدی عددی `$from_id` در گردونه شانس برنده *{$wonCoins} سکه* شد!";
                SM('@PejvakEvents', $channel_message, null, json_encode(['inline_keyboard'=>[[['text'=>'منم میخوام شانسمو امتحان کنم! 🎲', 'url'=>'https://t.me/'.$bot_user]]]]), 'MarkDown');
                $isFinalPrize = true;
                break;

            case 'silver':
                $wonSilver = rand(50, 100);
                $pdo->exec("UPDATE users SET silver = silver + $wonSilver, last_spin_time = '$currentTime' WHERE id = '$from_id' LIMIT 1");
                $newSilverBalance = $users['silver'] + $wonSilver;

                $responseText = "💎 عالیه! تو برنده *{$wonSilver} نقره* شدی! 💎\n\n" .
                                "💡 *کاربرد نقره:* از منوی اصلی وارد بخش «پژواک پلاس ➕» شو و نقره‌هات رو به سکه تبدیل کن.\n\n" .
                                "🪙 موجودی جدید نقره شما: *{$newSilverBalance}*";
                bot('editMessageText', ['chat_id' => $from_id, 'message_id' => $message_id, 'text' => $responseText, 'parse_mode' => 'MarkDown']);

                $pdo->exec("INSERT INTO luckwheel_stats (user_id, prize_type, prize_value, spin_time) VALUES ('$from_id', 'silver', '$wonSilver', '$currentTime')");

                $channel_message = "🥳 کاربر خوش‌شانس با آیدی عددی `$from_id` در گردونه شانس برنده *{$wonSilver} نقره* شد!";
                SM('@PejvakEvents', $channel_message, null, json_encode(['inline_keyboard'=>[[['text'=>'منم میخوام شانسمو امتحان کنم! 🎲', 'url'=>'https://t.me/'.$bot_user]]]]), 'MarkDown');
                $isFinalPrize = true;
                break;

            case 'source':
                $freeSource = $pdo->query("SELECT * FROM files WHERE ads_type = 'free' ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($freeSource) {
                    $responseText = "💥 خودشه! برنده خوش‌شانس امروز تویی! 💥\n\n" .
                                    "🎁 تو برنده سورس رایگان *«{$freeSource['title']}»* شدی!\n\n" .
                                    "💡 *راهنما:* فایل سورس رو دانلود و از حالت فشرده خارج کن. معمولاً یک فایل راهنما داخلش هست که بهت کمک می‌کنه.\n\n" .
                                    "👇 اینم از جایزه‌ت:";
                    bot('editMessageText', ['chat_id' => $from_id, 'message_id' => $message_id, 'text' => $responseText, 'parse_mode' => 'MarkDown']);
                    
                    bot('sendDocument', [
                        'chat_id' => $from_id,
                        'document' => $freeSource['file_id'],
                        'caption' => '📂 *نام سورس:* '.$freeSource['title']."\n" .
                                     '➰ *آیدی سورس:* `'.$freeSource['id'].'`'."\n" .
                                     '📝 *زبان:* '.$freeSource['lang']."\n\n" .
                                     '📜 *توضیحات:* '.$freeSource['caption'],
                        'parse_mode' => 'MarkDown'
                    ]);

                    $pdo->exec("UPDATE users SET last_spin_time = '$currentTime' WHERE id = '$from_id' LIMIT 1");
                    $pdo->exec("INSERT INTO luckwheel_stats (user_id, prize_type, prize_value, spin_time) VALUES ('$from_id', 'source', '{$freeSource['id']}', '$currentTime')");
                    
                    $channel_message = "🥳 کاربر خوش‌شانس با آیدی عددی `$from_id` در گردونه شانس برنده سورس رایگان *«{$freeSource['title']}»* شد!";
                    SM('@PejvakEvents', $channel_message, null, json_encode(['inline_keyboard'=>[[['text'=>'منم میخوام شانسمو امتحان کنم! 🎲', 'url'=>'https://t.me/'.$bot_user]]]]), 'MarkDown');
                } else {
                    // Fallback prize if no free source is available
                    $wonCoins = 3;
                    $pdo->exec("UPDATE users SET coin = coin + $wonCoins, last_spin_time = '$currentTime' WHERE id = '$from_id' LIMIT 1");
                    bot('editMessageText', ['chat_id' => $from_id, 'message_id' => $message_id, 'text' => "🎁 واو! تو برنده یک سورس رایگان شدی، ولی متاسفانه در حال حاضر سورس رایگانی برای اهدا وجود نداره.\n\nبرای اینکه دلت نشکنه، *{$wonCoins} سکه* جایزه گرفتی! 😉", 'parse_mode' => 'MarkDown']);
                    $pdo->exec("INSERT INTO luckwheel_stats (user_id, prize_type, prize_value, spin_time) VALUES ('$from_id', 'coins', '$wonCoins', '$currentTime')");
                }
                $isFinalPrize = true;
                break;
        }
    }
    $pdo = null; exit();
}
    elseif($message=='❤️ محبوب ترین'){
        $query = $pdo->query("SELECT * FROM files WHERE like_count > '0' ORDER BY like_count DESC LIMIT 5")->fetchAll();
        if(count($query) > 0){
            $list .= "❤️ لیست 5 سورس محبوب در ربات از نطر تعداد لایک\n\n";
            foreach($query as $result){
                $id = $result['id'];
                $title = $result['title'];
                $like = $result['like_count'];
                $list .= "نام سورس : 📂 $title\n❤️ تعداد لایک : $like\n📥 دریافت سورس : /down_$id\n\n";
            }
            SM($from_id, $list."\n\n🆔 @".$channel['username'], $message_id, $key_best);
            $pdo = null;
        } else {
            SM($from_id, 'لیست موجود نیست ❗️', $message_id, $key_best);
            $pdo = null;
        }
    }

    elseif($message=='بیشترین دانلود 👍'){
        $query = $pdo->query("SELECT * FROM files WHERE down_count > '0' ORDER BY down_count DESC LIMIT 5")->fetchAll();
        if(count($query) > 0){
            $list .= "👍 لیست 5 سورس محبوب در ربات از نطر تعداد دانلود\n\n";
            foreach($query as $result){
                $id = $result['id'];
                $title = $result['title'];
                $down = $result['down_count'];
                $list .= "نام سورس : 📂 $title\n📥 تعداد دانلود : $down\n📥 دریافت سورس : /down_$id\n\n";
            }
            SM($from_id, $list."\n\n🆔 @".$channel['username'], $message_id, $key_best);
            $pdo = null;
        } else {
            SM($from_id, 'لیست موجود نیست ❗️', $message_id, $key_best);
            $pdo = null;
        }
    }

    elseif($message=='ویژه ها ⚜️'){
        $query = $pdo->query("SELECT * FROM files WHERE ads_type = 'vip' or ads_type = 'coin' ORDER BY like_count DESC LIMIT 5")->fetchAll();
        if(count($query) > 0){
            $list .= "❤️ لیست 5 سورس محبوب در ربات از نطر تعداد خرید(ویژه)\n\n";
            foreach($query as $result){
                $id = $result['id'];
                $title = $result['title'];
                if($result['ads_type']=='vip'){
                    $buys =  $result['buy_count'];
                }  else{
                    $buys =  $result['down_count'];
                }
                $list .= "نام سورس : 📂 $title\n⚜️ تعداد خرید : $buys\n📥 دریافت سورس : /down_$id\n\n";
            }
            SM($from_id, $list."\n\n🆔 @".$channel['username'], $message_id, $key_best);
            $pdo = null;
        } else {
            SM($from_id, 'لیست موجود نیست ❗️', $message_id, $key_best);
            $pdo = null;
        }
    }

    elseif($message=='💣 پرفروش ترین ها'){
        $query = $pdo->query("SELECT * FROM files WHERE ads_type = 'vip' ORDER BY buy_count DESC LIMIT 5")->fetchAll();
        if(count($query) > 0){
            $list .= "💵 لیست 5 سورس پر فروش ما \n\n ";
            foreach($query as $result){
                $id = $result['id'];
                $title = $result['title'];
                if($result['ads_type']=='vip'){
                    $buys =  $result['buy_count'];
                }
                $list .= "نام سورس : 📂 $title\n⚜️ تعداد فروش : $buys\n📥 دریافت سورس : /down_$id\n\n";
            }
            SM($from_id, $list."\n\n🆔 @".$channel['username'], $message_id, $key_best);
            $pdo = null;
        } else {
            SM($from_id, 'لیست موجود نیست ❗️', $message_id, $key_best);
            $pdo = null;
        }
    }

    elseif($message=='🗳 جدید ترین ها'){
        $query = $pdo->query("SELECT * FROM files ORDER BY id DESC LIMIT 3")->fetchAll();
        if(count($query) > 0){
            $list .= "🎈 لیست 3 سورس ثبت شده اخیر \n\n";
            foreach($query as $result){
                $id = $result['id'];
                $title = $result['title'];
                $like = $result['like_count'];
                $list .= "نام سورس : 📂 $title\n❤️ تعداد لایک : $like\n📥 دریافت سورس : /down_$id\n\n";
            }
            SM($from_id, $list."\n━ ━ ━ ━
👈🏻 جهت مشاهده لیست کامل سورس ها کافیست به « @".$channel['username']." » مراجعه کنید
👈🏻 شما میتوانید با افزایش سکه های حساب خود اقدام به دریافت سورس ها بدون محدودیت کنید

🆔 @".$channel['username'], $message_id, $toper_key);
            $pdo = null;
        } else {
            SM($from_id, 'لیست موجود نیست ❗️', $message_id, $menu);
            $pdo = null;
        }
    }



    elseif(strpos($message,"topterin_") !== false){
        bot('answerCallbackQuery',[
            'callback_query_id'=>$update->callback_query->id,
            'text'=>'✅ in progress request...',
            'show_alert'=>false
        ]);

        bot('deletemessage',[
            'chat_id'=>$from_id,
            'message_id'=>$message_id
        ]);
        $tid     = str_replace("topterin_","",$message);
        $explode = explode("_",$tid);


        $query = $pdo->query("SELECT * FROM files ORDER BY id DESC LIMIT $explode[0]")->fetchAll();
        $key_one = ($explode[0] ==10 ? "3" : "10");
        $key_one = ($explode[0] ==5 ? "3" : "5");
        $key_two = ($explode[0] ==5 ? "10" : "5");
        $key_two = ($explode[0] ==10 ? "3" : "10");
        if(count($query) > 0){

            $list .= "🎈 لیست $explode[0] سورس ثبت شده اخیر \n\n";
            foreach($query as $result){
                $id = $result['id'];
                $title = $result['title'];
                $like = $result['like_count'];
                $list .= "نام سورس : 📂 $title\n❤️ تعداد لایک : $like\n📥 دریافت سورس : /down_$id\n\n";
            }
            SM($from_id, $list."\n━ ━ ━ ━
👈🏻 جهت مشاهده لیست کامل سورس ها کافیست به « @".$channel['username']." » مراجعه کنید
👈🏻 شما میتوانید با افزایش سکه های حساب خود اقدام به دریافت سورس ها بدون محدودیت کنید

🆔 @".$channel['username'], $message_id, json_encode(['inline_keyboard'=>[
                [['text'=>"📊 دیدن $key_one سورس اخیر ",'callback_data'=>"topterin_$key_one"]],
                [['text'=>"📈 دیدن $key_two سورس اخیر",'callback_data'=>"topterin_$key_two"]],
                [['text'=>"📥 آخرین سورس ما",'url'=>"https://t.me/{$channel['username']}/$id"]]
            ]]));
            $pdo = null;

        } else {
            SM($from_id, 'لیست موجود نیست ❗️', $message_id, $menu);
            $pdo = null;
        }
    }

    elseif($message=='حساب من 👤'){
        $rial = $users['coin']  * $channel['tariff'];
        $rial = number_format($rial);

        SM($from_id, '⏺ اطلاعات حساب شما در '.$bot_name.'
⚠️ جهت ارسال سورس های خود برای ثبت در ربات کافیست به پشتیبانی مراجعه نمایید.

⚠️ جهت افزایش موجودی حساب خود از طریق دکمه (💰 افزایش سکه) اقدام نمایید.

👈 آیدی عددی شما : '."`$from_id`", $message_id, json_encode([
            'inline_keyboard'=>[
                [['text'=>'------👇اطلاعات مالی 👇------','callback_data'=>'JShow']],
                [['text'=>'«'.$users['coin'].'»','callback_data'=>'JShow'],['text'=>'💰 موجودی حساب ','callback_data'=>'JShow']],
                [['text'=>'«'.$users['buy_count'].'»','callback_data'=>'JShow'],['text'=>'💳 تعداد خرید انجام شده','callback_data'=>'JShow']],
                [['text'=>'💵 شما معادل '.$rial.' ریال سکه دارید!','callback_data'=>'JShow']],
                [['text'=>'------👇 اطلاعات فعالیت 👇------','callback_data'=>'JShow']],
                [['text'=>'«'.$users['down_count'].'»','callback_data'=>'JShow'],['text'=>'📥 تعداد فایلهای دریافتی ','callback_data'=>'JShow']],
                [['text'=>'«'.$users['like_count'].'»','callback_data'=>'JShow'],['text'=>'❤️ تعداد لایک انجام شده','callback_data'=>'JShow']],
                [['text'=>'«'.$users['subset'].'»','callback_data'=>'JShow'],['text'=>'👥 تعداد زیر مجموعه','callback_data'=>'JShow']],
               [['text'=>'«'.$users['daily_subset'].'»','callback_data'=>'JShow'],['text'=>'👥 زیرمجموعه روزانه','callback_data'=>'JShow']],


            ]
        ]), 'markdown');


        $pdo = null;
    }
    


   elseif($message=="پژواک پلاس ➕"){
    sm($from_id,
        "🍫 *به پژواک پلاس خوش آمدید!* ⚡️\n\n".
        "🎁 <b>چگونه نقره کسب کنید؟</b>\n".
        "با جمع‌آوری نقره می‌توانید آن را به سکه تبدیل کرده و برای دانلود سورس‌های دلخواه استفاده کنید!\n\n".
        "🎈 <b>مزایای ویژه:</b>\n".
        "✅ رایگان و آسان\n".
        "✅ خدمات عابر بانک (انتقال سکه به دیگران)\n\n".
        "⚡️ شروع به کسب نقره کنید و از مزایای فوق بهره‌مند شوید!",
        $message_id, $pejvak_club);
}

elseif($message=="❓ چگونه نقره بگیریم؟"){
    SM($from_id,
        "📈 *راهنمای کسب نقره*\n\n".
        "<b>روش کسب نقره:</b>\n".
        "1️⃣ از بخش زیرمجموعه‌گیری شروع کنید\n".
        "2️⃣ با جذب زیرمجموعه سکه به صورت آنی دریافت کنید\n".
        "3️⃣ در بخش برترین‌های ربات نمایش داده شوید\n\n".
        "🏆 <b>جایزه روزانه:</b>\n".
        "به 5 نفر برتر بر اساس بیشترین زیرمجموعه روزانه به صورت خودکار نقره تعلق می‌گیرد\n".
        "📌 نام شما در کانال @pejvakevents نمایش داده خواهد شد");
}

elseif($message=="📈 نقره‌های من"){
    $moadel = round($users['silver'] / 100);
    SM($from_id,
        "💎 *حساب نقره‌ای شما*\n\n".
        "<b>موجودی فعلی:</b>\n".
        "🪙 {$users['silver']} عدد نقره\n\n".
        "<b>معادل سکه:</b>\n".
        "💰 $moadel سکه\n\n".
        "⬇️ <b>اقدام بعدی:</b>\n".
        "از بخش تبدیل نقره به سکه می‌توانید موجودی خود را به سکه تبدیل کنید!");
}

elseif($message=="🔄 تبدیل نقره به سکه"){
    $moadel = round($users['silver'] / 100);
    if($users['silver'] >=100 ){
    SM($from_id,"🧧 شما {$users['silver']} عدد نقره دارید که معادل $moadel سکه است ، آیا میخواهید تبدیل کنید ؟",$message_id,$confirm_key);
     $pdo->query("update `users` set step='tabdeil' where id=$from_id LIMIT 1");
     $pdo = null;
}else{
    sm($from_id,"❌ نقره های شما کافی نیست حداقل 100نقره برای تبدیل نیاز است !\nنقره های شما :{$users['silver']}");
}
}

if($users['step']=="tabdeil" and $message=="بلـــــــی" and $message !="بازگشت ↪️"){
     $moadel = round($users['silver'] / 100);
    $ex = $moadel;
    sm($from_id,"✅  تبریک شما ، $ex عدد سکه دریافت کردید !",$message_id,$menu);
    // $newcoin = $users['silver'] - $users['silver'];
    
     $pdo->query("update `users` set coin=coin+$moadel where id=$from_id LIMIT 1");
     $pdo->query("update `users` set silver=0 where id=$from_id LIMIT 1");
    
    
}
    elseif($message=="💎 انتقال سکه"){
        $pdo->query("update `users` set step ='sendcoin254' where id=$from_id");
        SM($from_id, "+ آیدی عددی کسی که میخوای سکه بدی بهش رو بفرست اینجا 👇", $message_id, $back);
    }

    elseif($users['step'] == "sendcoin254" and $message !="بازگشت ↪️"){
        $rowCount = $pdo->query("SELECT id FROM users WHERE id = '$message'")->rowCount();
        if($message !=$from_id){
            if($rowCount > 0){

                $pdo->query("update `users` set step ='sendco-$message' where id=$from_id");
                $sender = bot('getchat',[
                    'chat_id'=>$message
                ])->result;
                $names = $sender->first_name;
                $max = $users['coin'] - 1;
                if($max >0){
                    SM($from_id, "👈 جهت انتقال به کاربر $names به آیدی $message ، تعداد سکه مورد نظر را ارسال بفرمایید :

💰 کل موجودی شما : {$users['coin']}                
🏦 موجودی قابل انتقال شما : $max

+ توجه حداکثر 10 سکه می توانید در هر بار انتقال دهید.", $message_id, $back);
                }else{
                    SM($from_id,"❌ متاسفانه شما سکه قابل انتقال کافی ندارید!
ابتدا موجودی خود را افزایش داده و سپس اقدام به انتقال سکه کنید .
                    
👈 موجودی شما : {$users['coin']}
👈 موجودی قابل انتقال :  $max",$message_id,$pejvak_club);
                }
            }else{
                sm($from_id,"❌ کاربر مورد نظر یافت نشد ، آیدی وارد شده اشتباه است یا کاربر هنوز عضو ربات نیست!",$message_id,$back);
            }
        }else{
            sm($from_id,"😐 داچ به خودت نمیتونی سکه انتقال بدی!
🙂 آیدی اون کسی که میخوای بفرستی براشو بده بهم حاجی",$message_id,$back);
        }
    }

    elseif(strpos($users['step'],"sendco-") !==false and $message!="بازگشت ↪️"){

        $user_id = explode('-', $users['step'])[1];

        if($message <=10){
            if($users['coin'] >=$message){
                $newcoin = $users['coin'] - $message;
                $max_send = $users['coin'] - 1;
                if($newcoin !==0){
                    // transfer to new owner
                    $pdo->query("update `users` set coin=$newcoin where id=$from_id LIMIT 1");

                    $pdo->query("update `users` set coin=coin+$message where id=$user_id LIMIT 1");

                    $pdo->query("update `users` set step='none' where id=$from_id LIMIT 1");
                    sm($from_id,"✅ مقدار $message سکه با موفقیت به کاربر $user_id منتقل گردید و تعداد $message سکه از حساب شما کسر شد!

✅ سکه های جدید شما : $newcoin",$message_id,$back);

                    sm($user_id,"✅ کاربر عزیز از طرف کاربر $from_id تعداد $message سکه به حساب شما واریز گردید!",$message_id,$back);
                    sm(-1001295833851,"✅ کاربر [$from_id](tg://user?id=$from_id) به کاربر [$user_id](tg://user?id=$user_id) تعداد $message سکه انتقال داد.",null,null,"markdown");

                }else{
                    sm($from_id,"❌ حداقل باید 1 سکه تو حسابت بمونه!

✅ تعداد سکه های وارد شده : $message
✅ سکه های شما : {$users['coin']}

✅ شما حداکثر می توانید $max_send  سکه انتقال دهید ، مقدار جدید را وارد کنید :",$message_id,$back);
                }
            }else{
                sm($from_id,"❌ سکه های شما کافی نیست!..
✅ تعداد سکه های وارد شده : $message
✅ سکه های شما : {$users['coin']}

✅ شما حداکثر می توانید {$users['coin']}  انتقال دهید ، مقدار جدید را وارد کنید :",$message_id,$back);
            }
        }else{
            sm($from_id,"❌ شما حداکثر می توانید 10 سکه انتقال دهید !
🗣 تعداد وارده شما : $message

مقدار جدید را تا 10 سکه وارد کنید :",$message_id,$back);
        }
    }
    elseif($message=='💰 افزایش سکه'){
        SM($from_id, '🔰 جهت افزایش سکه حساب خود یکی از گزینه های زیر را انتخاب کنید :', $message_id, $key_coin);
        $pdo = null;
    }
    #-------------------------------------------------------#
    elseif($message=="👮‍♀️ قانون اصلی"){
        sm($from_id,"👈 قانون 1 :
        
🤚 شما باید هر روز زیرمجموعه گیری کنید ، اگر زیرمجموعه گیری انجام ندهید اسم شما از لیست حذف خواهد شد، پس هر روز زیرمجموعه گیری کنید تا از جوایز نفیس هفتگی برخورد دار شوید.",$message_id,$topser_menu);
    }
    elseif($message=="123"){

        $quer = $pdo->query("SELECT * FROM `users` where coin >'0' ORDER BY coin  DESC LIMIT 20");

        foreach($quer as $result){
            $id = $result['id'];
            $coins = $result['coin'];
            $iop +=1;
            $ok .="$iop- 🗣آیدی : $id\n💰سکه : $coins\n\n";

        }

        sm($from_id,$ok);
    }
    
    //-------------------
    
    
    
    //----------------------
    elseif($message=="برترین ها 🌟"){

        // $date = "2022/08/78";
        $logs = json_decode(file_get_contents("data/logs.json"), true);
        $query = $pdo->query("SELECT * FROM users WHERE daily_subset > '0' AND last_subset='$date'  ORDER BY daily_subset DESC LIMIT 5")->fetchAll();
        $querd = $pdo->query("SELECT * FROM users WHERE daily_subset > '0' AND last_subset='$date' ORDER BY daily_subset DESC ")->fetchAll();

        foreach($querd as $res){ // Get User Rank.
            $i = $i + 1;
            if($res['id']==$from_id ){
                $my_rank = $i;
            }
        }
        if($my_rank=="" || $my_rank==NULL){$my_rank = "🙂 شرکت نکردی!";}

        if(count($query) > 0){

            $list .= "❤️ لیست 5 نفر از ممبرای گل که بیشترین زیر مجموعه ها رو آوردن :\n\n";
            $i = 0;
            foreach($query as $result){

                $i = $i + 1;
                $id    = $result['id'];
                if($result['last_subset'] =="$date"){

                    switch($i){case'1':$nf = 'اول';break;case'2' : $nf = 'دوم';break;case'3':$nf = 'سوم';break;case'4': $nf='چهارم'; break;case'5':$nf='پنجم';break;}

                    $subset= $result['daily_subset'];

                    $list .= "💰 نفر $nf [$id](tg://user?id=$id) \n👥 تعداد زیرمجموعه : *$subset*\n\n"."➖ ➖ ➖ ➖ ➖ ➖ ➖ ➖ ➖➖ ➖ ➖"."\n\n";


                }
            }
            bot('sendmessage',['chat_id'=>$from_id,'text'=>$list."\n\n🔰 تاریخ برگزاری چالش بعدی : {$logs['next_gift_weekly']}\n🏵 رتبه شما : $my_rank\n\n🤨 تو هم میخوای اسمت توی لیست باشه؟\n😎کاری نداره؟! کافیه از بخش زیرمجموعه گیری👥 دوستاتو دعوت کنی!\n\n💎 جایزه هر زیرمجموعه : ".$channel['subset_coin']." سکه\n\n👇 « قانون اصلی را بخوانید »👇\n\n🆔 @".$channel['username'],'parse_mode'=>"markdown",/*'reply_to_message_id'=>$message_id,*/'reply_markup'=>$topser_menu]);

            $pdo = null;

        } else {
            SM($from_id, 'لیست موجود نیست ❗️', $message_id, $topser_menu);
            $pdo = null;
        }
    }
    #-------------------------------------------------------#
    elseif ($message=='🎉 تاریخچه برندگان') {
        $query = $pdo->query("SELECT * FROM `history_subset` LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if(count($query) > 0){

            SM($from_id,'💎 تاریخ برگزاری : '.$query['date'].'

🦈 برندگان : 
'.$query['data'],$message_id,json_encode([
                'inline_keyboard'=>[
                    [['text'=>'🎁 مشاهده لیست در کانال','url'=>'https://t.me/'.$channel['username'].'/'.$query['msg_id']]]
                ]
            ]));
        }else {
            SM($from_id, 'لیست موجود نیست ❗️', $message_id, $menu);
            $pdo = null;
        }
    }

    #-------------------------------------------------------#
    elseif($message=='زیرمجموعه گیری👥'){
        $msg_id = bot('sendPhoto', [
            'chat_id'=>$from_id,
            'photo'=>new CURLFile('data/banner.jpg'),
            'caption'=>'📢 کانال '.$brand_name.' مرجع انواع سورس کد های مختلف

✅ سورس انواع ربات ها , قالب ها و اسکریپت های تست شده و حرفه ای
🌟 هر روز کلی سورس کد و اسکریپت منتظر شماست !

👇🏻 برای دریافت سورس ها کافیه از این ربات فوق العاده استفاده کنی

t.me/'.$bot_user.'?start=inv_'.$from_id
        ])->result->message_id;
        SM($from_id, '👆🏻 بنر بالا حاوی لینک دعوت شما به ربات است
 
🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید به ازای هر نفر {'.$channel['subset_coin'].'} سکه دریافت کنید
☑️ پس با زیرمجموعه گیری به راحتی میتوانید سکه حساب خود را رایگان! افزایش دهید

❗️ توجه کنید که زیر مجموعه های شما برای دریافت سکه رایگان حتما باید در کانال ما عضو شوند

💰 موجودی حساب : '.$users['coin'].' سکه
👥 تعداد زیر مجموعه : '.$users['subset'].' نفر', $msg_id, $key_coin);
        $pdo = null;
    }

    elseif($message=='خرید سکه 💸'){


     



        $key_pardakht = json_encode([ 'keyboard'=>[
            [['text'=>"💳 کارت به کارت"],['text'=>"💸 درگاه آنلاین پرداخت"]],
            [['text'=>"💰 خرید با ارز دیجیتال"]],
            [['text'=>'بازگشت ↪️']]
        ],'resize_keyboard'=>true]);

        if($users['phone_number']!=0){

//             SM($from_id, '☑️ تمامی پرداخت ها به صورت اتوماتیک بوده و پس از تراکنش موفق مبلغ آن به سکه حساب شما در ربات افزوده خواهد شد .

// 👇🏻 پرای پرداخت کافیست از دکمه زیر استفاده کنید️', $message_id,$paykey);



SM($from_id,"📍 کاربر محترم ، لطفا شیوه پرداخت مد نظرخود را انتخاب کنید :",$message_id,$key_pardakht);

            $pdo = null;
        } else {



            $pdo->exec("UPDATE users SET step = 'share_number' WHERE id = '$from_id' LIMIT 1");
            SM($from_id, 'کاربر گرامی جهت ادامه فعالیت شما در ربات و تایید هویت ایرانی لازم به اشتراک شماره شما میباشد ‼️
لطفا با کلیدبُرد زیر اقدام به تایید هویت خود کنید 👇👇', $message_id, $request);
            $pdo = null;
        }
    }
    
elseif($message=="💸 درگاه آنلاین پرداخت"){
    
      $randomcode  =  uniqid().rand(1000,9999);
      $randomcode2 =  uniqid().rand(1000,9999);
      $randomcode3 =  uniqid().rand(1000,9999);
      $payment = $pdo->query("SELECT * FROM re_payments WHERE id = '{$randomcode}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

     try{
      $pdo->exec("INSERT INTO re_payments (`file`,`id`,`amount`,`desc`,`type`,`fromid`,`time`) VALUES ('0','$randomcode','50000','خرید موجودی $bot_name','coin','$from_id','$timering')");
      $pdo->exec("INSERT INTO re_payments (`file`,`id`,`amount`,`desc`,`type`,`fromid`,`time`) VALUES ('0','$randomcode3','200000','خرید موجودی $bot_name','coin','$from_id','$timering')");
      $pdo->exec("INSERT INTO re_payments (`file`,`id`,`amount`,`desc`,`type`,`fromid`,`time`) VALUES ('0','$randomcode2','125000','خرید موجودی $bot_name','coin','$from_id','$timering')");

     } catch(PDOException $e){
        file_put_contents('e.txt',$e->getMessage());
      die();
  }

            SM($from_id, '☑️ تمامی پرداخت ها به صورت اتوماتیک بوده و پس از تراکنش موفق مبلغ آن به سکه حساب شما در ربات افزوده خواهد شد .

👇🏻 پرای پرداخت کافیست از دکمه زیر استفاده کنید️', $message_id,$paykey);
// sm($from_id,"این بخش غیرفعال است.");


}
elseif($message=="💳 کارت به کارت"){
//     $pdo->query("update `users` set step ='card-to-card' where id=$from_id");

//     sm($from_id,"❗️ قیمت هر یک عدد سکه در حال حاضر $toman_tariff  تومان است.
// ❗️ شما مجاز به خرید بین 5 سکه تا 500 سکه هستید!

// 🪙 لطفا تعداد سکه های مد نظر خود را ارسال بفرمایید :",$message_id,$back);
// }
//     elseif($users['step']=='card-to-card' and $message!='بازگشت ↪️'){
//         $pdo->query("update `users` set step ='none' where id=$from_id");

//         if($message <=500){
//             $price = $message * $toman_tariff ;
// $price = number_format($price);
//             sm($from_id,"✅ فاکتور شما ساخته شد!

// ⭕️ خرید   تعداد $message سکه به مبلغ $price به صورت کارت به کارت :

// 🔻لطفا مبلغ $price تومان را به شماره کارت ذیل واریز کرده و رسید آن را در پشتیبانی ربات ارسال فرمایید ، سپس بلافاصله حساب شما شارژ خواهد گردید!
 
//  6280231344166672
// به نام مهدی اسکندری",$message_id,$menu);
sm($from_id,"برای خرید به آیدی @after_world مراجعه کنید.");

        }
    

    // elseif($message=="💸 درگاه آنلاین پرداخت"){
    //     $key_pardakht = json_encode([ 'keyboard'=>[
    //         [/*['text'=>"💳 کارت به کارت"]*/['text'=>"💸 درگاه آنلاین پرداخت"]],
    //         [['text'=>"💰 خرید با ارز دیجیتال"]],
    //         [['text'=>'بازگشت ↪️']]
    //     ],'resize_keyboard'=>true]);

    //     sm($from_id,"درگاه پرداخت به علت مشکلات فنی غیرفعال می باشد ، لطفا برای پرداخت از دیگر گزینه ها استفاده کنید!",$message_id, $key_pardakht );
    // }


    elseif($message == "pay_1"){
        bot('answerCallbackQuery',[
            'callback_query_id'=>$update->callback_query->id,
            'text'=>'⭐️ در حال ایجاد فاکتور پرداخت...',
            'show_alert'=>false
        ]);

        $randomcode  =  uniqid().rand(1111,9999);
        $pay_1 = $channel['tariff'] * 10;
        try{
            $pdo->exec("INSERT INTO re_payments (`file`,`id`,`amount`,`desc`,`type`,`fromid`,`time`) VALUES ('0','$randomcode','$pay_1','خرید موجودی $bot_name','coin','$from_id','$timering')");
        } catch(PDOException $e){
            bot('answerCallbackQuery',[
                'callback_query_id'=>$update->callback_query->id,
                'text'=>'❌ خطا در ثبت فاکتور! لطفاً دوباره تلاش کنید.',
                'show_alert'=>true
            ]);
            error_log("خطا در ثبت فاکتور pay_1: " . $e->getMessage());
            $pdo = null;
            exit();
        }
        
        $pay_1_formatted = number_format($pay_1);
        $payment_text = "💎 جهت ادامه خرید روی دکمه زیر بزنید و مستقیما وارد درگاه می شوید.\n📍 پس از پرداخت مستقیما سکه ها به حساب شما واریز خواهد شد!\n👇 جهت ادامه  👇";
        $payment_keyboard = json_encode([
            'inline_keyboard'=>[
                [['text'=>"⭐️ خرید 10 سکه به مبلغ $pay_1_formatted ریال",'url'=>"{$channel['domin']}/PayLink/request.php?payment=$randomcode"]],
            ]
        ]);
        
        // حذف پیام قدیمی و ارسال پیام جدید
        @bot('deletemessage', ['chat_id'=>$from_id, 'message_id'=>$message_id]);
        
        // ارسال پیام جدید با لینک پرداخت
        $send_result = SM($from_id, $payment_text, null, $payment_keyboard);
        if (!$send_result || !$send_result->ok) {
            error_log("خطا در ارسال پیام pay_1: " . json_encode($send_result));
            // تلاش مجدد با bot مستقیم
            bot('sendMessage', [
                'chat_id'=>$from_id,
                'text'=>$payment_text,
                'parse_mode'=>'html',
                'disable_web_page_preview'=>true,
                'reply_markup'=>$payment_keyboard
            ]);
        }
        
        $pdo = null;
    }

    elseif($message == "pay_2"){
        bot('answerCallbackQuery',[
            'callback_query_id'=>$update->callback_query->id,
            'text'=>'⭐️ در حال ایجاد فاکتور پرداخت...',
            'show_alert'=>false
        ]);
        
        $randomcode  =  uniqid().rand(1111,9999);
        $pay_1 = $channel['tariff'] * 25;
        try{
            $pdo->exec("INSERT INTO re_payments (`file`,`id`,`amount`,`desc`,`type`,`fromid`,`time`) VALUES ('0','$randomcode','$pay_1','خرید موجودی $bot_name','coin','$from_id','$timering')");
        } catch(PDOException $e){
            bot('answerCallbackQuery',[
                'callback_query_id'=>$update->callback_query->id,
                'text'=>'❌ خطا در ثبت فاکتور! لطفاً دوباره تلاش کنید.',
                'show_alert'=>true
            ]);
            error_log("خطا در ثبت فاکتور pay_2: " . $e->getMessage());
            $pdo = null;
            exit();
        }
        
        $pay_1_formatted = number_format($pay_1);
        $payment_text = "💎 جهت ادامه خرید روی دکمه زیر بزنید و مستقیما وارد درگاه می شوید.\n📍 پس از پرداخت مستقیما سکه ها به حساب شما واریز خواهد شد!\n👇 جهت ادامه  👇";
        $payment_keyboard = json_encode([
            'inline_keyboard'=>[
                [['text'=>"⭐️ خرید 25 سکه به مبلغ $pay_1_formatted ریال",'url'=>"{$channel['domin']}/PayLink/request.php?payment=$randomcode"]],
            ]
        ]);
        
        // حذف پیام قدیمی و ارسال پیام جدید
        @bot('deletemessage', ['chat_id'=>$from_id, 'message_id'=>$message_id]);
        
        // ارسال پیام جدید با لینک پرداخت
        $send_result = SM($from_id, $payment_text, null, $payment_keyboard);
        if (!$send_result || !$send_result->ok) {
            error_log("خطا در ارسال پیام pay_2: " . json_encode($send_result));
            // تلاش مجدد با bot مستقیم
            bot('sendMessage', [
                'chat_id'=>$from_id,
                'text'=>$payment_text,
                'parse_mode'=>'html',
                'disable_web_page_preview'=>true,
                'reply_markup'=>$payment_keyboard
            ]);
        }

        $pdo = null;
    }


    elseif($message == "pay_3"){
        bot('answerCallbackQuery',[
            'callback_query_id'=>$update->callback_query->id,
            'text'=>'⭐️ در حال ایجاد فاکتور پرداخت...',
            'show_alert'=>false
        ]);
        
        $randomcode  =  uniqid().rand(1111,9999);
        $pay_1 = $channel['tariff'] * 40;
        try{
            $pdo->exec("INSERT INTO re_payments (`file`,`id`,`amount`,`desc`,`type`,`fromid`,`time`) VALUES ('0','$randomcode','$pay_1','خرید موجودی $bot_name','coin','$from_id','$timering')");
        } catch(PDOException $e){
            bot('answerCallbackQuery',[
                'callback_query_id'=>$update->callback_query->id,
                'text'=>'❌ خطا در ثبت فاکتور! لطفاً دوباره تلاش کنید.',
                'show_alert'=>true
            ]);
            error_log("خطا در ثبت فاکتور pay_3: " . $e->getMessage());
            $pdo = null;
            exit();
        }
        
        $pay_1_formatted = number_format($pay_1);
        $payment_text = "💎 جهت ادامه خرید روی دکمه زیر بزنید و مستقیما وارد درگاه می شوید.\n📍 پس از پرداخت مستقیما سکه ها به حساب شما واریز خواهد شد!\n👇 جهت ادامه  👇";
        $payment_keyboard = json_encode([
            'inline_keyboard'=>[
                [['text'=>"⭐️ خرید 40 سکه به مبلغ $pay_1_formatted ریال",'url'=>"{$channel['domin']}/PayLink/request.php?payment=$randomcode"]],
            ]
        ]);
        
        // حذف پیام قدیمی و ارسال پیام جدید
        @bot('deletemessage', ['chat_id'=>$from_id, 'message_id'=>$message_id]);
        
        // ارسال پیام جدید با لینک پرداخت
        $send_result = SM($from_id, $payment_text, null, $payment_keyboard);
        if (!$send_result || !$send_result->ok) {
            error_log("خطا در ارسال پیام pay_3: " . json_encode($send_result));
            // تلاش مجدد با bot مستقیم
            bot('sendMessage', [
                'chat_id'=>$from_id,
                'text'=>$payment_text,
                'parse_mode'=>'html',
                'disable_web_page_preview'=>true,
                'reply_markup'=>$payment_keyboard
            ]);
        }

        $pdo = null;
    }
    elseif($message == "pay_event"){
        bot('answerCallbackQuery',[
            'callback_query_id'=>$update->callback_query->id,
            'text'=>'⭐️ در حال ایجاد فاکتور پرداخت...',
            'show_alert'=>false
        ]);
        
        $randomcode  =  uniqid().rand(1111,9999);
        $pay_1 = 399000;
        try{
            $pdo->exec("INSERT INTO re_payments (`file`,`id`,`amount`,`desc`,`type`,`fromid`,`time`) VALUES ('0','$randomcode','$pay_1','خرید موجودی $bot_name','coin','$from_id','$timering')");
        } catch(PDOException $e){
            bot('answerCallbackQuery',[
                'callback_query_id'=>$update->callback_query->id,
                'text'=>'❌ خطا در ثبت فاکتور! لطفاً دوباره تلاش کنید.',
                'show_alert'=>true
            ]);
            error_log("خطا در ثبت فاکتور pay_event: " . $e->getMessage());
            $pdo = null;
            exit();
        }

        $payment_text = "💎 جهت ادامه خرید روی دکمه زیر بزنید و مستقیما وارد درگاه می شوید.\n📍 پس از پرداخت مستقیما سکه ها به حساب شما واریز خواهد شد!\n👇 جهت ادامه  👇";
        $payment_keyboard = json_encode([
            'inline_keyboard'=>[
                [['text'=>"🔥🔥 خرید 129 سکه فقط 39,900 تومان 🔥🔥",'url'=>"{$channel['domin']}/PayLink/request.php?payment=$randomcode"]],
            ]
        ]);
        
        // حذف پیام قدیمی و ارسال پیام جدید
        @bot('deletemessage', ['chat_id'=>$from_id, 'message_id'=>$message_id]);
        
        // ارسال پیام جدید با لینک پرداخت
        $send_result = SM($from_id, $payment_text, null, $payment_keyboard);
        if (!$send_result || !$send_result->ok) {
            error_log("خطا در ارسال پیام pay_event: " . json_encode($send_result));
            // تلاش مجدد با bot مستقیم
            bot('sendMessage', [
                'chat_id'=>$from_id,
                'text'=>$payment_text,
                'parse_mode'=>'html',
                'disable_web_page_preview'=>true,
                'reply_markup'=>$payment_keyboard
            ]);
        }

        $pdo = null;
    }


    elseif($message=="pay_select"){

        bot('deletemessage',['chat_id'=>$from_id,'message_id'=>$message_id]);

        $pdo->exec("UPDATE users SET step = 'select_pay_step' WHERE id = '$from_id' LIMIT 1");


        bot('sendmessage', [
            'chat_id'=>$from_id,
            'text'=>"🙂 کاربر عزیز تعداد سکه های مورد نظرتون رو وارد کنید : 

👍 در جریان باش که سکه هات باید از 3 تا 450 سکه باشه!

💰 دوست عزیز تعرفه هر سکه {$channel['tariff']} ریال هست (معادل $toman_tariff تومان)

👈 خب حالا تعداد سکه ها رو وارد کن : ",
            'message_id'=>$message_id,
            'reply_markup'=>$back
        ]);

    }

    elseif($users['step']=="select_pay_step" and !in_array($message, ['بازگشت ↪️', '/start'])){

        if($message >=3 and $message <=450){

            $amount_coin =  $channel['tariff'] * $message;

            $randomcode  =  uniqid().rand(1111,9999);

            try{
                $pdo->exec("INSERT INTO re_payments (`file`,`id`,`amount`,`desc`,`type`,`fromid`,`time`) VALUES ('0','$randomcode','$amount_coin','خرید موجودی $bot_name','coin','$from_id','$timering')");
            } catch(PDOException $e){
                file_put_contents('e.txt',$e->getMessage());
                die();
            }

            $amount_coin = number_format($amount_coin);
            $pdo->exec("UPDATE users SET step = 'none' WHERE id = '$from_id' LIMIT 1");
            SM($from_id,"💰 فاکتور شما به مبلغ $amount_coin ریال صادر گردید!

💎 مشخصات فاکتور : 
💵 به مبلغ : <b>$amount_coin</b> ریال
👈 به تعداد : <b>$message</b> سکه
👈 در تاریخ : ".date("Y/m/d H:i:s")."

👇 جهت ادامه پرداخت روی دکمه زیر بزنید :",$message_id,json_encode(['inline_keyboard'=>[

                [['text'=>"⭐️ خرید $message سکه به قیمت $amount_coin ریال",'url'=>"{$channel['domin']}/PayLink/request.php?payment=$randomcode"]],

            ]
            ]));

        }else{
            sm($from_id,"🪓  تعداد وارده باید از 3 تا 450 سکه باشد!
➕ تعداد وارده شما : $message

👇 تعداد سکه های جدید رو وارد کن :",$message_id,$back);
        }
    }

    elseif($users['step']=='share_number' and !in_array($message, ['بازگشت ↪️', '/start'])){
        if(isset($update->message->contact)){
            if($update->message->contact->user_id == $from_id){
                $phone_number = str_replace(['+',' ','(',')'], null ,$update->message->contact->phone_number);
                if(substr($phone_number, 0, -10)=='98'){
                    SM($from_id, 'شماره تلفن [ +'.$phone_number.' ] با موفقیت تایید شد✅', $message_id, $menu);
                    $pdo->exec("UPDATE users SET step = 'NULL', phone_number = '$phone_number' WHERE id = '$from_id' LIMIT 1");
                    // bot('SendContact',[
                    //     'chat_id'=>end($channel_logs),
                    //     'first_name'=>$update->message->from->first_name,
                    //     'phone_number'=>$phone_number
                    // ]);
                    // $pdo = null;
                } else {
                    $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
                    SM($from_id, 'شماره شما مالکیت ایرانی ندارد و اجازه خرید را ندارید ‼️', $message_id, $menu);
                    // $pdo = null;
                }
            } else {
                $pdo->exec("UPDATE users SET step = 'share_number' WHERE id = '$from_id' LIMIT 1");
                SM($from_id, 'لطفا فقط با کلید بُرد زیر اقدام به تایید هویت خود کنید 👇👇', $message_id, $request);
                // $pdo = null;
            }
        } else {
            $pdo->exec("UPDATE users SET step = 'share_number' WHERE id = '$from_id' LIMIT 1");
            SM($from_id, 'فقط مخاطب مورد تایید ربات میباشد لطفا از ارسال فایل متفرقه خودداری فرمایید ‼', $message_id, $request);
            
        }
        $pdo = null;
    }

    elseif($message=="💰 خرید با ارز دیجیتال"){
        $exmp = ($toman_tariff * 10) *1.25;
        $toman_tariff = $toman_tariff *1.25;
        sm($from_id,"📍 برای خرید سکه به صورت ترون به آدرس زیر واریز کنید و لینک انتقال رو به بخش پشتیبانی ربات ارسال کنید :

🎈  TRX : `TSvYVLwTJf9ZawiWkwK2uX1S29sA8sy1aT`

📍 توجه داشته باشید هر سکه برابر با $toman_tariff تومان است.
📍 توجه خرید سکه بواسطه ارز دیجیتال 25% گرانتر از خرید ریالی است.(علت : کارمز های انتقال و برداشت از صرافی)

💡 برای مثال 10 سکه برابر با $exmp  تومان است.",$message_id, $key_pardakht,"markdown" );
    }
    
    elseif($message=='پشتیبانی 🆘'){
    //     $pdo->exec("UPDATE users SET step = 'online_support' WHERE id = '$from_id' LIMIT 1");
    //     SM($from_id, '➕ پیامت رو بفرست هر سوالی چیزی داری میتونی بپرسی :'."\n\n".'✔️ راستی عکس ، فیلم ، فایل ، متن و ... رو می تونی بفرستی برامون!', $message_id, $back);
    //     $pdo = null;
    // }
$welcome_message = "⚠️ قوانین ثبت تیکت :\n\n" .
                           "هر کاربر فقط یک تیکت فعال می‌تواند داشته باشد!\n\n" .
                           "تا زمانی که مدیر پاسخ نداده , ارسال پیام جدید امکان‌پذیر نیست , بنابراین لطفاً تمام جزئیات را در یک پیام کامل بنویسید!\n\n" .
                     
                           "دپارتمان مربوطه را با دقت انتخاب کنید تا تیکت به واحد درست ارجاع شود!";
        
        // استفاده از WEB_APP_URL از config.php (خوانده شده از .env)
        $ticket_url = rtrim(WEB_APP_URL, '/') . '/index.html';
        $keyboard = ['inline_keyboard' => [[['text' => '🎫 تیکت‌ها', 'web_app' => ['url' => $ticket_url]]]]];
        sm($from_id, $welcome_message, $message_id,json_encode($keyboard));
}
    elseif($users['step']=='online_support' and !in_array($message, ['بازگشت ↪️', '/start'])){

        for($i=0; $i< count($Devs); $i++){
            bot('copymessage',[
                'chat_id'=>$Devs[$i],
                'from_chat_id'=>$from_id,
                'message_id'=>$message_id,

            ]);
            bot('sendmessage',[
                'chat_id'=>$Devs[$i],
                'text'=>"user id : `$from_id`",
                'disable_notification'=>true,
                'parse_mode'=>"MARKDOWN"
            ]);
            SM($Devs[$i], "شما یک پیام دارید جدید دریافت کردید !\n\n از طرف : <a href='tg://user?id=$from_id'>".$update->message->from->first_name."</a>", null, json_encode(['inline_keyboard'=>[
                [['text'=>'پاسخ به کاربر🗣','callback_data'=>'Answer_'.$from_id],['text'=>'بلاک کردن✖️','callback_data'=>'Block_'.$from_id]],
                [['text'=>'رد پیام❌','callback_data'=>'delmsg']]
            ]]));
        }



        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, 'پیام شما به دست مدیر رسید ✔️', $message_id, $back);
        $pdo = null;
    }

    elseif($message=='delmsg' and in_array($from_id, $Devs)){
        bot('answerCallbackQuery',[
            'callback_query_id'=>$update->callback_query->id,
            'text'=>'با موفقیت رد شد !',
            'show_alert'=>false
        ]);
        bot('deletemessage',[
            'chat_id'=>$from_id,
            'message_id'=>$message_id
        ]);
        $pdo = null;
    }

    elseif(strpos($message, 'Answer_') !== false and in_array($from_id, $Devs)){
        $user_id = explode('_', $message)[1];
        $pdo->exec("UPDATE users SET step = 'SendPasokh_$user_id' WHERE id = '$from_id' LIMIT 1");
        sm($user_id,"😃 پیامت خوند شد!\nبزودی ادمین بهت جواب میده😉");
        SM($from_id, "شما در حال چت با کاربر : <a href='tg://user?id=$user_id'>$user_id</a> هستید!\nپیام خود را در قالب یک متن ارسال کنید :", $message_id, $back);

        $pdo = null;
    }

    elseif(strpos($users['step'], 'SendPasokh_') !== false and !in_array($message, ['بازگشت ↪️', '/start'])){
        $user_id = explode('_', $users['step'])[1];
        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");

        SM($user_id, '🤚 پیامی از طرف مدیریت  دارید ! '."\n\n".'👈 متن پیام به شرح زیر :'." \n\n"."<i>$message</i>");
        SM($from_id, "پیام شما به کاربر :  <a href='tg://user?id=$user_id'>$user_id</a> رسید !", $message_id, $back);
        $pdo = null;
    }

    elseif(strpos($message, 'Block_') !== false and in_array($from_id, $Devs)){
        $user_id = explode('_', $message)[1];
        $query = $pdo->query("SELECT id,block FROM users WHERE id = '$user_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if(isset($query['id'])){
            if($query['block']==0){
                $pdo->exec("UPDATE users SET block = '1' WHERE id = '$user_id' LIMIT 1");
                SM($from_id, 'کاربر مورد نظر با موفقیت بلاک شد!', $message_id, $back);
                SM($user_id, 'شما توسط مدیران ربات بلاک شدید!', null, $remove);
                $pdo = null;
            } else {
                SM($from_id, 'کاربر مورد نظر از قبل بلاک میباشد !', $message_id, $back);
                $pdo = null;
            }
        } else {
            SM($from_id, 'چنین کاربری عضو ربات نیست!', $message_id, $back);
            $pdo = null;
        }
    }


    elseif($message=='📚 راهنـما'){
        SM($from_id, '📢 کانال '.$brand_name.' مرجع انواع سورس کد های مختلف
 
📂 بانک انواع سورس کد های مختلف به صورت کاملا تست شده.
✅ سورس انواع ربات ها , قالب ها و اسکریپت های تست شده و حرفه ای.

☑️ با ما همراه باشید و مارو به دوستاتون معرفی کنید 
🌟 هر روز کلی سورس کد و اسکریپت منتظر شماست !

👈🏻 جهت مشاهده لیست کامل سورس ها کافیست به « @'.$channel['username'].' » مراجعه کنید.
👈🏻 جهت ارسال سورس خود به کانال کافیست به پشتیبانی مراجعه کنید.

👈🏻 با دریافت هر سورس که ظرفیت دانلود رایگان آن به پایان رسیده است 1 سکه از شما کسر خواهد شد.
👈🏻 شما میتوانید با افزایش سکه های حساب خود اقدام به دریافت سورس ها بدون محدودیت کنید.

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید به ازای هر نفر {'.$channel['subset_coin'].'} سکه دریافت کنید.
❌ زیرمجموعه گیری فیک عواقب سنگینی دارد و غیرمجاز است!
⚠️ جهت افزایش سکه به مبلغ دلخواه به صورت آنلاین میتوانید از خرید سکه استفاده کنید.

👈🏻 تعرفه هر 1 سکه 500  تومان است!
👈🏻 با مراجعه به بخش ویژه ها میتوانید سورس های حرفه ای و تهیه شده توسط '.$brand_name.' را دریافت کنید.
👈🏻 سورس های ویژه , توسط '.$brand_name.' تهیه شده اند و دارای تضمین کارکرد و راهنمای نصب و پشتیبانی هستند.

❗️ توجه کنید که برای سورس های ویژه نمیتوانید از سکه استفاده کنید , میتوانید با خرید مستقیم سورس را دریافت کنید.

📍در صورت داشتن هر نوع پیشنهاد یا انتقاد از دکمه پشتیبانی استفاده کنید .

🤖 ارتباط با ما و دریافت سورس ها : @'.$bot_user.'
👈🏻 کانال '.$brand_name.' : @'.$channel['username'], $message_id, $menu);
        $pdo = null;
    }
    elseif(strpos($message, '/down_') !== false){
        $id = str_replace('/down_', null, $message);
        $query = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if(isset($query['id'])){
            if($query['ads_type']=='free'){
                bot('sendPhoto', [
                    'chat_id'=>$from_id,
                    'photo'=>$query['cover'],
                    'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : '.$query['id'].'
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر :
'.$query['caption'].'

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
                    'parse_mode'=>'html',
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
                        [['text'=>'📮 دریافت سورس', 'url'=>'https://t.me/'.$bot_user.'?start=file_'.$id]],
                        [['text'=>'📊 آمار دانلود بصورت رایگان : '.$query['down_count'].' از '.$query['limits'], 'callback_data'=>'DNLoad']],
                        [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'flike_'.$id], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]],
                        [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$id"]],
                                    

                    ],])
                ]);
                $pdo = null;
            }
            if($query['ads_type']=='vip'){
                if($users['phone_number']!=0){
                    $randomcode  =  uniqid().rand(1000,9999);
                    $pdo->exec("INSERT INTO re_payments (`file`,`id`,`amount`,`desc`,`type`,`fromid`,`time`) VALUES ('$id','$randomcode','{$query['amount']}','خرید سورس {$query['id']} در $bot_name','source','$from_id','$timering')");

                    bot('sendPhoto', [
                        'chat_id'=>$from_id,
                        'photo'=>$query['cover'],
                        'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : <code>'.$query['id'].'</code>
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر :
'.$query['caption'].'

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
                        'parse_mode'=>'html',
                        'reply_markup'=>json_encode(['inline_keyboard'=>[
                            [['text'=>'💰قیمت '.number_format($query['amount'] / 10).' تومان'.' | '.number_format($query['amount']).' ریال', 'url'=>"https://codezed.ir/Bots/Pejvak-MEO/PayLink/request.php?payment=$randomcode"]],

                        ],])
                    ]);
                    $pdo = null;
                }else{
                    $pdo->exec("UPDATE users SET step = 'share_number' WHERE id = '$from_id' LIMIT 1");
                    SM($from_id, 'کاربر گرامی جهت ادامه فعالیت شما در ربات و تایید هویت ایرانی لازم به اشتراک شماره شما میباشد ‼️
لطفا با کلیدبُرد زیر اقدام به تایید هویت خود کنید 👇👇', $message_id, $request);
                    $pdo = null;
                }
            }
            if($query['ads_type']=='coin'){
                $msg_id = bot('sendPhoto', [
                    'chat_id'=>$from_id,
                    'photo'=>$query['cover'],
                    'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : <code>'.$query['id'].'</code>
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر :
'.$query['caption'].'

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
                    'parse_mode'=>'html',
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
                        [['text'=>'دریافت سورس', 'url'=>"https://t.me/{$channel['bot_id']}?start=file_".$query['id']]],
                        [['text'=>'قیمت '.$query['amount'].' سکه','callback_data'=>'BuyBTN']],
                        [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'cclike_'.$id], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]],
                        [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$id"]],
                                    

                    ],])
                ]);
            }
        } else {
            SM($from_id, '❌ خطا ، محصول مورد نظر شما یافت نشد', $message_id, $menu);
            $pdo = null;
        }
    }
 elseif(strpos($message, 'slike_') !== false){
        $id = str_replace('slike_', null, $message);
        $query = $pdo->query("SELECT * FROM likes WHERE user_id = '$from_id' AND file_id = '$id' LIMIT 1")->rowCount();
        if($query < 1){
            bot('answerCallbackQuery', [
                'callback_query_id'=>$update->callback_query->id,
                'text' => '❤️ لایک شما با موفقیت انجام شد',
                'show_alert' =>true
            ]);
            $files = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $like = $files['like_count'] + 1;
            $pdo->exec("UPDATE files SET like_count = '$like' WHERE id = '$id' LIMIT 1");
            $pdo->exec("INSERT INTO likes (like_gift,user_id, file_id) VALUES (1,'$from_id', '$id')");
            $lkcn = $users['like_count']+1;

            $pdo->exec("UPDATE users SET like_count = '$lkcn' , coin=coin+{$channel['like_coin']} WHERE id = '$from_id' LIMIT 1");
            $gift = $users['coin'] +$channel['like_coin'];
            sm($from_id,"❤️ سپاس از لایک شما برای سورس $id 
😁 تعداد {$channel['like_coin']} سکه به شما هدید داده شد!
💰 سکه های جدید شما : $gift");

            bot('editMessageReplyMarkup',[
                'chat_id'=>$brand_username,
                'message_id'=>$id,
                 'reply_markup'=>json_encode(['inline_keyboard'=>[
                    [['text'=>'خرید با Stars ⭐️', 'url'=>'https://t.me/'.$bot_user.'?start=stars_'.$id]],
                    [['text'=>'⭐️ قیمت: '.$files['amount'].' ستاره', 'callback_data'=>'JShow']],
                    [['text'=>'❤️ ('.$like.')', 'callback_data'=>'slike_'.$id], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                ]])
            ]);
             if($chat_type=="private"){
                 bot('editMessageReplyMarkup',[
                    'chat_id'=>$from_id,
                    'message_id'=>$message_id,
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
                        [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$id"]],
                        [['text'=>'❤️ ('.$like.')', 'callback_data'=>'slike_'.$id], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]],
                    ]])
                ]);
            }
            $pdo = null;
        } else {
             bot('answerCallbackQuery', [
                'callback_query_id'=>$update->callback_query->id,
                'text' => '❗️ شما قبلا این سورس را لایک کرده اید',
                'show_alert' =>true
            ]);
            $pdo = null;
        }
    }
    elseif(strpos($message, 'flike_') !== false){
        $id = str_replace('flike_', null, $message);
        $query = $pdo->query("SELECT * FROM likes WHERE user_id = '$from_id' AND file_id = '$id' LIMIT 1")->rowCount();
        if($query < 1){
            bot('answerCallbackQuery', [
                'callback_query_id'=>$update->callback_query->id,
                'text' => '❤️ لایک شما با موفقیت انجام شد',
                'show_alert' =>true
            ]);
            $files = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $like = $files['like_count'] + 1;
            $pdo->exec("UPDATE files SET like_count = '$like' WHERE id = '$id' LIMIT 1");
            $pdo->exec("INSERT INTO likes (like_gift,user_id, file_id) VALUES (1,'$from_id', '$id')");
            $lkcn = $users['like_count']+1;

            $pdo->exec("UPDATE users SET like_count = '$lkcn' , coin=coin+{$channel['like_coin']} WHERE id = '$from_id' LIMIT 1");
            $gift = $users['coin'] +$channel['like_coin'];
            sm($from_id,"❤️ سپاس از لایک شما برای سورس $id 
😁 تعداد {$channel['like_coin']} سکه به شما هدید داده شد!
💰 سکه های جدید شما : $gift");

            bot('editMessageReplyMarkup',[
                'chat_id'=>$brand_username,
                'message_id'=>$id,
                'reply_markup'=>json_encode(['inline_keyboard'=>[
                    [['text'=>'📮 دریافت سورس', 'url'=>'https://t.me/'.$bot_user.'?start=file_'.$id]],
                    [['text'=>'📊 آمار دانلود بصورت رایگان : '.$files['down_count'].' از '.$files['limits'], 'callback_data'=>'DNLoad']],
                    [['text'=>'❤️ ('.$like.')', 'callback_data'=>'flike_'.$id], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    

                ]])
            ]);
            if($chat_type=="private"){

                bot('editMessageReplyMarkup',[
                    'chat_id'=>$from_id,
                    'message_id'=>$message_id,
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
                        [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$id"]],
                        [['text'=>'❤️ ('.$files['like_count'].')', 'callback_data'=>'flike_'.$id], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]],
                                    
                    ]])
                ]);
            }
            $pdo = null;
        } else {
            $liker = $pdo->query("SELECT * FROM likes WHERE user_id = '$from_id' and file_id='$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $gift = $users['coin'] + $channel['like_coin'];
            if($liker['like_gift']==0){
                $pdo->exec("update `likes` set like_gift='1' where user_id='$from_id' and file_id='$id'");

                $pdo->exec("update `users` set coin=$gift where id='$from_id' LIMIT 1");

                $filesr = $pdo->query("SELECT id FROM files")->rowcount();
                $mandeh = $pdo->query("SELECT * FROM likes WHERE user_id='$from_id' and file_id!=$id and like_gift=0")->rowcount();

                $countLK = $filesr - $mandeh;

                sm($from_id,"❤️ سپاس از لایک شما برای سورس $id 
😁 تعداد {$channel['like_coin']} سکه به شما هدید داده شد!
💰 سکه های جدید شما : $gift

 🎁 هنوز $mandeh  لایک را دریافت نکرده اید ، وارد کانال '.$brand_name.' شوید و با زدن روی ❤️ لایک کرده و رایگان سکه دریافت کنید!");
            }

            bot('answerCallbackQuery', [
                'callback_query_id'=>$update->callback_query->id,
                'text' => '❗️ شما قبلا این سورس را لایک کرده اید',
                'show_alert' =>true
            ]);
            $pdo = null;
        }
    }

    elseif(strpos($message, 'vlike_') !== false){
        $id = str_replace('vlike_', null, $message);
        $query = $pdo->query("SELECT * FROM likes WHERE user_id = '$from_id' AND file_id = '$id' LIMIT 1")->rowCount();
        if($query < 1){
            bot('answerCallbackQuery', [
                'callback_query_id'=>$update->callback_query->id,
                'text' => '❤️ لایک شما با موفقیت انجام شد',
                'show_alert' =>true
            ]);
            $files = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $like = $files['like_count'] + 1;
            $pdo->exec("UPDATE files SET like_count = '$like' WHERE id = '$id' LIMIT 1");
            $pdo->exec("INSERT INTO likes (like_gift,user_id, file_id) VALUES ('1','$from_id', '$id')");
            $lkcn = $users['like_count']+1;
            $pdo->exec("UPDATE users SET like_count = '$lkcn' , coin=coin +{$channel['like_coin']}WHERE id = '$from_id' LIMIT 1");
            $gift = $users['coin'] +$channel['like_coin'];
            sm($from_id,"❤️ سپاس از لایک شما برای سورس $id 
😁 تعداد {$channel['like_coin']} سکه به شما هدید داده شد!
💰 سکه های جدید شما : $gift");
            bot('editMessageReplyMarkup',[
                'chat_id'=>$brand_username,
                'message_id'=>$id,
                'reply_markup'=>json_encode(['inline_keyboard'=>[
                    [['text'=>'📮 دریافت سورس', 'url'=>'https://t.me/'.$bot_user.'?start=buy_'.$id]],
                    [['text'=>'💰قیمت '.number_format($files['amount'] / 10).' تومان'.' | '.number_format($files['amount']).' ریال','callback_data'=>"BuyBTN"]]
                    [['text'=>'💎 تعداد فروش موفق : '.$files['down_count'],'callback_data'=>'selles']],
                    [['text'=>'❤️ ('.$like.')', 'callback_data'=>'vlike_'.$id], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    

                ]])
            ]);
            if($chat_type=="private"){
                bot('editMessageReplyMarkup',[
                    'chat_id'=>$from_id,
                    'message_id'=>$message_id,
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
                        [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$id"]],
                        [['text'=>'❤️ ('.$files['like_count'].')', 'callback_data'=>'vlike_'.$id], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]]
                    ]])
                ]);
            }
            $pdo = null;
        } else {
            $liker = $pdo->query("SELECT * FROM likes WHERE user_id = '$from_id' and file_id='$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $gift = $users['coin'] + $channel['like_coin'];
            if($liker['like_gift']==0){
                $pdo->exec("update `likes` set like_gift='1' where user_id='$from_id' and file_id='$id'");

                $pdo->exec("update `users` set coin=$gift where id='$from_id' LIMIT 1");

                $filesr = $pdo->query("SELECT id FROM files")->rowcount();
                $mandeh = $pdo->query("SELECT * FROM likes WHERE user_id='$from_id' and file_id!=$id and like_gift=0")->rowcount();

                $countLK = $filesr - $mandeh;

                sm($from_id,"❤️ سپاس از لایک شما برای سورس $id 
😁 تعداد {$channel['like_coin']} سکه به شما هدید داده شد!
💰 سکه های جدید شما : $gift

 🎁 هنوز $mandeh  لایک را دریافت نکرده اید ، وارد کانال '.$brand_name.' شوید و با زدن روی ❤️ لایک کرده و رایگان سکه دریافت کنید!");
            }

            bot('answerCallbackQuery', [
                'callback_query_id'=>$update->callback_query->id,
                'text' => '❗️ شما قبلا این سورس را لایک کرده اید',
                'show_alert' =>true
            ]);
            $pdo = null;
        }
    }

    elseif(preg_match('/^cclike_(.*)/',$message,$match)){
        $id = $match[1];

        $query = $pdo->query("SELECT * FROM likes WHERE user_id = '$from_id' AND file_id = '$id' LIMIT 1")->rowCount();
        if($query < 1){
            bot('answerCallbackQuery', [
                'callback_query_id'=>$update->callback_query->id,
                'text' => '❤️ لایک شما با موفقیت انجام شد',
                'show_alert' =>true
            ]);
            $files = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $like = $files['like_count'] + 1;
            $pdo->exec("UPDATE files SET like_count = '$like' WHERE id = '$id' LIMIT 1");
            $pdo->exec("INSERT INTO likes (like_gift,user_id, file_id) VALUES (1,'$from_id', '$id')");
            $lkcn = $users['like_count']+1;
            $pdo->exec("UPDATE users SET like_count = '$lkcn' , coin=coin +{$channel['like_coin']}WHERE id = '$from_id' LIMIT 1");
            $gift = $users['coin'] +$channel['like_coin'];
            sm($from_id,"❤️ سپاس از لایک شما برای سورس $id 
😁 تعداد {$channel['like_coin']} سکه به شما هدید داده شد!
💰 سکه های جدید شما : $gift");
            bot('editMessageReplyMarkup',[
                'chat_id'=>$brand_username,
                'message_id'=>$id,
                'reply_markup'=>json_encode(['inline_keyboard'=>[
                    [['text'=>'📮 دریافت سورس', 'url'=>'https://t.me/'.$bot_user.'?start=file_'.$id]],
                    [['text'=>'💰قیمت '.$files['amount'].' سکه', 'callback_data'=>'BuyBTN']],
                    [['text'=>'❤️ ('.$files['like_count'].')', 'callback_data'=>'cclike_'.$id], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    

                ]])
            ]);
            if($chat_type=="private"){
                bot('editMessageReplyMarkup',[
                    'chat_id'=>$from_id,
                    'message_id'=>$message_id,
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
                        [['text'=>"🔗 اشتراک گذاری با دوستان",'switch_inline_query'=>"$id"]],
                        [['text'=>'❤️ ('.$files['like_count'].')', 'callback_data'=>'cclike_'.$id], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][0]]]
                    ]])
                ]);
            }
            $pdo = null;
        } else {
            $liker = $pdo->query("SELECT * FROM likes WHERE user_id = '$from_id' and file_id='$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $gift = $users['coin'] + $channel['like_coin'];
            if($liker['like_gift']==0){
                $pdo->exec("update `likes` set like_gift='1' where user_id='$from_id' and file_id='$id'");

                $pdo->exec("update `users` set coin=$gift where id='$from_id' LIMIT 1");
                $filesr = $pdo->query("SELECT id FROM files")->rowcount();
                $mandeh = $pdo->query("SELECT * FROM likes WHERE user_id='$from_id' and file_id!=$id and like_gift=0")->rowcount();

                $countLK = $filesr - $mandeh;

                sm($from_id,"❤️ سپاس از لایک شما برای سورس $id 
😁 تعداد {$channel['like_coin']} سکه به شما هدید داده شد!
💰 سکه های جدید شما : $gift

 🎁 هنوز $mandeh  لایک را دریافت نکرده اید ، وارد کانال '.$brand_name.' شوید و با زدن روی ❤️ لایک کرده و رایگان سکه دریافت کنید!");
            }

            bot('answerCallbackQuery', [
                'callback_query_id'=>$update->callback_query->id,
                'text' => '❗️ شما قبلا این سورس را لایک کرده اید',
                'show_alert' =>true
            ]);
            $pdo = null;
        }
    }
    elseif($message=='JShow'){
        bot('answerCallbackQuery', [
            'callback_query_id'=> $update->callback_query->id,
            'text' => '🥋 این دکمه نمایشی است و کاربرد دیگری ندارد!',
            'show_alert' =>true
        ]);
        $pdo = null;
    }
    elseif($message=='DNLoad'){
        bot('answerCallbackQuery', [
            'callback_query_id'=> $update->callback_query->id,
            'text' => '❗️ این دکمه جهت نمایش تعداد محدودیت دانلود این سورس است 
👈🏻 شما میتوانید با افزایش سکه های حساب خود در ربات اقدام به دریافت سورس ها بدون محدودیت کنید',
            'show_alert' =>true
        ]);
        $pdo = null;
    }
   
    require_once 'panel.php';
}
 //sm(-1002266754005,json_encode($update,448));
if($chat_type=='channel'){
   //  sm(-1002266754005,json_encode($update,448));
    //sm(-1001295833851,json_encode($update,448));
    if($pdo->query("SELECT id FROM users WHERE id = '$from_id'")->rowCount()==0){
        bot('answerCallbackQuery', [
            'callback_query_id'=>$update->callback_query->id,
            'text' => 'جهت استفاده ربات را استارت نمایید ❗️',
            'show_alert' =>true
        ]);
        $pdo = null;
    }

    if($ChannelLock_Two=='left'){
        bot('answerCallbackQuery', [
            'callback_query_id'=>$update->callback_query->id,
            'text' => 'جهت استفاده از ربات عضو کانال شوید ❗️',
            'show_alert' =>true
        ]);
        $pdo = null;
    }

// START: ADDED LIKE HANDLER FOR STARS IN CHANNEL
    elseif(strpos($message, 'slike_') !== false){
        $id = str_replace('slike_', null, $message);
        $query = $pdo->query("SELECT * FROM likes WHERE user_id = '$from_id' AND file_id = '$id' LIMIT 1")->rowCount();
        if($query < 1){
            bot('answerCallbackQuery', ['callback_query_id'=>$update->callback_query->id, 'text' => '❤️ لایک شما با موفقیت انجام شد', 'show_alert' =>true]);
            
            $pdo->exec("UPDATE files SET like_count = like_count + 1 WHERE id = '$id' LIMIT 1");
            $pdo->exec("INSERT INTO likes (like_gift,user_id, file_id) VALUES (1,'$from_id', '$id')");
            $pdo->exec("UPDATE users SET like_count = like_count + 1, coin = coin + {$channel['like_coin']} WHERE id = '$from_id' LIMIT 1");
            
            $new_coin_balance = $users['coin'] + $channel['like_coin'];
            sm($from_id,"❤️ سپاس از لایک شما برای سورس $id\n😁 تعداد {$channel['like_coin']} سکه به شما هدیه داده شد!\n💰 سکه های جدید شما : $new_coin_balance");

            $files = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $like_count = $files['like_count'];
            
            $keyboard = [
                [['text'=>'خرید با Stars ⭐️', 'url'=>'https://t.me/'.$bot_user.'?start=stars_'.$id]],
                [['text'=>'⭐️ قیمت: '.$files['amount'].' ستاره', 'callback_data'=>'JShow']],
                [['text'=>'❤️ ('.$like_count.')', 'callback_data'=>'slike_'.$id], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
            ];

            bot('editMessageReplyMarkup',['chat_id'=>$brand_username, 'message_id'=>$id, 'reply_markup'=> json_encode(['inline_keyboard' => $keyboard])]);
            $pdo = null;
        } else {
            bot('answerCallbackQuery', ['callback_query_id'=>$update->callback_query->id, 'text' => '❗️ شما قبلا این سورس را لایک کرده اید', 'show_alert' =>true]);
            $pdo = null;
        }
    }
    elseif(strpos($message, 'flike_') !== false){
        $id = str_replace('flike_', null, $message);
        $query = $pdo->query("SELECT * FROM likes WHERE user_id = '$from_id' AND file_id = '$id' LIMIT 1")->rowCount();
        if($query < 1){
            bot('answerCallbackQuery', ['callback_query_id'=>$update->callback_query->id, 'text' => '❤️ لایک شما با موفقیت انجام شد', 'show_alert' =>true]);
            
            $pdo->exec("UPDATE files SET like_count = like_count + 1 WHERE id = '$id' LIMIT 1");
            $pdo->exec("INSERT INTO likes (like_gift,user_id, file_id) VALUES (1,'$from_id', '$id')");
            $pdo->exec("UPDATE users SET like_count = like_count + 1, coin = coin + {$channel['like_coin']} WHERE id = '$from_id' LIMIT 1");
            
            $new_coin_balance = $users['coin'] + $channel['like_coin'];
            sm($from_id,"❤️ سپاس از لایک شما برای سورس $id\n😁 تعداد {$channel['like_coin']} سکه به شما هدیه داده شد!\n💰 سکه های جدید شما : $new_coin_balance");

            $files = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $like_count = $files['like_count'];
            
            $keyboard = [];
            if ($files['ads_type'] == 'zm') {
                 $keyboard = [
                    [['text'=>'دریافت با عضوگیری 👥', 'url'=>'https://t.me/'.$bot_user.'?start=zm_'.$id]],
                    [['text'=>'❤️ ('.$like_count.')', 'callback_data'=>'flike_'.$id], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                ];
            } else { // 'free'
                $keyboard = [
                    [['text'=>'📮 دریافت سورس', 'url'=>'https://t.me/'.$bot_user.'?start=file_'.$id]],
                    [['text'=>'📊 آمار دانلود بصورت رایگان : '.$files['down_count'].' از '.$files['limits'], 'callback_data'=>'DNLoad']],
                    [['text'=>'❤️ ('.$like_count.')', 'callback_data'=>'flike_'.$id], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                ];
            }

            bot('editMessageReplyMarkup',['chat_id'=>$brand_username, 'message_id'=>$id, 'reply_markup'=> json_encode(['inline_keyboard' => $keyboard])]);
            $pdo = null;
        } else {
            bot('answerCallbackQuery', ['callback_query_id'=>$update->callback_query->id, 'text' => '❗️ شما قبلا این سورس را لایک کرده اید', 'show_alert' =>true]);
            $pdo = null;
        }
    }

    elseif(strpos($message, 'vlike_') !== false){
        $id = str_replace('vlike_', null, $message);
        $query = $pdo->query("SELECT * FROM likes WHERE user_id = '$from_id' AND file_id = '$id' LIMIT 1")->rowCount();
        if($query < 1){
            bot('answerCallbackQuery', [
                'callback_query_id'=>$update->callback_query->id,
                'text' => '❤️ لایک شما با موفقیت انجام شد',
                'show_alert' =>true
            ]);
            $files = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $like = $files['like_count'] + 1;
            $pdo->exec("UPDATE files SET like_count = '$like' WHERE id = '$id' LIMIT 1");
            $pdo->exec("INSERT INTO likes (like_gift,user_id, file_id) VALUES (1,'$from_id', '$id')");
            $lkcn = $users['like_count']+1;
            $pdo->exec("UPDATE users SET like_count = '$lkcn' , coin=coin +{$channel['like_coin']}WHERE id = '$from_id' LIMIT 1");
            $gift = $users['coin'] +$channel['like_coin'];
            sm($from_id,"❤️ سپاس از لایک شما برای سورس $id 
😁 تعداد {$channel['like_coin']} سکه به شما هدید داده شد!
💰 سکه های جدید شما : $gift");
            bot('editMessageReplyMarkup',[
                'chat_id'=>$brand_username,
                'message_id'=>$id,
                'reply_markup'=>json_encode(['inline_keyboard'=>[
                    [['text'=>'📮 دریافت سورس', 'url'=>'https://t.me/'.$bot_user.'?start=buy_'.$id]],
                    [['text'=>'💰قیمت '.number_format($files['amount'] / 10).' تومان'.' | '.number_format($files['amount']).' ریال','callback_data'=>"BuyBTN"]],
                    [['text'=>'💎 تعداد فروش موفق : '.$files['down_count'],'callback_data'=>'selles']],
                    [['text'=>'❤️ ('.$like.')', 'callback_data'=>'vlike_'.$id], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    

                ]])
            ]);
            $pdo = null;
        } else {
            $liker = $pdo->query("SELECT * FROM likes WHERE user_id = '$from_id' and file_id='$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $gift = $users['coin'] + $channel['like_coin'];
            if($liker['like_gift']==0){
                $pdo->exec("update `likes` set like_gift='1' where user_id='$from_id' and file_id='$id'");

                $pdo->exec("update `users` set coin=$gift where id='$from_id' LIMIT 1");

                $filesr = $pdo->query("SELECT id FROM files")->rowcount();
                $mandeh = $pdo->query("SELECT * FROM likes WHERE user_id='$from_id' and file_id!=$id and like_gift=0")->rowcount();

                $countLK = $filesr - $mandeh;

                sm($from_id,"❤️ سپاس از لایک شما برای سورس $id 
😁 تعداد {$channel['like_coin']} سکه به شما هدید داده شد!
💰 سکه های جدید شما : $gift

 🎁 هنوز $mandeh  لایک را دریافت نکرده اید ، وارد کانال '.$brand_name.' شوید و با زدن روی ❤️ لایک کرده و رایگان سکه دریافت کنید!");
            }

            bot('answerCallbackQuery', [
                'callback_query_id'=>$update->callback_query->id,
                'text' => '❗️ شما قبلا این سورس را لایک کرده اید',
                'show_alert' =>true
            ]);
            $pdo = null;
        }
    }

    elseif(preg_match('/^cclike_(.*)/',$message,$match)){
        $id = $match[1];
        $query = $pdo->query("SELECT * FROM likes WHERE user_id = '$from_id' AND file_id = '$id' LIMIT 1")->rowCount();
        if($query < 1){
            bot('answerCallbackQuery', [
                'callback_query_id'=>$update->callback_query->id,
                'text' => '❤️ لایک شما با موفقیت انجام شد',
                'show_alert' =>true
            ]);
            $files = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $like = $files['like_count'] + 1;
            $pdo->exec("UPDATE files SET like_count = '$like' WHERE id = '$id' LIMIT 1");
            $pdo->exec("INSERT INTO likes (like_gift,user_id, file_id) VALUES (1,'$from_id', '$id')");
            $lkcn = $users['like_count']+1;
            $pdo->exec("UPDATE users SET like_count = '$lkcn' , coin=coin +{$channel['like_coin']}WHERE id = '$from_id' LIMIT 1");
            $gift = $users['coin'] +$channel['like_coin'];
            sm($from_id,"❤️ سپاس از لایک شما برای سورس $id 
😁 تعداد {$channel['like_coin']} سکه به شما هدید داده شد!
💰 سکه های جدید شما : $gift");
            bot('editMessageReplyMarkup',[
                'chat_id'=>$brand_username,
                'message_id'=>$update->callback_query->message->message_id,
                'reply_markup'=>json_encode(['inline_keyboard'=>[
                    [['text'=>'📮 دریافت سورس', 'url'=>'https://t.me/'.$bot_user.'?start=file_'.$id]],
                    [['text'=>'💰قیمت '.$files['amount'].' سکه', 'callback_data'=>'BuyBTN']],
                    [['text'=>'❤️ ('.$files['like_count'].')', 'callback_data'=>'cclike_'.$id], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    

                ]])
            ]);
            $pdo = null;
        } else {

            $liker = $pdo->query("SELECT * FROM likes WHERE user_id = '$from_id' and file_id='$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $gift = $users['coin'] + $channel['like_coin'];
            if($liker['like_gift']==0){
                $pdo->exec("update `likes` set like_gift='1' where user_id='$from_id' and file_id='$id'");

                $pdo->exec("update `users` set coin=$gift where id='$from_id' LIMIT 1");
                $filesr = $pdo->query("SELECT id FROM files")->rowcount();
                $mandeh = $pdo->query("SELECT * FROM likes WHERE user_id='$from_id' and file_id!=$id and like_gift=0")->rowcount();

                $countLK = $filesr - $mandeh;
                sm($from_id,"❤️ سپاس از لایک شما برای سورس $id 
😁 تعداد {$channel['like_coin']} سکه به شما هدید داده شد!
💰 سکه های جدید شما : $gift

 🎁 هنوز $mandeh  لایک را دریافت نکرده اید ، وارد کانال '.$brand_name.' شوید و با زدن روی ❤️ لایک کرده و رایگان سکه دریافت کنید!");
            }

            bot('answerCallbackQuery', [
                'callback_query_id'=>$update->callback_query->id,
                'text' => '❗️ شما قبلا این سورس را لایک کرده اید',
                'show_alert' =>true
            ]);
            $pdo = null;
        }
    }

    elseif($message=='DNLoad'){
        bot('answerCallbackQuery', [
            'callback_query_id'=> $update->callback_query->id,
            'text' =>"❓ این دکمه جهت نمایش تعداد و ظرفیت دانلود رایگان این سورس است!

✅ شما هر زمان با پایان یافتن ظرفیت رایگان دانلود  و با افزایش سکه حساب خودتان اقدام به دریافت این سورس بدون محدودیت کنید.",
            'show_alert' =>true

        ]);
        $pdo = null;
    }





    elseif($message=='BuyBTN'){
        bot('answerCallbackQuery', [
            'callback_query_id'=> $update->callback_query->id,
            'text' => 'این دکمه جهت نمایش قیمت سورس میباشد و کاربرد دیگری ندارد ❗️',
            'show_alert' =>true
        ]);
        $pdo = null;
    }
    elseif($message=='selles'){
        bot('answerCallbackQuery', [
            'callback_query_id'=> $update->callback_query->id,
            'text' => ' 💰 این دکمه جهت نمایش تعداد فروش موفق است و کاربرد دیگری ندارد!️',
            'show_alert' =>true
        ]);
        $pdo = null;
    }


    elseif($message=='ValueOfGifs'){
        bot('answerCallbackQuery', [
            'callback_query_id'=> $update->callback_query->id,
            'text' =>"برحسب مقدار اعلام شده در کانال @pejvakevents",
            'show_alert' =>true

        ]);
        $pdo = null;
    }
}


elseif($message=="date"){
    $bob  = strtotime("+2 days");
    $tt   =  date("Y/m/d",$bob);
    sm($from_id,$tt." ".date("Y/m/d"));
}
// if($message=="sedn"){
//     $query = 165000;
// $text= '💰قیمت '.number_format($query).' ریال'.' | '.number_format($query / 10).' تومان';
// bot('sendmessage',[
//     'chat_id'=>$from_id,
//     'text'=>"see this items",
//     'reply_markup'=>json_encode(['inline_keyboard'=>[
//       [['text'=>'💰قیمت '.number_format($query / 10).' تومان'.' | '.number_format($query).' ریال','callback_data'=>"buy"]]

//         ]])
//     ]);
// }

if($message=="صلوات"){

$dater = date("H")-1;$dater = $dater.date(":i");

sm($from_id,"$dater");
    
}

// if(file_exists("error_log"))unlink("error_log");



$token = TOKEN_POKER;
$apiUrl = "https://api.telegram.org/bot$token/";


// تابع برای ارسال پیام به کاربر
function sendMessage($chat_id, $text, $keyboard = null) {
    global $apiUrl;

    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'MARKDOWN'
    ];

    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }

    file_get_contents($apiUrl . "sendMessage?" . http_build_query($data));
}

// تابع برای نمایش لیست کاربران با صفحه‌بندی
function showUsers($chat_id, $page) {
    global $pdo;

    $limit = 10; // تعداد کاربران در هر صفحه
    $offset = ($page - 1) * $limit;

    // دریافت تعداد کل کاربران
    $total_query = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_rows = $total_query->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_rows / $limit);

    // دریافت کاربران برای صفحه جاری
    $stmt = $pdo->prepare("SELECT id, coin, timer FROM users LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // آماده‌سازی متن برای نمایش کاربران
    $text = "لیست کاربران - صفحه $page از $total_pages:\n\n";
    foreach ($users as $user) {
        $text .= "آیدی: " . $user['id'] . "\n";
        $text .= "[🗣 نمایش پروفایل](tg://user?id=".$user['id'].")"."\n\n";
        $text .= "سکه هاش : " . $user['coin'] . "\n";
        $text .= "زمان عضویت : " . $user['timer'] . "\n\n";
    }

    // تنظیم دکمه‌های قبل و بعد بر اساس صفحه فعلی
    $keyboard = ['inline_keyboard' => []];
    if ($page > 1) {
        $keyboard['inline_keyboard'][0][] = ['text' => 'قبل', 'callback_data' => "prev_page_" . ($page - 1)];
         $keyboard['inline_keyboard'][1][] = ['text' => 'صفحه آخر '.$total_pages, 'callback_data' => "prev_page_" . $total_pages];
            $keyboard['inline_keyboard'][1][] = ['text' => 'صفحه اول ', 'callback_data' => "prev_page_" . 1];
            
            $keyboard['inline_keyboard'][2][] = ['text' => 'شما در صفحه '.$page.'هستید', 'callback_data' => "nothing"];
        
        
    }
    if ($page < $total_pages) {
        $keyboard['inline_keyboard'][0][] = ['text' => 'بعد', 'callback_data' => "next_page_" . ($page + 1)];
        
            $keyboard['inline_keyboard'][2][] = ['text' => 'شما در صفحه '.$page.'هستید', 'callback_data' => "nothing"];
        
    }

    sendMessage($chat_id, $text, $keyboard);
}

// دریافت آپدیت‌های دریافتی
$update = json_decode(file_get_contents("php://input"), true);
$chat_id = $update["message"]["chat"]["id"] ?? null;
$text = $update["message"]["text"] ?? '';
$callback_query = $update["callback_query"] ?? null;
if($text=="جبار") sendMessage($chat_id, $text);
// پردازش دستورات
if ($text == "نمایش") {
    showUsers($chat_id, 1); // نمایش صفحه اول از لیست کاربران
}

if (isset($callback_query)) {
    $callback_data = $callback_query["data"];
    $chat_id = $callback_query["message"]["chat"]["id"];

    // صفحه‌بندی لیست کاربران
    if (strpos($callback_data, "prev_page_") === 0) {
        $page = (int)str_replace("prev_page_", "", $callback_data);
        showUsers($chat_id, $page);
    } elseif (strpos($callback_data, "next_page_") === 0) {
        $page = (int)str_replace("next_page_", "", $callback_data);
        showUsers($chat_id, $page);
    }
}



$pdo = null;