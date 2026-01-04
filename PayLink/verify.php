<?php
// فعال کردن لاگ خطاها
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_errors.log');

// تنظیم timeout
set_time_limit(30);
ini_set('max_execution_time', 30);

@require_once '../config.php';
@require_once "func_pay.php";

function bot($method, $data=[]){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot'.TOKEN_POKER.'/'.$method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // timeout 10 ثانیه
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // timeout اتصال 5 ثانیه
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || !empty($curl_error)) {
        error_log("خطا در bot() - method: $method - error: " . $curl_error);
        return (object)['ok' => false, 'error' => $curl_error];
    }
    
    $decoded = json_decode($response);
    if ($decoded === null) {
        error_log("خطا در decode پاسخ Telegram API - method: $method");
        return (object)['ok' => false, 'error' => 'Invalid JSON response'];
    }
    
    return $decoded;
}
$channel['ch_logs'] = '-1001511214347';
$bot_name = bot('GetMe')->result->first_name;
$bot_user = bot('GetMe')->result->username;

// $Authority = $_GET['Authority'];

// $data = array("merchant_id" => "{$channel['MerchantID']}", "authority" => $Authority, "amount" => $_GET['amount']);
// $jsonData = json_encode($data);

// $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
// curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
// curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
// curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($ch, CURLOPT_HTTPHEADER, array(
//     'Content-Type: application/json',
//     'Content-Length: ' . strlen($jsonData)
// ));

// $result = curl_exec($ch);
// curl_close($ch);
// $result = json_decode($result, true);


