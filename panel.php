    <?php


// if(!in_array($from_id,$Devs) or $admins['type']!=2 and in_array($message, ['/panel', 'بازگشت 🔙','پنل','مدیریت','\kg','manage','/manage','panel'])){
      
//       $answer = array('🙂','🦒','😐','💩','بارگیری مادر شما در اینجا...');
//       $nina = rand(0,count($answer)-1);
  
//      SM($from_id,$answer[$nina]);
//     }

if(in_array($from_id,$Devs) or $admins['id']!=null){
     
    if(in_array($message, ['/panel', 'بازگشت 🔙','پنل','مدیریت','\kg','manage','/manage','panel'])){
        if($admins['type'] !=2){
            
        
        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, 'به پنل مدیریت وارد شدید ➰', $message_id, $panel);
        $pdo = null;  exit();
    }else{
          $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '🔎 به پنل خوش آمدید!

✅ سطح دسترسی شما :  2 است.', $message_id, $partners);
        $pdo = null;  exit();
    }
    }
   


if($message=='آمار 📈'){
    
    $user = $pdo->query("SELECT id FROM users")->rowcount();
    $ban = $pdo->query("SELECT block FROM users WHERE block = '1'")->rowcount();
    // ... (your existing stats queries) ...
    $offs = $pdo->query("SELECT id FROM users WHERE `flood` < '$threemonth'")->rowcount();
    
    // --- New Stats for Wheel of Fortune (Corrected Queries) ---
    $total_spins = $pdo->query("SELECT id FROM luckwheel_stats")->rowCount();
    
    $coins_query = $pdo->query("SELECT SUM(prize_value) as total FROM luckwheel_stats WHERE prize_type = 'coins'")->fetch(PDO::FETCH_ASSOC);
    $total_coins_won = intval($coins_query['total']);

    $silver_query = $pdo->query("SELECT SUM(prize_value) as total FROM luckwheel_stats WHERE prize_type = 'silver'")->fetch(PDO::FETCH_ASSOC);
    $total_silver_won = intval($silver_query['total']);

    $total_sources_won = $pdo->query("SELECT id FROM luckwheel_stats WHERE prize_type = 'source'")->rowCount();
    $total_nothing = $pdo->query("SELECT id FROM luckwheel_stats WHERE prize_type = 'nothing'")->rowCount();
    // --- End of New Stats ---
  $user = $pdo->query("SELECT id FROM users")->rowcount();
        $ban = $pdo->query("SELECT block FROM users WHERE block = '1'")->rowcount();
        $phone = $pdo->query("SELECT id FROM users WHERE phone_number != '0'")->rowcount();
        $files = $pdo->query("SELECT id FROM files")->rowcount();
        $buy = $pdo->query("SELECT id FROM buy")->rowcount();
        $downloads = $pdo->query("SELECT * FROM download")->rowcount();
        $likes = $pdo->query("SELECT * FROM likes")->rowcount();
        $one = time() - 86400; $week = time() - 604800; $threemonth = time()-7776000;
        $oneusers = $pdo->query("SELECT id FROM users WHERE flood > '$one'")->rowcount();
        $weekusers = $pdo->query("SELECT id FROM users WHERE flood > '$week'")->rowcount();
        $free = $pdo->query("SELECT id FROM files WHERE `ads_type` = 'free'")->rowcount();
        $vip = $pdo->query("SELECT id FROM files WHERE `ads_type` = 'vip'")->rowcount();
        $offs = $pdo->query("SELECT id FROM users WHERE `flood` < '$threemonth'")->rowcount();
              // ======================= START: ADDED STATS =======================
        $stars = $pdo->query("SELECT id FROM files WHERE `ads_type` = 'stars'")->rowcount();
        // ======================= END: ADDED STATS ==  =====================
    $amount = 0;
    foreach($pdo->query("SELECT * FROM buy") as $buys => $val){
        $amount += $val['amount'];
    }
   if($admins['type'] !=2){
   
$d = "📊 *آمار کلی ربات*
👥 *تعداد کل کاربران:* `$user`
⏱ *کاربران فعال (24 ساعت گذشته):* `$oneusers`
⏰ *کاربران فعال (یک هفته گذشته):* `$weekusers`
💤 *کاربران غیرفعال (سه ماه گذشته):* `$offs`
📲 *کاربرانی که شماره خود را تأیید کرده‌اند:* `$phone`

📁 *وضعیت فایل‌ها*
💡 *فایل‌های رایگان:* `$free`
⭐️ *فایل‌های ویژه (ستاره‌دار):* `$stars`
💰 *فایل‌های پولی (VIP):* `$vip`
🗂 *کل فایل‌های ثبت‌شده:* `$files`

🛒 *آمار خرید و دانلود*
🔢 *تعداد خریدهای انجام‌شده:* `$buy`
📥 *تعداد دانلودها:* `$downloads`
💵 *مجموع درآمد:* `".number_format($amount)."` ریال
❤️ *تعداد کل لایک‌ها:* `$likes`

👮🏻‍♂️ *مدیریت*
👮🏻‍♂️ *تعداد مدیران ربات:* `".(count($Devs)-1)."`
🚫 *کاربران مسدود‌شده:* `$ban`

🎡 *آمار گردونه شانس*
- 🎰 *کل چرخش‌ها:* `$total_spins`
- 🪙 *کل سکه‌های برنده شده:* `$total_coins_won`
- 🥈 *کل نقره‌های برنده شده:* `$total_silver_won`
- 💾 *کل سورس‌های برنده شده:* `$total_sources_won`
- 🚫 *تعداد پوچ‌ها:* `$total_nothing`";

      $key = $panel;
    }else{
        // ... (your existing partner stats, you can add wheel stats here too if you want)
    }
    SM($from_id,$d,$message_id,$key, 'MarkDown'); // Make sure parse mode is MarkDown
    $pdo = null;  exit();
}


// Add these new blocks for managing the wheel
elseif($message == 'مدیریت گردونه 🎡'){
    $status_text = ($settings['luckwheel_status'] == 1) ? 'روشن ✅' : 'خاموش ❌';
    $button_text = ($settings['luckwheel_status'] == 1) ? 'خاموش کردن 끄기' : 'روشن کردن 켜기';

    $luckwheel_panel = json_encode(['inline_keyboard'=>[
        [['text'=>$button_text, 'callback_data'=>'toggle_luckwheel']],
        [['text'=>'بستن منو ❌', 'callback_data'=>'close_panel_menu']]
    ]]);

    SM($from_id, "⚙️ به بخش مدیریت گردونه شانس خوش آمدید.\n\n*وضعیت فعلی:* $status_text", $message_id, $luckwheel_panel, 'MarkDown');
    $pdo = null; exit();
}

elseif($message == 'toggle_luckwheel' and in_array($from_id, $Devs)){
    $new_status = ($settings['luckwheel_status'] == 1) ? 0 : 1;
    $pdo->exec("UPDATE panel SET luckwheel_status = '$new_status' WHERE id = '85' LIMIT 1");

    // To show the change immediately, we re-fetch the settings
    $settings['luckwheel_status'] = $new_status; 

    $status_text = ($settings['luckwheel_status'] == 1) ? 'روشن ✅' : 'خاموش ❌';
    $button_text = ($settings['luckwheel_status'] == 1) ? 'خاموش کردن 끄기' : 'روشن کردن 켜기';

    $luckwheel_panel = json_encode(['inline_keyboard'=>[
        [['text'=>$button_text, 'callback_data'=>'toggle_luckwheel']],
        [['text'=>'بستن منو ❌', 'callback_data'=>'close_panel_menu']]
    ]]);

    bot('editMessageText', [
        'chat_id' => $from_id,
        'message_id' => $message_id,
        'text' => "⚙️ به بخش مدیریت گردونه شانس خوش آمدید.\n\n*وضعیت فعلی:* $status_text",
        'reply_markup' => $luckwheel_panel,
        'parse_mode' => 'MarkDown'
    ]);
    $pdo = null; exit();
}

