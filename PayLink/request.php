<?php
// فعال کردن نمایش خطاها برای دیباگ (در production باید خاموش باشد)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_errors.log');

// تنظیم timeout برای اجرای اسکریپت
set_time_limit(30);
ini_set('max_execution_time', 30);

@require_once __DIR__.'/../config.php';
@require_once "func_pay.php";

// تابع برای نمایش صفحه خطا
function showErrorPage($title, $message, $showBackButton = true) {
    $botUser = isset($GLOBALS['botUser']) ? $GLOBALS['botUser'] : 'pejvakrobot';
    echo "<!DOCTYPE HTML>
<html dir='rtl' lang='fa'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$title</title>
    <style>
        body {
            font-family: 'irsans', Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 20px;
            text-align: center;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 { color: #FF0000; margin-top: 0; }
        p { font-size: 1.2em; line-height: 1.6; }
        .button {
            background-color: #007BFF;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 1.1em;
            cursor: pointer;
            border-radius: 5px;
            margin-top: 20px;
            text-decoration: none;
            display: inline-block;
        }
        .button:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>$title</h1>
        <p>$message</p>";
    if ($showBackButton) {
        echo "<a href='tg://resolve?domain=$botUser' class='button'>🤖 بازگشت به بات</a>";
    }
    echo "</div>
</body>
</html>";
    exit;
}

//echo "<h1>Wait... </h1>";
function getip()
{
    if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
        $ip = $_SERVER["HTTP_CLIENT_IP"];
    } else {
        if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else {
            $ip = $_SERVER["REMOTE_ADDR"];
        }
    }
    return $ip;
}

function ip_info($ip)
{
    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, "http://ip-api.com/csv/" . $ip . "");
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($c, CURLOPT_TIMEOUT, 5); // timeout 5 ثانیه
    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 3); // timeout اتصال 3 ثانیه
    $exec = curl_exec($c);
    $curl_error = curl_error($c);
    curl_close($c);
    
    if ($exec === false || !empty($curl_error)) {
        error_log("خطا در بررسی IP: " . $curl_error);
        // در صورت خطا، به عنوان ایران در نظر می‌گیریم (برای جلوگیری از مسدود شدن)
        return "Iran";
    }
    
    $exp = explode(",", $exec);
    if (isset($exp[1])) {
        return $exp[1];
    }
    return "Iran"; // پیش‌فرض
}
// دریافت اطلاعات ربات با timeout و error handling
$botUser = 'pejvakrobot'; // مقدار پیش‌فرض
try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot' . TOKEN_POKER . '/getMe');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $bot_info = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($bot_info !== false && empty($curl_error)) {
        $bot_data = json_decode($bot_info, true);
        if (isset($bot_data['result']['username'])) {
            $botUser = $bot_data['result']['username'];
        }
    } else {
        error_log("خطا در دریافت اطلاعات ربات: " . $curl_error);
    }
} catch (Exception $e) {
    error_log("خطا در دریافت اطلاعات ربات: " . $e->getMessage());
}

$GLOBALS['botUser'] = $botUser;

$request = json_encode(['keyboard'=>[
    [['text'=>'تایید هویت 🔑','request_contact'=>true]],
    [['text'=>'بازگشت ↪️']]
],'resize_keyboard'=>true]);
function getChat($chat_id){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot'. TOKEN_POKER. '/getChat');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'chat_id'=> $chat_id
    ]);
    return json_decode(curl_exec($ch));
}
function redirect($url)
{
    if (!headers_sent()){
        header("Location: $url");
    }else{
        echo "<script type='text/javascript'>window.location.href='$url'</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=$url'/></noscript>";
    }
    $text="❌ شما به علت دستکاری در قیمت سورس حین خرید ربات گزارش شدید!
تکرار این اقدام سبب انسداد دائمی شما خواهد شد!";
    file_get_contents("https://api.telegram.org/bot".TOKEN_POKER."/sendmessage?chat_id={$payment['fromid']}&text=$text&parse_mode=html");

    exit;
}

#--------------------------------------#
// بررسی وجود پارامتر payment
if (!isset($_GET['payment']) || empty($_GET['payment'])) {
    showErrorPage("❌ خطا", "لینک پرداخت نامعتبر است. لطفاً دوباره تلاش کنید.");
}