// if($_GET['success']==1) {
    // echo "شناسه سفارش: ".$_GET['orderId']."<br>";

    // بررسی وجود trackId
    if (!isset($_GET['trackId']) || empty($_GET['trackId'])) {
        error_log("خطا: trackId در verify.php موجود نیست");
        echo "<!DOCTYPE HTML><html dir='rtl' lang='fa'><head><meta charset='utf-8'><title>خطا</title></head><body><h1>❌ خطا: اطلاعات پرداخت نامعتبر است</h1></body></html>";
        exit;
    }
    
    //start verfication
    $parameters = array(
        "merchant" => ZIBAL_MERCHANT_KEY,//required
        "trackId" => $_GET['trackId'],//required
    );

    $response = postToZibal('verify', $parameters);
    
    // بررسی پاسخ
    if (!$response || !isset($response->result)) {
        error_log("خطا: پاسخ نامعتبر از Zibal در verify.php");
        echo "<!DOCTYPE HTML><html dir='rtl' lang='fa'><head><meta charset='utf-8'><title>خطا</title></head><body><h1>❌ خطا در ارتباط با درگاه پرداخت</h1></body></html>";
        exit;
    }


  if ($response->result == 100 and $_GET['success']==1) {
    if($_GET['type'] == 'coin'){
        $users = $pdo->query("SELECT * FROM users WHERE id = '{$_GET['fromid']}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
      
        $point = $_GET['amount'] / $channel['tariff'];
         // if($_GET['amount']==399000){$point=129;}
         
         if($point >=25) { $point = $point*2;

$mtn = "🩷 تبریک به مناسبت بلک فرایدی شما دو برابر سکه دریافت کردید!";

bot('sendmessage',[
    'chat_id'=>$_GET['fromid'],
    'text'=>$mtn
    ]);
         }
        $cn = $users['coin'] + $point;
        $pdo->exec("UPDATE users SET coin = '{$cn}' WHERE id = '{$_GET['fromid']}' LIMIT 1");
        $pdo->exec("UPDATE re_payments SET status = 'yespay' WHERE id = '{$_GET['payment_id']}' LIMIT 1");
        $RefID = $_GET['trackId'];
        $Date = date('Y-m-d');
        $Time = date('H:i:s');
        $pdo->exec("INSERT INTO buy (id, owner, amount, date, time, product) VALUES ('{$RefID}', '{$_GET['fromid']}', '{$_GET['amount']}', '{$Date}', '{$Time}', '{$point}')");
  
        bot('sendMessage',[
            'chat_id'=>$_GET['fromid'],
            'text'=>"✅ #پرداخت_موفق 
⬆️ با تشکر از خرید شما , سکه های حساب شما افزایش یافت

💵 تعداد سکه های خریداری شده : $point
💰 موجودی جدید حساب : {$cn} سکه
☑️ مبلغ پرداخت شده : {$_GET['amount']} ریال",
            'parse_mode'=>'html',
            'disable_web_page_preview'=>true
        ]);
bot('sendMessage',[
'chat_id'=>$channel['ch_logs'],
'text'=>"✅ پرداخت جدید
+  کاربر : [{$_GET['fromid']}](tg://user?id={$_GET['fromid']})

💵 تعداد سکه های خریداری شده : $point
💰 موجودی جدید حساب : {$cn} سکه
☑️ مبلغ پرداخت شده : {$_GET['amount']} ریال",
'parse_mode'=>'markdown',
]);
        
    }
      $query = $pdo->query("SELECT * FROM files WHERE id = '{$_GET['file']}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
      
   if($_GET['type'] == 'source'){
      if($query['amount']==$_GET['amount']){
        $pdo->exec("UPDATE re_payments SET status = 'yespay' WHERE id = '{$_GET['payment_id']}' LIMIT 1");
        bot('sendDocument', [
            'chat_id'=>$_GET['fromid'],
            'document'=>$query['file_id'],
            'caption'=>"✅ پرداخت موفق
                
📂 سورس {$query['title']}
➰ ایدی سورس : <code>{$query['id']}</code>
📝زبان توسعه دهنده  : {$query['lang']}

📜 توضیحات بیشتر :
{$query['caption']}

☑️ مبلغ پرداخت شده : {$_GET['amount']} ریال
🆔 @{$channel['username']}",
            'parse_mode'=>'html',
            'reply_markup'=>json_encode(['inline_keyboard'=>[
                [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'flike_'.$id], ['text'=>'📢 '.$brand_name, 'url'=>$channel['link'][1]]]
            ],])
        ]);
      bot('sendmessage',['chat_id'=>$_GET['fromid'],'text'=>"🌹 اینم از سورس خدمت شما 👆

اگه سورس کار نکرد و مشکل داشتید با پشتیبانی ربات صحبت کنید 😎👍"]);

bot('sendMessage',[
    'chat_id'=>$channel['ch_logs'],
    'text'=>"✅  پرداخت موفق خرید سورس
    +  کاربر :  [{$_GET['fromid']}](tg://user?id={$_GET['fromid']})
    📂 سورس {$query['title']}
    ➰ ایدی سورس : *{$query['id']}*
    ☑️ مبلغ پرداخت شده : {$_GET['amount']} ریال",
    'parse_mode'=>'markdown',
    ]);
        
        // $RefID = $result['data']['ref_id'];
                $RefID = $_GET['trackId'];

        $Date = date('Y-m-d');
        $Time = date('H:i:s');
        $pdo->exec("INSERT INTO buy (id, owner, amount, date, time, product) VALUES ('{$RefID}', '{$_GET['fromid']}', '{$_GET['amount']}', '{$Date}', '{$Time}', '{$query['file_id']}')");
        $dc = $query['down_count']+1;
        $bycn = $query['buy_count']+1;
        $pdo->exec("UPDATE files SET down_count = '$dc', buy_count = '$bycn' WHERE id = '{$_GET['file']}' LIMIT 1");
        $users = $pdo->query("SELECT * FROM users WHERE id = '{$_GET['fromid']}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $dncn = $users['down_count']+1;
        $bcn = $users['buy_count']+1;
        $pdo->exec("UPDATE users SET down_count = '$dncn', buy_count = '$bcn' WHERE id = '{$_GET['fromid']}' LIMIT 1");
        
        bot('editMessageReplyMarkup',[
                            'chat_id'=>$brand_username,
                            'message_id'=>$query['id'],
    	                    'reply_markup'=>json_encode(['inline_keyboard'=>[
	    		        [['text'=>'دریافت سورس 📥', 'url'=>'https://t.me/'.$bot_user.'?start=buy_'.$query['id']]],
                        [['text'=>'💰قیمت '.number_format($query['amount'] / 10).' تومان'.' | '.number_format($query['amount']).' ریال','callback_data'=>"BuyBTN"]],
						[['text'=>'💎 تعداد فروش موفق : '.$dc,'callback_data'=>'selles']],
                        [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'vlike_'], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']]
    	    	    	    ]])
        	    	    ]);
                        }else{
                        
                        echo"بیلاخ";
                        }
    }
    
    ?>
    <!DOCTYPE HTML>
    <html dir="rtl" lang='en'>
      
        <head>
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
            padding: 20px;
            text-align: center;
            animation: glow 1.5s ease-in-out infinite alternate; /* اضافه کردن انیمیشن الهی */
        }

        /* تعریف انیمیشن glow */
        @keyframes glow {
            0% {
                background-color: #f4f4f4; /* رنگ پس‌زمینه اولیه */
                box-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
            }
            100% {
                background-color: #ffffff; /* رنگ پس‌زمینه نهایی */
                box-shadow: 0 0 20px rgba(255, 255, 255, 1); /* افکت نور */
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
            <meta charset='utf-8'>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>پرداخت موفق</title>
        </head>
        <body>
            
            <!--<h1 style="color: #65d527; text-align: center">پرداخت شما با موفقیت انجام شد</h1>-->
            <!--<h1 style="text-align: center">کد رهگیری پرداخت شما : <?php //$_GET['trackId'] ?></h1>-->
            <!--<h1 style="text-align: center">شماره تراکنش شما : <?php //echo $_GET['trackId'] ?></h1>-->
            <!--<input type="button" style="background-color: white; color: black; margin-right: 45%;" onclick="location.href='tg://resolve?domain=<?php echo bot('getMe', [])->result->username; ?>';" value="بازگشت به ربات" />-->
      
       <div class="container">
                 <div class="containerphoto">
            <img src="yes.png" alt="تصویر گرد" class="rounded-image">
            </div>
       <h1>پرداخت با موفقیت انجام شد.</h1>
       <p>شماره تراکنش : <?php echo $_GET['trackId'] ?></p>
        <input type="button" class="button" onclick="location.href='tg://resolve?domain=pejvakrobot';" value="🤖 بازگشت به بات" />
        
        </div>
       
        </body>
    </html>
    <?php
} else {
    ?>
    <!DOCTYPE HTML>
    <html dir="rtl" lang='en'>
        <head>
            
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
            padding: 20px;
            text-align: center;
            animation: glow 1.5s ease-in-out infinite alternate; /* اضافه کردن انیمیشن الهی */
        }

        /* تعریف انیمیشن glow */
        @keyframes glow {
            0% {
                background-color: #f4f4f4; /* رنگ پس‌زمینه اولیه */
                box-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
            }
            100% {
                background-color: #ffffff; /* رنگ پس‌زمینه نهایی */
                box-shadow: 0 0 20px rgba(255, 255, 255, 1); /* افکت نور */
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
        }</style>
            <meta charset='utf-8'>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>صفحه ی خطا</title>
        </head>
        <body>
            
        <div class="container">
                 <div class="containerphoto">
            <img src="no.png" alt="تصویر گرد" class="rounded-image">
            </div>
             <h1>پرداخت انجام نشد !</h1>
                 
    <p> <?php //  echo "علت خطا : "; 
    echo "کد خطا : ". $response->result."<br>";
    echo "علت خطا: "; echo $response->message; ?></p>
       
       
        <input type="button" class="button" onclick="location.href='tg://resolve?domain=pejvakrobot';" value="🤖 بازگشت به بات" />
        
        </div>
        </body>
    </html>
    <?php
}
?>