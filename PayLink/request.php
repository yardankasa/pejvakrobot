<?php
@require_once '../config.php';
@require_once "func_pay.php";


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
    $exec = curl_exec($c);
    curl_close($c);
    $exp = explode(",", $exec);
    $pais = $exp[1];
    return $pais;
}
$ip = getip();
if (strtolower(ip_info($ip)) == "iran") {
    
    

$botUser= json_decode(file_get_contents('https://api.telegram.org/bot'. TOKEN_POKER. '/getMe'))->result->username;
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
$payment = $pdo->query("SELECT * FROM re_payments WHERE id = '{$_GET['payment']}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
// if($paymnet['time'] + 86400 < time()){exit("ERROR");}
$users = $pdo->query("SELECT * FROM users WHERE id = '{$payment['fromid']}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$file  = $pdo->query("SELECT * FROM files WHERE id = '{$payment['file']}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if($pdo->query("SELECT id FROM users WHERE id = '{$payment['fromid']}'")->rowCount() < 1){
        echo"<title>❌ لینک نامعتبر❌</title>";

    exit(
"<html dir='rtl' lang='fa-IR'>"."	
<html lang='fa-IR'><center><h1>❌ لینک نامعتبر<hr>📛 لینک شما نامعتبر است!<h1></center>");
    
}

$pn    = ($users['phone_number']==NULL or $users['phone_number']==0) ?"error" : $users['phone_number'];  
if($pn=="error"){
    
            $pdo->exec("UPDATE users SET step = 'share_number' WHERE id = '{$payment['fromid']}' LIMIT 1");
            
               $text="📛 کاربر عزیز بنابر درخواست شما در حین پرداخت و  در جهت تایید هویت ایرانی لازم به اشتراک شماره شما میباشد‼️
⚠️ این امر جزء قوانین ما است و با استفاده از کلیدبرد زیر اقدام به تایید هویت کنید👇👇";
    file_get_contents("https://api.telegram.org/bot".TOKEN_POKER."/sendmessage?chat_id={$payment['fromid']}&text=$text&parse_mode=html&reply_markup=".$request);
    echo"<title>❌خطای احراز هویت❌</title>";
    exit(	"<center><img src='https://www.rayanexchange.com/wp-content/uploads/2019/12/kyc.jpg' alt='Smiley face' width='512' height='512'>".
"<html dir='rtl' lang='fa-IR'>"."	
<html lang='fa-IR'><center><h1>❌ خطای احراز هویت!<hr>✅ لینک احراز هویت در پیوی تلگرام شما ارسال شد.<br> 📖 مطابق قوانین ما شما باید احراز هویت کرده و سپس اقدام به خرید کنید!<h1></center>");



         
}
// $pn = $users['phonenumber'];
if($payment['file']==0){$file_id=NULL;}
if($payment['file']!=0){$file_id = $payment['file'];}

$parameters = array("merchant" => ZIBAL_MERCHANT_KEY,
    "amount" => $payment['amount'],
    "callbackUrl" => "{$channel['domin']}/PayLink/verify.php?type={$payment['type']}&fromid={$payment['fromid']}&amount={$payment['amount']}&file={$file_id}&payment_id={$_GET['payment']}",
    "description" => $payment['desc'],
    "mobile" =>"{$pn}",
    );
$response = postToZibal('request', $parameters);

if ($response->result == 100)
{
     
            // header('Location: https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"]);
            // $sib = 'https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"];
            $sib = "https://gateway.zibal.ir/start/".$response->trackId;
    // header('location: '.$sib);
        
    } else {
        
          echo "result: ".$response->result."<br>";
    echo "message: ".$response->message;
    
        //  echo"<center>";
        //  echo'Error Code: ' . $result['errors']['code']."<br>";
        //  echo'message: ' .  $result['errors']['message']."<br>";
        //  echo 'pay_id  '.$_GET['payment'].'<br>'.'pay_amount  '.$payment['amount'].'<br>'.'sourceId '.$payment['file'].'<br>'.'type '.$payment['type'].'<br>'.'from_id '.$payment['fromid'].'<br>'.'phone '.$users['phone_number'].'<br>'.'desc '. $payment['desc'];
        //  echo"</center>";

    }


// if($file['amount'] !=$payment['amount'] & $payment['type']=='source'){ 
// redirect("tg://resolve?domain=$botUser");

// }
 
#---------------------------------------#
if(file_exists("error_log")){unlink("error_log");}
}else {
    echo"   <meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<h1 style=\"text-align: center;margin-top:30px\">لطفا فیلرشکن خود را خاموش کنید <br>و سپس اقدام به پرداخت نمایید</h1>";
 
    exit();
}
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
         <h2>بابت : <?php echo $payment['desc']; echo"<br><br>"; echo "مبلغ ".number_format($payment['amount']) . " ریال"; echo"<br>";?> </h2>
        <hr calss="styled-hr">
        <!--<p>جهت ادامه برای پرداخت روی <a href='<?php echo $sib ?>'>اینجا</a> کلیک کنید.</p>-->
        <p>جهت ادامه پرداخت بر روی دکمه زیر بزنید.</p>
        <input type="button" class="button" onclick="location.href='<?php echo $sib ;?>';" value="🟡 پرداخت آنلاین " />
        <br><br>
        <input type="button" class="button" onclick="location.href='tg://resolve?domain=after_world" value="⁉️ گزارش مشکل"/>
        <br></br>
        <input type="button" class="button" onclick="location.href='tg://resolve?domain=<?php echo $botUser ; ?>';" value="🤖 بازگشت به بات" />
    </div>
</body>
</html>