// دریافت اطلاعات پرداخت با error handling
try {
    $payment_id = filter_var($_GET['payment'], FILTER_SANITIZE_STRING);
    $stmt = $pdo->prepare("SELECT * FROM re_payments WHERE id = ? LIMIT 1");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        showErrorPage("❌ لینک نامعتبر", "لینک پرداخت یافت نشد. لطفاً دوباره از ربات اقدام کنید.");
    }
    
    // بررسی وجود کاربر
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$payment['fromid']]);
    if ($stmt->rowCount() < 1) {
        showErrorPage("❌ خطا", "کاربر یافت نشد. لطفاً دوباره از ربات اقدام کنید.");
    }
    
    // دریافت اطلاعات کاربر
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$payment['fromid']]);
    $users = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$users) {
        showErrorPage("❌ خطا", "اطلاعات کاربر یافت نشد. لطفاً دوباره تلاش کنید.");
    }
    
    // دریافت اطلاعات فایل (اگر وجود داشته باشد)
    $file = null;
    if ($payment['file'] != 0) {
        $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? LIMIT 1");
        $stmt->execute([$payment['file']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    error_log("خطا در دیتابیس request.php: " . $e->getMessage());
    showErrorPage("❌ خطای سیستم", "خطا در اتصال به دیتابیس. لطفاً بعداً تلاش کنید.");
}

// بررسی شماره تلفن
$pn = (!empty($users['phone_number']) && $users['phone_number'] != 0) ? $users['phone_number'] : "error";

if ($pn == "error") {
    try {
        $stmt = $pdo->prepare("UPDATE users SET step = 'share_number' WHERE id = ? LIMIT 1");
        $stmt->execute([$payment['fromid']]);
        
        $text = "📛 کاربر عزیز بنابر درخواست شما در حین پرداخت و  در جهت تایید هویت ایرانی لازم به اشتراک شماره شما میباشد‼️
⚠️ این امر جزء قوانین ما است و با استفاده از کلیدبرد زیر اقدام به تایید هویت کنید👇👇";
        
        // ارسال پیام با timeout
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . TOKEN_POKER . "/sendmessage");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $payment['fromid'],
            'text' => $text,
            'parse_mode' => 'html',
            'reply_markup' => $request
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_exec($ch);
        curl_close($ch);
        
    } catch (Exception $e) {
        error_log("خطا در ارسال پیام احراز هویت: " . $e->getMessage());
    }
    
    showErrorPage("❌ خطای احراز هویت", "✅ لینک احراز هویت در پیوی تلگرام شما ارسال شد.<br>📖 مطابق قوانین ما شما باید احراز هویت کرده و سپس اقدام به خرید کنید!");
}

// تعیین file_id
$file_id = ($payment['file'] == 0) ? NULL : $payment['file'];

// ایجاد درخواست پرداخت
$sib = null; // مقدار پیش‌فرض

try {
    // بررسی وجود channel['domin']
    if (empty($channel['domin'])) {
        error_log("خطا: channel['domin'] تعریف نشده است");
        showErrorPage("❌ خطای پیکربندی", "خطا در تنظیمات سیستم. لطفاً با پشتیبانی تماس بگیرید.");
    }
    
    $parameters = array(
        "merchant" => ZIBAL_MERCHANT_KEY,
        "amount" => $payment['amount'],
        "callbackUrl" => rtrim($channel['domin'], '/') . "/PayLink/verify.php?type={$payment['type']}&fromid={$payment['fromid']}&amount={$payment['amount']}&file={$file_id}&payment_id={$_GET['payment']}",
        "description" => $payment['desc'],
        "mobile" => $pn,
    );
    
    $response = postToZibal('request', $parameters);
    
    if ($response && isset($response->result) && $response->result == 100 && isset($response->trackId)) {
        $sib = "https://gateway.zibal.ir/start/" . $response->trackId;
    } else {
        $error_code = isset($response->result) ? $response->result : 'نامشخص';
        $error_message = isset($response->message) ? $response->message : 'خطای نامشخص در درخواست پرداخت';
        error_log("خطا در درخواست پرداخت Zibal - کد: $error_code - پیام: $error_message");
        showErrorPage("❌ خطا در ایجاد درخواست پرداخت", "کد خطا: $error_code<br>پیام: $error_message<br><br>لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.");
    }
    
} catch (Exception $e) {
    error_log("خطا در postToZibal: " . $e->getMessage());
    showErrorPage("❌ خطای سیستم", "خطا در ارتباط با درگاه پرداخت. لطفاً بعداً تلاش کنید.");
}

// اگر $sib هنوز null است
if (empty($sib)) {
    showErrorPage("❌ خطا", "لینک پرداخت ایجاد نشد. لطفاً دوباره تلاش کنید.");
}


// if($file['amount'] !=$payment['amount'] & $payment['type']=='source'){ 
// redirect("tg://resolve?domain=$botUser");

// }
 
#---------------------------------------#
if(file_exists("error_log")){unlink("error_log");}
?>

<!DOCTYPE HTML>
<html dir="rtl" lang='fa'>
<head>
    <meta charset='utf-8'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" href="round.jpg" type="image/x-icon"> <!-- برای فرمت .ico -->

    <title>صفحه پرداخت ایمن | Secure Payment Gateway</title>
        <!--<link href="https://fonts.googleapis.com/css2?family=Vazir&display=swap" rel="stylesheet">-->

    <style>
    
    @font-face {
    font-family: 'irsans';
    src: url('Iranian Sans.woff2') format('woff2'),
         url('Iranian Sans.woff') format('woff');
    font-weight: normal;
    font-style: normal;
}

.button {
    font-family: 'irsans', sans-serif !important; /* استفاده از !important */
}

  body {
    font-family: 'irsans', sans-serif; /* استفاده از فونت وزیری */
    background-color: #f4f4f4;
    color: #333;
    margin: 0;
    padding: 10px;
    text-align: center;
    font-size:12px;
    animation: pulse 2s ease-in-out infinite alternate; /* انیمیشن جدید */
}

/* تعریف انیمیشن pulse */
@keyframes pulse {
    0% {
        background-color: #f4f4f4; /* رنگ پس‌زمینه اولیه */
        box-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
        transform: scale(1); /* اندازه اولیه */
    }
    50% {
        background-color: #e0e0e0; /* رنگ پس‌زمینه میانی */
        box-shadow: 0 0 10px rgba(255, 255, 255, 0.7); /* افکت نور میانی */
        transform: scale(1.03); /* بزرگ شدن */
    }
    100% {
        background-color: #ffffff; /* رنگ پس‌زمینه نهایی */
        box-shadow: 0 0 15px rgba(255, 255, 255, 1); /* افکت نور نهایی */
        transform: scale(0.99); /* بازگشت به اندازه اولیه */
    }
}

        h1 {
            margin-top: 30px;
            color: #FF0000;
        }
          h2 {
            margin-top: 15px;
            color: #FF0000;
        }

.styled-hr {
            border: none; /* حذف حاشیه پیش‌فرض */
            height: 2px; /* ارتفاع خط */
            background-color: #007BFF; /* رنگ خط */
            margin: 20px 0; /* فاصله بالا و پایین */
            border-radius: 5px; /* گرد کردن گوشه‌های خط */
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5); /* افکت سایه */
        }
        
        p {
            font-size: 1.5em;
            color: #333;
        }

        a {
            color: #007BFF;
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            text-decoration: underline;
        }

        .button {
            background-color: #007BFF;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 1.2em;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .button:hover {
            background-color: #0056b3;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            
            
        }
        
        
          .rounded-image {
            width: 200px; /* عرض تصویر */
            height: 200px; /* ارتفاع تصویر */
            border-radius: 50%; /* گرد کردن تصویر */
            object-fit: cover; /* حفظ نسبت تصویر */
            border: 5px solid #007BFF; /* حاشیه دور تصویر */
        }
        .containerphoto {
            display: flex;
            justify-content: center; /* مرکز کردن تصویر */
            align-items: center; /* مرکز کردن عمودی */
            height: 40vh; /* ارتفاع کامل صفحه */
            background-color: #white; /* رنگ پس‌زمینه */
        }
        
    </style>
</head>

<body>
    <div class="container">
        <div class="containerphoto">
            <img src="round.jpg" alt="تصویر گرد" class="rounded-image">
            </div>
        <h1>اطلاعات پرداخت : </h1>
         <h2>بابت : <?php echo htmlspecialchars($payment['desc'], ENT_QUOTES, 'UTF-8'); echo"<br><br>"; echo "مبلغ ".number_format($payment['amount']) . " ریال"; echo"<br>";?> </h2>
        <hr class="styled-hr">
        <!--<p>جهت ادامه برای پرداخت روی <a href='<?php echo htmlspecialchars($sib, ENT_QUOTES, 'UTF-8'); ?>'>اینجا</a> کلیک کنید.</p>-->
        <p>جهت ادامه پرداخت بر روی دکمه زیر بزنید.</p>
        <input type="button" class="button" onclick="location.href='<?php echo htmlspecialchars($sib, ENT_QUOTES, 'UTF-8'); ?>';" value="🟡 پرداخت آنلاین " />
        <br><br>
        <input type="button" class="button" onclick="location.href='tg://resolve?domain=<?php echo htmlspecialchars($botUser, ENT_QUOTES, 'UTF-8'); ?>';" value="🤖 بازگشت به بات" />
    </div>
</body>
</html>