elseif($message == 'close_panel_menu' and in_array($from_id, $Devs)){
    bot('deleteMessage', ['chat_id' => $from_id, 'message_id' => $message_id]);
    $pdo = null; exit();
}
    elseif($message=='فوروارد 📤'){
        $pdo->exec("UPDATE users SET step = 'for_all' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '⚜️ پیام خود را ارسال کنید تا به همه اعضا فوروارد کنم :', $message_id, $back2);
        $pdo = null;  exit();
    }
    
    elseif($users['step']=='for_all' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        $pdo->exec("UPDATE send_all SET type = 'forward', count = '0', from_id = '$from_id', msg_id = '$message_id' WHERE id = '85' LIMIT 1");
        SM($from_id, 'پیام شما به عنوان فوروارد همگانی تنظیم شد
به زودی به همه کاربران ربات ارسال میگردد !', $message_id, $back2);
        $pdo = null;  exit();
    }
    
    elseif($message=='ارسال 📩'){
        $pdo->exec("UPDATE users SET step = 'send_all' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '⚜️ پیام خود را در قالب متن یا عکس کپشن دار ارسال کنید تا به همه اعضا ارسال کنم :', $message_id, $back2);
        $pdo = null;  exit();
    }
    
    elseif($users['step']=='send_all' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        if(isset($update->message->text)){
            $file_type = 'text';
            $text = $update->message->text ?: '-';
        }
        if(isset($update->message->photo)){
            $file_type = 'photo';
            $media = $update->message->photo[2]->file_id ?: '-';
        }
        if(isset($update->message->video)){
            $file_type = 'video';
            $media = $update->message->video->file_id ?: '-';
        }
        if(isset($update->message->document)){
            $file_type = 'document';
            $media = $update->message->document->file_id ?: '-';
        }
        $caption = $update->message->caption ?: '-';
        $pdo->exec("UPDATE send_all SET type = 'send', count = '0', sendtype = '$file_type', text = '$text', media = '$media', caption = '$caption', from_id = '$from_id' WHERE id = '85' LIMIT 1");
        SM($from_id, 'پیام شما به عنوان پیام همگانی تنظیم شد
به زودی به همه کاربران ربات ارسال میگردد !', $message_id, $back2);
        $pdo = null;  exit();
    }
        
    elseif($message=='📍 ارسال سورس'){
        $pdo->exec("UPDATE users SET step = 'sendBNR' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '📍 لطفا بنر سورس را ارسال کنید :', $message_id, $back2);
        $pdo = null;  exit();
    }
        
    elseif($users['step']=='sendBNR' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        if(isset($update->message->photo)){
            $pdo->exec("UPDATE users SET step = 'sendTitle' WHERE id = '$from_id' LIMIT 1");
            $photo_id = end($update->message->photo)->file_id;
            $data = json_decode(file_get_contents('data/data.json'), true);
            $data['cover'] = $photo_id;
            $data['like_count']=0;
            $data['down_count']=0;
            $data['buy_count']=0;
            file_put_contents('data/data.json', json_encode($data, 448));
            $getfile = bot('getfile', ['file_id' => $photo_id])->result->file_path;
            file_put_contents('data/cover.jpg', file_get_contents('https://api.telegram.org/file/bot'.TOKEN_POKER.'/'.$getfile));
            SM($from_id, '📍 لطفا نام سورس را ارسال کنید :', $message_id, $back2);
            $pdo = null;  exit();
        } else {
            SM($from_id, 'فقط ارسال عکس مجاز است !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    
    elseif($users['step']=='sendTitle' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        if(mb_strlen($message) < 301){
            $pdo->exec("UPDATE users SET step = 'sendLang' WHERE id = '$from_id' LIMIT 1");
            $data = json_decode(file_get_contents('data/data.json'), true);
            $data['title'] = $message;
            file_put_contents('data/data.json', json_encode($data, 448));
            SM($from_id, '📍 لطفا زبان توسعه یافته سورس را ارسال کنید :', $message_id, $back2);
            $pdo = null;  exit();
        } else {
            SM($from_id, 'متن وارد شده طولانی میباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
        
    elseif($users['step']=='sendLang' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        if(mb_strlen($message) < 101){
            $pdo->exec("UPDATE users SET step = 'sendCaption' WHERE id = '$from_id' LIMIT 1");
            $data = json_decode(file_get_contents('data/data.json'), true);
            $data['lang'] = $message;
            file_put_contents('data/data.json', json_encode($data, 448));
            SM($from_id, '📍 توضیحات مربوط به سورس را ارسال نمایید :', $message_id, $back2);
            $pdo = null;  exit();
        } else {
            SM($from_id, 'متن وارد شده طولانی میباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
        
    elseif($users['step']=='sendCaption' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        if(mb_strlen($message) < 1001){
            $pdo->exec("UPDATE users SET step = 'sendType' WHERE id = '$from_id' LIMIT 1");
            $data = json_decode(file_get_contents('data/data.json'), true);
            $data['caption'] = $message;
            file_put_contents('data/data.json', json_encode($data, 448));
            if($admins['type']==2){
                $key=json_encode(['keyboard'=>[
                    [['text'=>'stars'], ['text'=>'zm']],
                    [['text'=>'coin'], ['text'=>'free']],
                    [['text'=>'بازگشت 🔙']]
                ], 'resize_keyboard'=>true]);
            } else {
                $key=json_encode(['keyboard'=>[
                    [['text'=>'stars'], ['text'=>'zm']],
                    [['text'=>"vip"],['text'=>'coin'], ['text'=>'free']],
                    [['text'=>'بازگشت 🔙']]
                ], 'resize_keyboard'=>true]);
            }
      
            
            SM($from_id, '📍 نوع سورس را از کیبورد زیر انتخاب نمایید :', $message_id, $key);
            $pdo = null;  exit();
        } else {
            SM($from_id, 'متن وارد شده طولانی میباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }

    elseif($users['step']=='sendType' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        // Add 'zm' to the allowed types
               if(in_array($message, ['vip', 'free', 'coin', 'zm', 'stars'])){

            $data = json_decode(file_get_contents('data/data.json'), true);
            $data['type'] = $message;
            file_put_contents('data/data.json', json_encode($data, 448));

            if($message=='vip' || $message=='coin'){
                $pdo->exec("UPDATE users SET step = 'sendAmount' WHERE id = '$from_id' LIMIT 1");
                $prompt = ($message == 'vip') ? 'هزینه سورس را به ریال وارد کنید :' : 'هزینه سورس را به سکه وارد کنید :';
                SM($from_id, '📍 ' . $prompt, $message_id, $back2);
                $pdo = null;  exit();
            }
             if($message=='stars'){
                $pdo->exec("UPDATE users SET step = 'sendStarsAmount' WHERE id = '$from_id' LIMIT 1");
                SM($from_id, '📍 هزینه سورس را به Stars (ستاره) وارد کنید:', $message_id, $back2);
                $pdo = null; exit();
            }
            if($message=='zm'){
                // New step for ZM type
                $pdo->exec("UPDATE users SET step = 'sendZmMembers' WHERE id = '$from_id' LIMIT 1");
                SM($from_id, '📍 تعداد اعضای مورد نیاز برای دریافت این سورس را وارد کنید:', $message_id, $back2);
                $pdo = null; exit();
            }
            if($message=='free'){
                $pdo->exec("UPDATE users SET step = 'sendLimit' WHERE id = '$from_id' LIMIT 1");
                SM($from_id, '📍 تعداد محدودیت دانلود بصورت رایگان را وارد کنید :', $message_id, $back2);
                $pdo = null;  exit();
            }
        } else {
            if($admins['type']==2){
                $key=json_encode(['keyboard'=>[
                    [['text'=>'zm']],
                    [['text'=>'coin'], ['text'=>'free']]
                [['text'=>'بازگشت 🔙']]
            ], 'resize_keyboard'=>true]);
            
            }else{
                  $key=json_encode(['keyboard'=>[
                       [['text'=>'zm']],
                    [['text'=>"vip"],['text'=>'coin'], ['text'=>'free']]
                [['text'=>'بازگشت 🔙']]
            ], 'resize_keyboard'=>true]);
            }
            SM($from_id, '📍 نوع سورس را از کیبورد زیر انتخاب نمایید :', $message_id, $key);
            $pdo = null;  exit();
        }
    }
            // ======================= START: NEW STEP FOR STARS AMOUNT =======================
    elseif($users['step']=='sendStarsAmount' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        if(is_numeric($message)){
            $pdo->exec("UPDATE users SET step = 'sendFile' WHERE id = '$from_id' LIMIT 1");
            $data = json_decode(file_get_contents('data/data.json'), true);
            $data['amount'] = $message;
            file_put_contents('data/data.json', json_encode($data, 448));
            SM($from_id, '📍 سورس را ارسال نمایید :', $message_id, $back2);
            $pdo = null;  exit();
        } else {
            SM($from_id, 'فقط ارسال اعداد بصورت لاتین مجاز میباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    // ======================= END: NEW STEP FOR STARS AMOUNT =======================
        
           // New step handler for ZM member count
    elseif($users['step']=='sendZmMembers' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        if(is_numeric($message) && $message > 0){
            $pdo->exec("UPDATE users SET step = 'sendFile' WHERE id = '$from_id' LIMIT 1");
            $data = json_decode(file_get_contents('data/data.json'), true);
            // We use the 'amount' field to store the required member count for ZM sources
            $data['amount'] = $message;
            file_put_contents('data/data.json', json_encode($data, 448));
            SM($from_id, '📍 سورس را ارسال نمایید :', $message_id, $back2);
            $pdo = null;  exit();
        } else {
            SM($from_id, 'فقط ارسال عدد صحیح و بزرگتر از صفر مجاز میباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    
    
    
    elseif(in_array($users['step'],['sendAmount','sendcoin']) and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        if(is_numeric($message)){
            $pdo->exec("UPDATE users SET step = 'sendFile' WHERE id = '$from_id' LIMIT 1");
            $data = json_decode(file_get_contents('data/data.json'), true);
            $data['amount'] = $message;
            file_put_contents('data/data.json', json_encode($data, 448));
            SM($from_id, '📍 سورس را ارسال نمایید :', $message_id, $back2);
            $pdo = null;  exit();
        } else {
            SM($from_id, 'فقط ارسال اعداد بصورت لاتین مجاز میباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    
    elseif($users['step']=='sendLimit' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        if(is_numeric($message)){
            $pdo->exec("UPDATE users SET step = 'sendFile' WHERE id = '$from_id' LIMIT 1");
            $data = json_decode(file_get_contents('data/data.json'), true);
            $data['limit'] = $message;
            file_put_contents('data/data.json', json_encode($data, 448));
            SM($from_id, '📍 سورس را ارسال نمایید :', $message_id, $back2);
            $pdo = null;  exit();
        } else {
            SM($from_id, 'فقط ارسال اعداد بصورت لاتین مجاز میباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    
    elseif($users['step']=='sendFile' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        if(isset($update->message->document)){
            $file_id = $update->message->document->file_id;
            $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
            $data = json_decode(file_get_contents('data/data.json'), true);
            $stamp = imagecreatefrompng('data/mark.png');
            $im = imagecreatefromjpeg('data/cover.jpg');
            $marge_right = 10;
            $marge_bottom = 10;
            $sx = imagesx($stamp);
            $sy = imagesy($stamp);
            imagecopy($im, $stamp, imagesx($im) - $sx - $marge_right, imagesy($im) - $sy - $marge_bottom, 0, 0, imagesx($stamp), imagesy($stamp));
            imagepng($im , 'data/cover.png');
            imagedestroy($im);
            
            // ======================= START: ADDED POSTING LOGIC FOR STARS =======================
            if($data['type']=='stars'){
                $msg_id = bot('sendPhoto',[
                    'chat_id'=>$brand_username,
                    'photo'=>new CURLFile('data/cover.png'),
                    'caption'=>'📂 '.$data['title']."\n"
                             .'➰ ایدی سورس : id*'."\n"
                             .'📝زبان توسعه دهنده  : '.$data['lang']."\n\n"
                             .'📜 توضیحات بیشتر :'."\n".$data['caption']."\n\n"
                             .'🆔 @'.$channel['username'],
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
                        [['text'=>'خرید با Stars ⭐️', 'url'=>'https://t.me/'.$bot_user.'?start=stars_']],
                        [['text'=>'⭐️ قیمت: '.$data['amount'].' ستاره', 'callback_data'=>'JShow']],
                        [['text'=>'❤️ (0)', 'callback_data'=>'slike_'], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                    ]])
                ])->result->message_id;
    
                $pdo->exec("INSERT INTO files (id, cover, title, lang, caption, ads_type, limits, amount, file_id) VALUES ('$msg_id', '{$data['cover']}', '{$data['title']}', '{$data['lang']}', '{$data['caption']}', 'stars', '0', '{$data['amount']}', '$file_id');");
                
                $query = $pdo->query("SELECT * FROM files WHERE id = '$msg_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                
                bot('editMessageCaption', [
                    'chat_id'=>$brand_username,
                    'message_id'=>$msg_id,
                    'caption'=>'📂 '.$query['title']."\n"
                             .'➰ ایدی سورس : '.$query['id']."\n"
                             .'📝زبان توسعه دهنده  : '.$query['lang']."\n\n"
                             .'📜 توضیحات بیشتر :'."\n".$query['caption']."\n\n"
                             .'🆔 @'.$channel['username'],
                    'parse_mode'=>'html',
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
                        [['text'=>'خرید با Stars ⭐️', 'url'=>'https://t.me/'.$bot_user.'?start=stars_'.$query['id']]],
                        [['text'=>'⭐️ قیمت: '.$query['amount'].' ستاره', 'callback_data'=>'JShow']],
                        [['text'=>'❤️ (0)', 'callback_data'=>'slike_'.$query['id']], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                    ]])
                ]);
            }
            // ======================= END: ADDED POSTING LOGIC FOR STARS =======================
            
              if($data['type']=='zm'){
                // Logic for posting a ZM source to the channel
                $msg_id = bot('sendPhoto',[
                    'chat_id'=>$brand_username,
                    'photo'=>new CURLFile('data/cover.png'),
                    'caption'=>'📂 '.$data['title'].'
➰ ایدی سورس : id*
📝زبان توسعه دهنده  : '.$data['lang'].'

📜 توضیحات بیشتر :
'.$data['caption'].'

🎁 برای دریافت این سورس باید '.$data['amount'].' نفر را به کانال دعوت کنید.

🆔 @'.$channel['username'],
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
	    		        [['text'=>'دریافت با عضوگیری 👥', 'url'=>'https://t.me/'.$bot_user.'?start=zm_']],
                        [['text'=>'❤️ (0)', 'callback_data'=>'flike_'], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                    ]])
                ])->result->message_id;

                $amount = $data['amount'] ?: 0;
                $limit = 0; // No limit for ZM
                $pdo->exec("INSERT INTO files (id, cover, title, lang, caption, ads_type, limits, amount, file_id) VALUES ('$msg_id', '{$data['cover']}', '{$data['title']}', '{$data['lang']}', '{$data['caption']}', 'zm', '$limit', '$amount', '$file_id')");

                bot('editMessageCaption', [
                    'chat_id'=>$brand_username,
                    'message_id'=>$msg_id,
                    'caption'=>'📂 '.$data['title'].'
➰ ایدی سورس : '.$msg_id.'
📝زبان توسعه دهنده  : '.$data['lang'].'

📜 توضیحات بیشتر :
'.$data['caption'].'

🎁 برای دریافت این سورس باید '.$data['amount'].' نفر را به کانال دعوت کنید.

🆔 @'.$channel['username'],
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
	    		        [['text'=>'دریافت با عضوگیری 👥', 'url'=>'https://t.me/'.$bot_user.'?start=zm_'.$msg_id]],
                        [['text'=>'❤️ (0)', 'callback_data'=>'flike_'.$msg_id], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                    ]])
                ]);
            }
            
            
            if($data['type']=='free'){
                $msg_id = bot('sendPhoto',[
                    'chat_id'=>$brand_username,
                    'photo'=>new CURLFile('data/cover.png'),
                    'caption'=>'📂 '.$data['title'].'
➰ ایدی سورس : id*
📝زبان توسعه دهنده  : '.$data['lang'].'

📜 توضیحات بیشتر :
'.$data['caption'].'

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
	    		        [['text'=>'دریافت سورس 📥', 'url'=>'https://t.me/'.$bot_user.'?start=file_']],
                        [['text'=>'📊 آمار دانلود بصورت رایگان : '.$data['down_count'].' از '.$data['limits'], 'callback_data'=>'PejvakSource']],
                        [['text'=>'❤️ ('.$data['like_count'].')', 'callback_data'=>'flike_'], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    // [['text'=>"💛 خرید هاست مناسب این سورس","url"=>"https://gelinserver.ir/index.php?rp=/store/hostbot"]]
                    ]])
                ])->result->message_id;
                $amount=$data['amount']?:0;
                $pdo->exec("INSERT INTO files (id, cover, title, lang, caption, ads_type, limits, amount, file_id) VALUES ('$msg_id', '{$data['cover']}', '{$data['title']}', '{$data['lang']}', '{$data['caption']}', '{$data['type']}', '{$data['limit']}', '$amount', '$file_id')");
                $query = $pdo->query("SELECT * FROM files WHERE id = '$msg_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                bot('editMessageCaption', [
                    'chat_id'=>$brand_username,
                    'message_id'=>$msg_id,
                    'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : '.$query['id'].'
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر : 
'.$query['caption'].'

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
                    'parse_mode'=>'html',
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
                        [['text'=>'دریافت سورس 📥', 'url'=>'https://t.me/'.$bot_user.'?start=file_'.$query['id']]],
                        [['text'=>'📊 آمار دانلود بصورت رایگان : 0 از '.$query['limits'], 'callback_data'=>'PejvakSource']],
                        [['text'=>'❤️ (0)', 'callback_data'=>'flike_'.$query['id']], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    // [['text'=>"💛 خرید هاست مناسب این سورس","url"=>"https://gelinserver.ir/index.php?rp=/store/hostbot"]]
                    ]])
                ]);
            }
            else if($data['type']=='vip'){
                $data = json_decode(file_get_contents('data/data.json'), true);
                $amount=$data['amount']?:0;
                $msg_id = bot('sendPhoto',[
                    'chat_id'=>$brand_username,
	                'photo'=>new CURLFile('data/cover.png'),
	               // 'photo'=>$data['cover'],
	                'caption'=>'📂 '.$data['title'].'
➰ ایدی سورس : id*
📝زبان توسعه دهنده  : '.$data['lang'].'

📜 توضیحات بیشتر :
'.$data['caption'].'

🆔 @'.$channel['username'],
    	            'reply_markup'=>json_encode(['inline_keyboard'=>[
	    		        [['text'=>'دریافت سورس 📥', 'url'=>'https://t.me/'.$bot_user.'?start=buy_']],
                        [['text'=>'💰قیمت '.number_format($data['amount'] / 10).' تومان'.' | '.number_format($data['amount']).' ریال','callback_data'=>"BuyBTN"]],
						[['text'=>'💎 تعداد فروش موفق : '.$data['down_count'],'callback_data'=>'selles']],
                        [['text'=>'❤️ ('.$data['like_count'].')', 'callback_data'=>'vlike_'], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    // [['text'=>"💛 خرید هاست مناسب این سورس","url"=>"https://gelinserver.ir/index.php?rp=/store/hostbot"]]
                    ]])
                ])->result->message_id;
                                try {
                $pdo->exec("INSERT INTO files (id, cover, title, lang, caption, ads_type, limits, amount, file_id) VALUES ('$msg_id', '{$data['cover']}', '{$data['title']}', '{$data['lang']}', '{$data['caption']}', 'vip', '0', '{$data['amount']}', '$file_id');");
} catch(PDOException $e){
     file_put_contents('e.txt',$e->getMessage());
     file_put_contents("stepdata.json",$data);
     sm($from_id,"$msg_id id");
    die();
}
                $query = $pdo->query("SELECT * FROM files WHERE id = '$msg_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                bot('editMessageCaption', [
                    'chat_id'=>$brand_username,
                    'message_id'=>$msg_id,
                    'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : '.$query['id'].'
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر : 
'.$query['caption'].'

🆔 @'.$channel['username'],
                    'parse_mode'=>'html',
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
                        [['text'=>'دریافت سورس 📥', 'url'=>'https://t.me/'.$bot_user.'?start=buy_'.$query['id']]],
                        [['text'=>'💰قیمت '.number_format($data['amount'] / 10).' تومان'.' | '.number_format($data['amount']).' ریال','callback_data'=>"BuyBTN"]],
                        [['text'=>'💎 تعداد فروش موفق : '.$data['down_count'],'callback_data'=>'selles']],
                        [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'vlike_'.$query['id']], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    // [['text'=>"💛 خرید هاست مناسب این سورس","url"=>"https://gelinserver.ir/index.php?rp=/store/hostbot"]]
                    ]])
                ]);
            }
            else if($data['type']=='coin'){
                $data = json_decode(file_get_contents('data/data.json'), true);
                $amount=$data['amount']?:0;
                $msg_id = bot('sendPhoto',[
                    'chat_id'=>$brand_username,
	                'photo'=>new CURLFile('data/cover.png'),
	                'caption'=>'📂 '.$data['title'].'
➰ ایدی سورس : id*
📝زبان توسعه دهنده  : '.$data['lang'].'

📜 توضیحات بیشتر :
'.$data['caption'].'


🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
    	            'reply_markup'=>json_encode(['inline_keyboard'=>[
	    		        [['text'=>'دریافت سورس 📥', 'url'=>'https://t.me/'.$bot_user.'?start=file_']],
                        [['text'=>'💰قیمت '.$data['amount'].' سکه', 'callback_data'=>'buy']],
                        [['text'=>'❤️ ('.$data['like_count'].')', 'callback_data'=>'cclike_'], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    // [['text'=>"💛 خرید هاست مناسب این سورس","url"=>"https://gelinserver.ir/index.php?rp=/store/hostbot"]]
                    ]])
                ])->result->message_id;
                                try {
                $pdo->exec("INSERT INTO files (id, cover, title, lang, caption, ads_type, limits, amount, file_id) VALUES ('$msg_id', '{$data['cover']}', '{$data['title']}', '{$data['lang']}', '{$data['caption']}', 'coin', '0', '{$data['amount']}', '$file_id');");
} catch(PDOException $e){
     file_put_contents('e.txt',$e->getMessage());
     file_put_contents("stepdata2.json",$data);
    die();
}
                $query = $pdo->query("SELECT * FROM files WHERE id = '$msg_id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                bot('editMessageCaption', [
                    'chat_id'=>$brand_username,
                    'message_id'=>$msg_id,
                    'caption'=>'📂 '.$query['title'].'
➰ ایدی سورس : '.$query['id'].'
📝زبان توسعه دهنده  : '.$query['lang'].'

📜 توضیحات بیشتر : 
'.$query['caption'].'


🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
                    'parse_mode'=>'html',
                    'reply_markup'=>json_encode(['inline_keyboard'=>[
                        [['text'=>'دریافت سورس 📥', 'url'=>'https://t.me/'.$bot_user.'?start=file_'.$query['id']]],
                        [['text'=>'💰قیمت '.$data['amount'].' سکه', 'callback_data'=>'buy']],
                        [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'cclike_'.$query['id']], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    // [['text'=>"💛 خرید هاست مناسب این سورس","url"=>"https://gelinserver.ir/index.php?rp=/store/hostbot"]]
                    ]])
                ]);
            }
    	    @unlink('data/data.json');
    		@unlink('data/cover.jpg');
    		@unlink('data/cover.png');
    		SM($from_id, 'با موفقیت به کانال ارسال شد ✅', $message_id, $back2);
    		$pdo = null;  exit();
        } else {
            SM($from_id, 'فقط ارسال فایل مجاز میباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
        
    elseif($message=='📍 حذف سورس'){
        $pdo->exec("UPDATE users SET step = 'delSRC' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '📍 لطفا آیدی سورس را ارسال کنید :', $message_id, $back2);
        $pdo = null;  exit();
    }
        
    elseif($users['step']=='delSRC' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        bot('deleteMessage', [
            'chat_id'=>$brand_username,
            'message_id'=>$message
        ]);
        $query = $pdo->query("SELECT * FROM files WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $pdo->exec("DELETE FROM files WHERE id = '$message' LIMIT 1");
        $likes = $pdo->query("SELECT * FROM likes WHERE file_id = '$message'")->fetchAll();
        foreach($likes as $result){
            $pdo->exec("DELETE FROM likes WHERE file_id = '{$result['id']}' LIMIT 1");
        }
        SM($from_id, '✅ با موفقیت حذف شد', $message_id, $back2);
        $pdo = null;  exit();
    }

    elseif($message=='اهدا سکه 🌀'){
        $pdo->exec("UPDATE users SET step = 'donate_coin' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '⚜️ مقدار سکه جهت اهدا را به صورت عدد لاتین ارسال نمایید :', $message_id, $back2);
        $pdo = null;  exit();
    }

    elseif($users['step']=='donate_coin' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        if(is_numeric($message)){
            $pdo->exec("UPDATE users SET step = 'coin_donate_$message' WHERE id = '$from_id' LIMIT 1");
            SM($from_id, 'مقدار '.$message.' سکه با موفقیت تایید شد. حالا ایدی عددی کاربر مورد نظر را ارسال نمایید :', $message_id, $back2);
            $pdo = null;  exit();
        } else {
            SM($from_id, 'توجه ! فقط به صورت عدد لاتین مجاز میباشد یک بار دیگر سعی کنید.', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    
    elseif(strpos($users['step'], 'coin_donate_') !== false){
        $coin = explode('_', $users['step'])[2];
        $sql2 = $pdo->query("SELECT id FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if(isset($sql2['id'])){
            $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
            $users2 = $pdo->query("SELECT * FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $cn = $users2['coin'] + $coin;
            $pdo->exec("UPDATE users SET coin = '$cn' WHERE id = '$message' LIMIT 1");
            SM($message, 'کاربر گرامی❗️'."\n".'از طرف مدیریت مقدار '.$coin.' سکه به حساب شما افزوده گردید .');
            SM($from_id, 'با موفقیت ارسال گردید !', $message_id, $back2);
            $pdo = null;  exit();
        } else {
            SM($from_id, 'ایدی عددی کاربری که ارسال کردید در لیست اعضای ربات وجود ندارد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    
    elseif($message=='ℹ️ کسر سکه'){
        $pdo->exec("UPDATE users SET step = 'deduction_coin' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '⚜️ مقدار سکه جهت کسر را به صورت عدد لاتین ارسال نمایید :', $message_id, $back2);
        $pdo = null;  exit();
    }
    
    elseif($users['step']=='deduction_coin' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        if(is_numeric($message)){
            $pdo->exec("UPDATE users SET step = 'coin_deduction_$message' WHERE id = '$from_id' LIMIT 1");
            SM($from_id, 'مقدار '.$message.' سکه با موفقیت تایید شد. حالا ایدی عددی کاربر مورد نظر را ارسال نمایید :', $message_id, $back2);
            $pdo = null;  exit();
        } else {
            SM($from_id, 'توجه ! فقط به صورت عدد لاتین مجاز میباشد یک بار دیگر سعی کنید.', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    
    elseif(strpos($users['step'], 'coin_deduction_') !== false){
        $coin = explode('_', $users['step'])[2];
        $sql2 = $pdo->query("SELECT id FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if(isset($sql2['id'])){
            $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
            $users2 = $pdo->query("SELECT * FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $cn = $users2['coin'] - $coin;
            $pdo->exec("UPDATE users SET coin = '$cn' WHERE id = '$message' LIMIT 1");
            SM($message, 'کاربر گرامی❗️'."\n".'از طرف مدیریت مقدار '.$coin.' سکه از حساب شما کسر گردید .');
            SM($from_id, 'با موفقیت کسر گردید !', $message_id, $back2);
            $pdo = null;  exit();
        } else {
            SM($from_id, 'ایدی عددی کاربری که ارسال کردید در لیست اعضای ربات وجود ندارد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    
    elseif($message=='سکه همگانی ⛓'){
        $pdo->exec("UPDATE users SET step = 'coin_all' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '⚜️ مقدار سکه جهت اهدا #همگانی را به صورت عدد لاتین ارسال نمایید :', $message_id, $back2);
        $pdo = null;  exit();
    }
    
    elseif($users['step']=='coin_all' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        if(is_numeric($message)){
            $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
            $pdo->exec("UPDATE send_all SET type = 'ehda', count = '0', value = '$message', from_id = '$from_id' WHERE id = '85' LIMIT 1");
            SM($from_id, 'مقدار سکه مورد نظر جهت ارسال به همه کاربران تنظیم شد
به زودی به همه کاربران ربات ارسال میگردد !', $message_id, $back2);
            $pdo = null;  exit();
        } else {
            SM($from_id, 'توجه ! فقط به صورت عدد لاتین مجاز میباشد یک بار دیگر سعی کنید.', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    
    elseif($message=='بلاک کردن ⚠️'){
        $pdo->exec("UPDATE users SET step = 'block_user' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '⚜️ آیدی عددی کاربر مورد نظر را ارسال نمایید :', $message_id, $back2);
        $pdo = null;  exit();
    }
    
    elseif($users['step']=='block_user' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        $sql2 = $pdo->query("SELECT id,block FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if(isset($sql2['id'])){
            if($sql2['block']==0){
                $pdo->exec("UPDATE users SET coin = '0', block = '1' WHERE id = '$message' LIMIT 1");
                SM($message, 'شما توسط مدیران ربات بلاک شدید!', null, $remove);
                SM($from_id, 'کاربر مورد نظر با موفقیت بلاک شد!', $message_id, $back2);
                $pdo = null;  exit();
            } else {
                SM($from_id, 'کاربر مورد نظر از قبل بلاک میباشد !', $message_id, $back2);
                $pdo = null;  exit();
            }
        } else {
            SM($from_id, 'کاربر مورد نظر عضو ربات نمیباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    
    elseif($message == 'آنبلاک کردن 🌀'){
        $pdo->exec("UPDATE users SET step = 'unblock_user' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '⚜️ آیدی عددی کاربر مورد نظر را ارسال نمایید :', $message_id, $back2);
        $pdo = null;  exit();
    }
    
    elseif($users['step']=='unblock_user' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        $sql2 = $pdo->query("SELECT id,block FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if(isset($sql2['id'])){
            if($sql2['block']==1 or $sql2['block']==2){
                $pdo->exec("UPDATE users SET block = '0' WHERE id = '$message' LIMIT 1");
                SM($message, 'شما توسط مدیران ربات آنبلاک شدید!', null, $menu);
                SM($from_id, 'کاربر مورد نظر با موفقیت آنبلاک شد!', $message_id, $back2);
                $pdo = null;  exit();
            } else {
                SM($from_id, 'کاربر مورد نظر از قبل آنبلاک میباشد !', $message_id, $back2);
                $pdo = null;  exit();
            }
        } else {
            SM($from_id, 'کاربر مورد نظر عضو ربات نمیباشد !', $message_id, $back2);
            $pdo = null;  exit();            }
        }
     elseif($message == '🗣 افزودن همکار'){
        $pdo->exec("UPDATE users SET step = 'insert_admin_' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '⚜️ آیدی عددی کاربر مورد نظر را ارسال نمایید :', $message_id, $back2);
        $pdo = null;  exit();
    }
    
    elseif($users['step']=='insert_admin_' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        $sql = $pdo->query("SELECT id FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if(isset($sql['id'])){
            if(!in_array($message, explode('^', $settings['admins']))){
                $admins .= $settings['admins'].$message.'^';
                $pdo->exec("UPDATE panel SET admins = '$admins' WHERE id = '85' LIMIT 1");
                $time = date("H:i:s");
                $pdo->exec("insert into Admins (id,type,grower,posts,timeup) values ('$message','2','$from_id','^','$time-$date')");
                SM($message, 'سطح دسترسی شما به 2 افزایش یافت.', null, $panel);
                SM($from_id, 'کاربر مورد نظر با موفقیت به لیست مدیران ربات افزوده شد !', $message_id, $back2);
                $pdo = null;  exit();
            } else {
                SM($from_id, 'کاربر مورد نظر از قبل مدیر میباشد !', $message_id, $back2);
                $pdo = null;  exit();
            }
        } else {
            SM($from_id, 'کاربر مورد نظر عضو ربات نمیباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    
    elseif($message == 'افزودن مدیر 👨‍💻'){
        $pdo->exec("UPDATE users SET step = 'insert_admin' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '⚜️ آیدی عددی کاربر مورد نظر را ارسال نمایید :', $message_id, $back2);
        $pdo = null;  exit();
    }
    
    elseif($users['step']=='insert_admin' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        $sql = $pdo->query("SELECT id FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if(isset($sql['id'])){
            if(!in_array($message, explode('^', $settings['admins']))){
                $admins .= $settings['admins'].$message.'^';
                $pdo->exec("UPDATE panel SET admins = '$admins' WHERE id = '85' LIMIT 1");
                SM($message, 'مقام شما با موفقیت به مدیر ربات تغییر یافت !', null, $panel);
                SM($from_id, 'کاربر مورد نظر با موفقیت به لیست مدیران ربات افزوده شد !', $message_id, $back2);
                $pdo = null;  exit();
            } else {
                SM($from_id, 'کاربر مورد نظر از قبل مدیر میباشد !', $message_id, $back2);
                $pdo = null;  exit();
            }
        } else {
            SM($from_id, 'کاربر مورد نظر عضو ربات نمیباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    
    elseif($message == 'حذف مدیر 👨‍💻'){
        $pdo->exec("UPDATE users SET step = 'delete_admin' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '⚜️ آیدی عددی کاربر مورد نظر را ارسال نمایید :', $message_id, $back2);
        $pdo = null;  exit();
    }
    
    elseif($users['step']=='delete_admin' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        $sql = $pdo->query("SELECT id FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if(isset($sql['id'])){
            if(in_array($message, explode('^', $settings['admins']))){
                $admins = str_replace($message.'^', null, $settings['admins']);
                $pdo->exec("UPDATE panel SET admins = '$admins' WHERE id = '85' LIMIT 1");
                SM($message, 'مقام شما از مدیر ربات به کاربر عادی تغییر یافت !', null, $menu);
                SM($from_id, 'کاربر مورد نظر با موفقیت از لیست مدیران ربات حذف شد !', $message_id, $back2);
                $pdo = null;  exit();
            } else {
                SM($from_id, 'کاربر مورد نظر از قبل مدیر نمیباشد !', $message_id, $back2);
                $pdo = null;  exit();
            }
        } else {
            SM($from_id, 'کاربر مورد نظر عضو ربات نمیباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    }
    
    elseif(strpos($message, '🔑 کلید پاور') !== false){
        $explode = explode(' ', $message);
        $match[2] = str_replace(['[', ']'], null, $explode[3]);
        $type = str_replace(['ON', 'OFF'],['خاموش' ,'روشن'], $match[2]);
        if($match[2]=='ON'){ 
            $power=0;
        } else {
            $power=1;
        }
        $pdo->exec("UPDATE panel SET power = '$power' WHERE id = '85' LIMIT 1");
        SM($from_id, 'ربات با موفقیت '.$type.' شد ✔️', $message_id, $back2);
        $pdo = null;  exit();
    }

    elseif($message=="📮 اطلاعات کاربر"){
        $pdo->exec("UPDATE users SET step = 'get_user_info' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '⚜️ آیدی عددی کاربر مورد نظر را ارسال نمایید :', $message_id, $back2);
        $pdo = null;  exit();
    }

    elseif($users['step']=="get_user_info"and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        $sql = $pdo->query("SELECT id FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $get = $pdo->query("SELECT * FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if(isset($sql['id'])){
     
             $phone_number = ($get['phone_number'] !=0) ?$get['phone_number']." | هویت محرز شده✅" :  "عدم احراز هویت  ❌";
            SM($from_id, '⏺ اطلاعات کاربر خاطی در  '.$bot_name.'
            
            🤷‍♀️ هویت : '.$phone_number.'
            
            🌀 شناسه عددی :{['.$message.'](tg://user?id='.$message.')}
            💰 موجودی حساب : '.$get['coin'].' سکه
            👥 تعداد زیر مجموعه : '.$get['subset'].' نفر
            
            📥 تعداد فایل های دریافت شده : '.$get['down_count'].' فایل
            ❤️ تعداد لایک انجام شده : '.$get['like_count'].' لایک
            💳 تعداد خرید انجام شده : '.$get['buy_count'].' فایل', $message_id, $panel, 'markdown');
                    $pdo = null;
      
        }else {
            SM($from_id, 'کاربر مورد نظر عضو ربات نمیباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
    } elseif($message=="تنظیمات ⚙️"){
        $pdo->exec("UPDATE users SET step = 'none' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '✅ به بخش تنظیمات خوش آمدید!', $message_id, $managment);
        $pdo = null;  exit();
    }

    #-----------------------------------#
    elseif($message=="🪝 پاکسازی تعلیق ها"){
       
        $susp = $pdo->query("SELECT id FROM users where block=2")->rowcount();
        
        if($susp >0){
sm($from_id,"🏎 ادمین عزیز ،
تعداد $susp کاربر در حالت تعلیق قرار دارند ، آیا مایل هستید اینان را به حالت عادی بازگردانید؟

+ توجه کنید که در حالت عادی محدودیتی ندارند!ً",$message_id,json_encode(['inline_keyboard'=>[
[['text'=>"پاکسازی معلق ها",'callback_data'=>"del_sus"]],
]]));
    }else{
        sm($from_id,"🍺 تعلیقی ندارم داش تعطیلیه",$message_id,$managment);
    }
}

elseif($message=="del_sus"){
 
       
      
    $query = $pdo->query("SELECT id FROM users where block=2")->fetchAll();
    foreach($query as $users){
        $pdo->exec("update users set block='0' where id={$users['id']}");
        
        sm($users['id'],"⚽️ کاربر عزیز شما از لیست تعلیقی های ما حذف شدید!
#همگانی",null,$menu);
$i+=1;
        
    }
  
      bot('editmessagetext',[
       'chat_id'=>$from_id,
       'message_id'=>$message_id,
       'text'=>"💐 تمام کاربران از لیست معلقی ها حذف شدند.\n\n تعداد افراد که از لیست حذف شدند $i کاربر است."
       ]);
}
    #-----------------------------------#
    elseif($message=="⚡️ جریمه کاربر متخلف"){
        $pdo->exec("UPDATE users SET step = 'jarimeh' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '🙂 آیدی عددی کاربر کونی را وارد نمایید :', $message_id, $back2);
        $pdo = null;  exit();
    }
    #-----------------------------------#
    elseif($users['step']=='jarimeh' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $pdo->exec("UPDATE users SET step = 'motherisbone' WHERE id = '$from_id' LIMIT 1");
        $sql = $pdo->query("SELECT id FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $get = $pdo->query("SELECT * FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
      
        if(isset($sql['id'])){
            $logs = json_decode(file_get_contents('data/logs.json'), true);
            $logs['target_user'] = $message;
            file_put_contents('data/logs.json', json_encode($logs, 448));
    
            SM($from_id, '⏺ اطلاعات کاربر متخلف در  '.$bot_name.'

            🌀 شناسه عددی :{['.$message.'](tg://user?id='.$message.')}
            💰 موجودی حساب : '.$get['coin'].' سکه
            👥 تعداد زیر مجموعه : '.$get['subset'].' نفر
            
            📥 تعداد فایل های دریافت شده : '.$get['down_count'].' فایل
            ❤️ تعداد لایک انجام شده : '.$get['like_count'].' لایک
            💳 تعداد خرید انجام شده : '.$get['buy_count'].' فایل'."\n\n
            ✅ در خط اول تعداد سکه های جدید کاربر را وارد کنید.
            ✅ در خط دوم توضیحات مجازات کاربر را وارد کنید.", $message_id, $back2, 'markdown');


        }else {
            SM($from_id, 'کاربر مورد نظر عضو ربات نمیباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }

    }
    #-----------------------------------#
    elseif($users['step']=='motherisbone' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $pdo->exec("UPDATE users SET step = 'none' WHERE id = '$from_id' LIMIT 1");
        $logs = json_decode(file_get_contents('data/logs.json'), true);
        $ex = explode("\n",$message);
        $sql = $pdo->query("SELECT id FROM users WHERE id = {$logs['target_user']} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
       
        $pdo->exec("UPDATE users SET coin = '$ex[0]' WHERE id = {$logs['target_user']} LIMIT 1");  
        if($ex[0] !==null  and $ex[1] !==null){
        SM($from_id,"❌ کاربر {$logs['target_user']} جریمه شد و  سکه های جدید وی به ".$ex[0]." تغییر یافت!

        🏮 علت مجازات  {$ex[1]}",$message_id,$panel);
        SM($logs['target_user'],"❌اخطار مدیریت❌

        📍 کاربر محترم :
        
        ➕ شما به علت $ex[1]  توسط مدیریت کل جریمه شدید و تکرار این تخلف سبب قطع دسترسی همیشگی شما در ربات خواهد شد!
        
        🦈 سکه های جدید شما $ex[0]",$message_id,$menu);
        $pdo = null;  exit();
        }

    }
    #-----------------------------------#
      elseif($message=="💾 سورس به کاربر"){
        $pdo->exec("UPDATE users SET step = 'source_send_touser' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '⚜️ آیدی عددی کاربر مورد نظر را ارسال نمایید :', $message_id, $back2);
        $pdo = null;  exit();
    }
    #-----------------------------------#
     elseif($users['step']=='source_send_touser' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $pdo->exec("UPDATE users SET step = 'source_id_sendus' WHERE id = '$from_id' LIMIT 1");
        $sql = $pdo->query("SELECT id FROM users WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if(isset($sql['id'])){
            $data = json_decode(file_get_contents('data/data.json'), true);
            $data['usid'] = $message;
            file_put_contents('data/data.json', json_encode($data));
            SM($from_id, '📥 شماره سورس را برای ارسال به کاربر '.$message.' ارسال فرمایید :', $message_id, $back2);
            
            
        } else {
            SM($from_id, 'کاربر مورد نظر عضو ربات نمیباشد !', $message_id, $back2);
            $pdo = null;  exit();
        }
        
     }
     #-----------------------------------#
      elseif($users['step']=='source_id_sendus' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $pdo->exec("UPDATE users SET step = 'NULL' WHERE id = '$from_id' LIMIT 1");
        $sql  = $pdo->query("SELECT id FROM files WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $file = $pdo->query("SELECT * FROM files WHERE id = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $data = json_decode(file_get_contents('data/data.json'), true);
        if(isset($sql['id'])){
        SM($from_id, '✅ سورس '.$message.' با موفقیت به کاربر '.$data['usid'].'  ارسال گردید.', $message_id, $panel);
        
    $from = "info@codezed.ir";
    $to = "yardankasa@gmail.com";
    $subject = "🗣 اطلاعیه رسمی $brand_name";
    $messagei = "<h1>📍 {$file['title']}
✅ کاربر عزیز سورس ".$message." از طرف مدیریت برای شما ارسال گردیده ست.

🤷‍♀️ این سورس می تواند نسخه جدید ، یا رفع ایراد شده باشد،پس حتما دانلود کنید.

👇 برای دانلود به ربات تلگرامی https://t.me/pejvakrobot رفته و با همان حساب که خرید نموده اید پیوی ربات را چک کنید.";
    $headers = "From:" . $from;
    mail($to,$subject,$messagei, $headers);
        
        
        bot('SendDocument',[
                'chat_id'=>$data['usid'],
                'document'=>$file['file_id'],
                'caption'=>"📍 {$file['title']}
✅ کاربر عزیز سورس ".$message." از طرف مدیریت برای شما ارسال گردیده ست.

🤷‍♀️ این سورس می تواند نسخه جدید ، یا رفع ایراد شده باشد،پس حتما دانلود کنید.",
            ]);
       
        }else {
            SM($from_id, '❌ سورس مورد نظر وجود ندارد ، آیدی درست را ارسال کنید :', $message_id, $back2);
           
        }
   
      }
      
           elseif($message=="🧰 مدیریت سورس"){
        $pdo->exec("UPDATE users SET step = 'mangment-source' WHERE id = '$from_id' LIMIT 1");
        SM($from_id, '✅ به بخش مدیریت سورس خوش آمدید!
        👍 آیدی سورس مورد نظرتان را وارد نمایید :', $message_id, $back2);
        $pdo = null; 
    }
    #-----------------------------------#
    elseif($users['step']=='mangment-source'and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){

        
        $sql = $pdo->query("SELECT `id` FROM `files` WHERE `id` = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $get = $pdo->query("SELECT * FROM `files` WHERE `id` = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if(isset($sql['id'])){
$pdo->exec("UPDATE users SET step = 'man-sou-2' WHERE id = '$from_id' LIMIT 1");

            $logs = json_decode(file_get_contents('data/logs.json'), true);
            $logs['target_source'] = $message;
            file_put_contents('data/logs.json', json_encode($logs, 448));
            
            $news = ($get['ads_type']=="free") ? $news="ویرایش محدودیت" : "" ;
            $cham = ($get['ads_type']=="vip") ? "ویرایش قیمت" : "" ;
            bot('sendDocument', [
                'chat_id'=>$from_id,
                'document'=>$get['file_id'],
                'caption'=>'📂 '.$get['title'].'
➰ ایدی سورس : <code>'.$get['id'].'</code>
📝زبان توسعه دهنده  : '.$get['lang'].'

📜 توضیحات بیشتر :
<pre>'.$get['caption'].'</pre>

',
            'parse_mode'=>'html',
            'reply_markup'=>json_encode(['inline_keyboard'=>[
            [['text'=>"مشاهده در کانال",'url'=>"https://t.me/".str_replace('@', '', $brand_username)."/$message"],['text'=>"جزئیات",'callback_data'=>"ginfo_$message"]]
            ]])
        ]);
bot('sendmessage',[
    'chat_id'=>$from_id,
    'text'=>"جهت ویرایش هر بخش از منوی زیر انتخاب فرمایید :",
    'reply_markup'=> json_encode(['keyboard'=>[
            [['text'=>'ویرایش سورس'],['text'=>'ویرایش کپشن'],['text'=>'ویرایش عنوان']],
            [['text'=>'ویرایش زبان'],['text'=>$news],['text'=>$cham]],
            [['text'=>'بازگشت 🔙']]
            ]])
        ]);

        }else{
           sm($from_id,"* آیدی سورس اشتباه است!");
        }
    }
  elseif(strpos($message, 'ginfo_') !== false){
        $id = str_replace('ginfo_', null, $message);
        
      $files = $pdo->query("SELECT * FROM files WHERE id = '$id' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        bot('answerCallbackQuery', [
            'callback_query_id'=> $update->callback_query->id,
            'text' =>"Time : ".date("H:i:s")."\n - آیدی سورس  : $id\n+ محدودیت دانلود : {$files['limits']}\n+ تعداد دانلود : {$files['down_count']}\n+ نوع سورس : {$files['ads_type']}",
            'show_alert' =>true
        
        ]);
        $pdo = null;  
    }
    #-----------------------------------#
    elseif($users['step']=='man-sou-2' and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
     
        $logs = json_decode(file_get_contents('data/logs.json'), true);
        $logs['edit_source'] = 'yes';
        file_put_contents('data/logs.json', json_encode($logs, 448));

        switch($message){
            case'ویرایش سورس':
                $pdo->exec("UPDATE users SET step = 'edit-file-source' WHERE id = '$from_id' LIMIT 1");
                sm($from_id,'✏️ خیلی خب اکنون فایل جدید را ارسال نمایید تا جایگزین کنم :',$message_id,$back2);
                break;
                case'ویرایش کپشن':
                    $pdo->exec("UPDATE users SET step = 'edit-caption-source' WHERE id = '$from_id' LIMIT 1");
                    sm($from_id,'🔖 خیلی خب اکنون کپشن جدید را وارد نمایید تا ویرایش کنم :',$message_id,$back2);

                    break;
                    case'ویرایش عنوان':
                        $pdo->exec("UPDATE users SET step = 'edit-title-source' WHERE id = '$from_id' LIMIT 1");
                        sm($from_id,'🔖 خیلی خب اکنون عنوان جدید را وارد نمایید تا ویرایش کنم :',$message_id,$back2);

                        break;
                        case'ویرایش زبان':
                            $pdo->exec("UPDATE users SET step = 'edit-lang-source' WHERE id = '$from_id' LIMIT 1");
                            sm($from_id,'🔖 خیلی خب اکنون زبان جدید را وارد نمایید تا ویرایش کنم :',$message_id,$back2);
    
                            break;
                              break;
                        case'ویرایش محدودیت':
                            $pdo->exec("UPDATE users SET step = 'edit-limit-source' WHERE id = '$from_id' LIMIT 1");
                            sm($from_id,'🔖 خیلی خب اکنون تعداد محدودیت جدید را وارد کنید تا ویرایش کنم :',$message_id,$back2);
    
                            break;
                            
                            case'ویرایش قیمت':
                            $pdo->exec("UPDATE users SET step = 'edit-amount-source' WHERE id = '$from_id' LIMIT 1");
                            sm($from_id,'🔰 گودرت عزیز الان قیمت جدید را وارد بنمایید : ',$message_id,$back2);
    
                            break;
                            // default :
                            // sm($from_id,'🙂',$message_id,$panel);
                            // break;



        }
    }
    #-----------------------------------#
    elseif($logs['edit_source']=="yes" and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
        $logs['edit_source'] = 'no';
        file_put_contents('data/logs.json', json_encode($logs, 448));
        $query = $pdo->query("SELECT * FROM files WHERE id = '{$logs['target_source']}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        if($users['step']=='edit-file-source'){
            if(isset($update->message->document)){
                $pdo->exec("UPDATE users SET step = 'none' WHERE id = '$from_id' LIMIT 1");
                $file_id = $update->message->document->file_id;
                $pdo->exec("UPDATE `files` SET `file_id` = '$file_id' WHERE id = '{$logs['target_source']}' LIMIT 1");

                sm($from_id,'➕ فایل جدید با موفقیت جایگزین فایل پیشین گردید!',$message_id,$panel);

            }else{

            sm($from_id,'فقط فایل ارسال کنید.');

            }
           
        }

        if($users['step']=='edit-caption-source'){

            if(isset($message)){

                $cap ="$message";

                $pdo->exec("UPDATE users SET step = 'none' WHERE id = '$from_id' LIMIT 1");
            
                $pdo->exec("UPDATE `files` SET `caption` = '$message' WHERE id = '{$logs['target_source']}' LIMIT 1");
               
              
                sm($from_id,'➕ کپشن جدید با موفقیت جایگزین کپشن پیشین گردید!',$message_id,$panel);

            }else{

            sm($from_id,'فقط متن برای کپشن ارسال کنید.');
        }

    }

    if($users['step']=='edit-title-source'){
            if(isset($message)){
                $til = "$message";
                $pdo->exec("UPDATE users SET step = 'none' WHERE id = '$from_id' LIMIT 1");
            
                $pdo->exec("UPDATE `files` SET `title` = '$message' WHERE id = '{$logs['target_source']}' LIMIT 1");

                sm($from_id,'➕ عنوان جدید با موفقیت جایگزین عنوان پیشین گردید!',$message_id,$panel);

            }else{

            sm($from_id,'فقط متن برای عنوان ارسال کنید.');
        }

    }

 if($users['step']=='edit-lang-source'){
            if(isset($message)){
                $lan = "$message";
                $pdo->exec("UPDATE users SET step = 'none' WHERE id = '$from_id' LIMIT 1");
            
                $pdo->exec("UPDATE `files` SET `lang` = '$message' WHERE id = '{$logs['target_source']}' LIMIT 1");

                sm($from_id,'➕ زبان جدید با موفقیت جایگزین زبان پیشین گردید!',$message_id,$panel);

            }else{

            sm($from_id,'فقط متن برای زبان سورس ارسال کنید.');
        }

    }
    
     if($users['step']=='edit-amount-source'){
            if(isset($message)){
                $amount = "$message";
                $pdo->exec("UPDATE users SET step = 'none' WHERE id = '$from_id' LIMIT 1");
            
                $pdo->exec("UPDATE `files` SET `amount` = '$message' WHERE id = '{$logs['target_source']}' LIMIT 1");

                sm($from_id,'➕ قیمت سورس تغییر یافت!',$message_id,$panel);

            }else{

            sm($from_id,'لطفا فقط عدد وارد نمایید :');
        }

    }
    
     if($users['step']=='edit-limit-source'){
            if(isset($message)){
        
                $lim = "$message";
                $pdo->exec("UPDATE users SET step = 'none' WHERE id = '$from_id' LIMIT 1");
            
                $pdo->exec("UPDATE `files` SET `limits` = '$message' WHERE id = '{$logs['target_source']}' LIMIT 1");

                sm($from_id,'➕ محدودیت سورس تغییر یافت.',$message_id,$panel);

            }else{

            sm($from_id,'فقط عدد برای محدودیت سورس ارسال کنید.');
        }

    }
    
    

    $caption = ($cap !=null) ? $cap : $query['caption'];
    $title   = ($til !=null) ? $til : $query['title'];
    $language = ($lan !=null) ? $lan : $query['lang'];
    $limition = ($lim !=null) ? $lim : $query['limits'];
    $amountion= ($amount !=null) ? "$amount" :$query['amount'];
    if($query['ads_type']=='vip'){
        bot('editMessageCaption', [
                            'chat_id'=>$brand_username,
                            'message_id'=>$logs['target_source'],
                            'caption'=>'📂 '.$title.'
➰ ایدی سورس : '.$query['id'].'
📝زبان توسعه دهنده  : '.$language.'
        
📜 توضیحات بیشتر : 
'.$caption.'
  
➕ این پست آخرین بار در '.$date_en.' ویرایش شده است.

🆔 @'.$channel['username'],
                            'parse_mode'=>'html',
                            'reply_markup'=>json_encode(['inline_keyboard'=>[
                                [['text'=>'دریافت سورس 📥', 'url'=>'https://t.me/'.$bot_user.'?start=buy_'.$query['id']]],
                               [['text'=>'💰قیمت '.number_format($amountion / 10).' تومان'.' | '.number_format($amountion).' ریال','callback_data'=>"BuyBTN"]],
                                    [['text'=>'💎 تعداد فروش موفق : '.$query['down_count'],'callback_data'=>'selles']],
                                [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'vlike_'.$query['id']], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    // [['text'=>"💛 خرید هاست مناسب این سورس","url"=>"https://gelinserver.ir/index.php?rp=/store/hostbot"]]
                            ]])
                        ]);
        }
     else if($query['ads_type']=='coin'){
         
                bot('editMessageCaption', [
                                  'chat_id'=>$brand_username,
                                  'message_id'=>$logs['target_source'],
                                  'caption'=>'📂 '.$title.'
➰ ایدی سورس : '.$query['id'].'
📝زبان توسعه دهنده  : '.$language.'
                                           
📜 توضیحات بیشتر : 
'.$caption.'
 
➕ این پست آخرین بار در '.$date_en.' ویرایش شده است.

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
                                  'parse_mode'=>'html',
                                  'reply_markup'=>json_encode(['inline_keyboard'=>[
                                      [['text'=>'دریافت سورس 📥', 'url'=>'https://t.me/'.$bot_user.'?start=file_'.$query['id']]],
                                      [['text'=>'💰قیمت '.$query['amount'].' سکه', 'callback_data'=>'BuyBTN']],
                                      [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'cclike_'.$query['id']], ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    // [['text'=>"💛 خرید هاست مناسب این سورس","url"=>"https://gelinserver.ir/index.php?rp=/store/hostbot"]]
                                  ]])
                              ]);
              }
           else if($query['ads_type']=='free'){
                    bot('editMessageCaption', [
                                        'chat_id'=>$brand_username,
                                        'message_id'=>$logs['target_source'],
                                        'caption'=>'📂 '.$title.'
➰ ایدی سورس : '.$query['id'].'
📝زبان توسعه دهنده  : '.$language.'
                    
📜 توضیحات بیشتر : 
'.$caption.'
                    
🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.
 
➕ این پست آخرین بار در '.$date_en.' ویرایش شده است.

🆔 @'.$channel['username'],
                                        'parse_mode'=>'html',
                                        'reply_markup'=>json_encode(['inline_keyboard'=>[
                                            [['text'=>'دریافت سورس 📥', 'url'=>'https://t.me/'.$bot_user.'?start=file_'.$query['id']]],
                                            [['text'=>'📊 آمار دانلود بصورت رایگان : '.$query['down_count'].' از '.$limition, 'callback_data'=>'DNLoad']],
                                            [['text'=>'❤️ ('.$query['like_count'].')', 'callback_data'=>'flike_'.$query['id']],  ['text'=>'🤖 '.$bot_name,'url'=>'https://t.me/'.$bot_user.'?start']],
                                    // [['text'=>"💛 خرید هاست مناسب این سورس","url"=>"https://gelinserver.ir/index.php?rp=/store/hostbot"]]
                                        ]])
                                    ]);
                    }
    
               

        
    }
#----------------------------------------------#
elseif($message=="👁‍🗨 ارسال گروهی سورس"){
       $pdo->exec("UPDATE users SET step = 'sendgroupsource' WHERE id = '$from_id' LIMIT 1");
       sm($from_id,"• آیدی سورس را ارسال نمایید : ",$message_id,$back2);
}
#----------------------------------------------#
elseif($users['step']=="sendgroupsource" and !in_array($message, ['بازگشت 🔙', '/start', '/panel'])){
      $pdo->exec("UPDATE users SET step = 'none' WHERE id = '$from_id' LIMIT 1");
    $query = $pdo->query("SELECT * FROM re_payments WHERE `file`='$message'")->fetchAll();
   
    $file = $pdo->query("SELECT * FROM `files` WHERE `id` = '$message' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
   $number = 0;
  if(isset($file['id'])){
    foreach($query as $sources){
        if($sources['status']!=='nopay'){
            $number +=1;
                 $ems = bot('SendDocument',[
                'chat_id'=>$sources['fromid'],
                'document'=>$file['file_id'],
                'caption'=>"📍 {$file['title']}
✅ کاربر عزیز سورس ".$message." از طرف مدیریت برای شما ارسال گردیده ست.

🤷‍♀️ این سورس می تواند نسخه جدید ، یا رفع ایراد شده باشد،پس حتما دانلود کنید.

#ارسال_گروهی_vip #مدیریت",
            ]);
            $msi = $ems->result->message_id;
            sm($sources['fromid'],"📛 هشدار هر گونه نشر مجدد ممنوع می باشد و با شناسایی فرد متخلف با وی به شدت برخورد خواهد شد.",$msi);
        }

}
   }else{
       sm($from_id,"• این سورس وجود ندارد!",$message_id,$panel);
       exit();  $pdo =null;
   }
 
 sm($from_id,"☑️  به تمام کسانی که سورس $message را خریداری کرده بودند نسخه جدید را ارسال کردم. 
 ✔️ به $number نفر ارسال کردم.");   
}

}