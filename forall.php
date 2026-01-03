<?php
$date = date("Y/m/d");
echo time();
date_default_timezone_set('Asia/Tehran');
$dater = date("H"); $dater = $dater; $dater = $dater.date(":i");
//  echo "<br>".$dater."<br>";
require_once 'config.php'; 


$send = $pdo->query("SELECT * FROM send_all WHERE id = '85' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$send_vip = $pdo->query("SELECT * FROM send_all WHERE id = '86' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$menu = json_encode(['keyboard'=>[
     
        [['text'=>'برترین سورس ها 📊'], ['text'=>'🗳 جدید ترین ها']],
        [['text'=>'برترین ها 🌟'],['text'=>'🗂 ارسال سورس']],
        [['text'=>'حساب من 👤'],['text'=>'💰 افزایش سکه']],
        [['text'=>'🆘'],['text'=>'🔎'],['text'=>'📚']],
    ],'resize_keyboard'=>true,'input_field_placeholder'=>'🤚  چیکار کنیم؟']);

function bot($method, $data=[]){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot'.TOKEN_POKER.'/'.$method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    return json_decode(curl_exec($ch));
}






if($send['type']=='forward'){
    $query = $pdo->query("SELECT id FROM users LIMIT 100 OFFSET {$send['count']}")->fetchAll();
    foreach($query as $users){
        bot('ForwardMessage',[
            'chat_id'=>$users['id'],
            'from_chat_id'=>$send['from_id'],
            'message_id'=>$send['msg_id']
        ]);
    }
    $cn = $send['count']+100;
    $pdo->exec("UPDATE send_all SET count = '$cn' WHERE id = '85' LIMIT 1");
    if($send['count'] + 100 >= $pdo->query("SELECT id FROM users")->rowcount()){
        bot('sendMessage',[
            'chat_id'=>$send['from_id'],
            'text'=>'پیام شما با موفقیت به همه اعضا فوروارد شد !'
        ]);
        $pdo->exec("UPDATE send_all SET type = '-', count = '0', from_id = '0', msg_id = '0' WHERE id = '85' LIMIT 1");
    }
}

if($send['type']=='source'){
    $bot_name = bot('GetMe')->result->first_name;
    $bot_user = bot('GetMe')->result->username;
    $query = $pdo->query("SELECT id FROM users LIMIT 100 OFFSET {$send['count']}")->fetchAll();
    foreach($query as $users){
        $data = $pdo->query("SELECT * FROM files WHERE id = '{$send['msg_id']}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if($data['ads_type']=='free'){
            bot('sendPhoto', [
                'chat_id'=>$users['id'],
                'photo'=>$data['cover'],
                'caption'=>'📂 '.$data['title'].'
➰ ایدی سورس : '.$data['id'].'
📝زبان توسعه دهنده  : '.$data['lang'].'

📜 توضیحات بیشتر : 
'.$data['caption'].'

🎁 با دعوت دوستان به ربات با لینک اختصاصی خود میتوانید این سورس را رایگان دریافت کنید.

🆔 @'.$channel['username'],
'parse_mode'=>'html',
'reply_markup'=>json_encode(['inline_keyboard'=>[
    [['text'=>'دریافت سورس 📥', 'callback_data'=>'start file_'.$data['id']]],
    [['text'=>'📊 آمار دانلود بصورت رایگان :  '.$data['down_count'].' از '.$data['limits'], 'callback_data'=>'flike_'.$data['id']]],
    [['text'=>'❤️ ('.$data['like_count'].')', 'callback_data'=>'flike_'.$data['id']], ['text'=>'📢 '.$brand_name,'url'=>'https://t.me/'.str_replace('@', '', $brand_username)]]
    ]])
    ]);
        }
    }
    $cn = $send['count']+100;
    $pdo->exec("UPDATE send_all SET count = '$cn' WHERE id = '85' LIMIT 1");
    if($send['count'] + 100 >= $pdo->query("SELECT id FROM users")->rowcount()){
    $cn = $pdo->query("SELECT id FROM users")->rowcount()-$send['count'];
    $cn = $send['count'] + $cn;
    $pdo->exec("UPDATE send_all SET count = '$cn' WHERE id = '85' LIMIT 1");
    }
    if($send['count'] == $pdo->query("SELECT id FROM users")->rowcount()){
        $pdo->exec("UPDATE send_all SET type = '-', count = '0', from_id = '0', msg_id = '0' WHERE id = '85' LIMIT 1");
    }
}
//-----------------------------------------------------------

if($send_vip['type']=='source_vip'){
    $bot_name = bot('GetMe')->result->first_name;
    $bot_user = bot('GetMe')->result->username;
    $query = $pdo->query("SELECT id FROM users LIMIT 100 OFFSET {$send['count']}")->fetchAll();
    foreach($query as $users){
        $data = $pdo->query("SELECT * FROM files WHERE id = '{$send['msg_id']}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if($data['ads_type']=='vip'){
            bot('sendPhoto', [
                'chat_id'=>$users['id'],
                'photo'=>$data['cover'],
                'caption'=>'📂 '.$data['title'].'
➰ ایدی سورس : '.$data['id'].'
📝زبان توسعه دهنده  : '.$data['lang'].'

📜 توضیحات بیشتر : 
'.$data['caption'].'

🎁 با خرید آنلاین بواسطه درگاه پرداخت می توانید بلافاصله این سورس را دریافت کنید.

🆔 @'.$channel['username'],
'parse_mode'=>'html',
'reply_markup'=>json_encode(['inline_keyboard'=>[
    [['text'=>'خرید سورس 📥', 'callback_data'=>'start down_'.$data['id']]],
    [['text'=>'💵 قیمت'.number_format($data['amount'] /10).' تومان', 'callback_data'=>'flike_'.$data['id']]],
    [['text'=>'❤️ ('.$data['like_count'].')', 'callback_data'=>'flike_'.$data['id']], ['text'=>'📢 '.$brand_name,'url'=>'https://t.me/'.str_replace('@', '', $brand_username)]]
    ]])
    ]);
        }
    }
    $cn = $send['count']+100;
    $pdo->exec("UPDATE send_all SET count = '$cn' WHERE id = '86' LIMIT 1");
    if($send['count'] + 100 >= $pdo->query("SELECT id FROM users")->rowcount()){
    $cn = $pdo->query("SELECT id FROM users")->rowcount()-$send['count'];
    $cn = $send['count'] + $cn;
    $pdo->exec("UPDATE send_all SET count = '$cn' WHERE id = '86' LIMIT 1");
    }
    if($send['count'] == $pdo->query("SELECT id FROM users")->rowcount()){
        $pdo->exec("UPDATE send_all SET type = '-', count = '0', from_id = '0', msg_id = '0' WHERE id = '86' LIMIT 1");
    }
}

//-----------------------------------------------------------
if($send['type']=='send'){
    $query = $pdo->query("SELECT id FROM users LIMIT 100 OFFSET {$send['count']}")->fetchAll();
    foreach($query as $users){
        if($send['sendtype']=='text'){
            if($send['text'] != '-' or $send['text'] != ''){
                bot('sendMessage',[
                    'chat_id'=>$users['id'],
                    'text'=>$send['text'],
                    'parse_mode'=>'html',
                    'reply_markup'=>$menu,
                    'disable_web_page_preview'=>true
                ]);
            }
        }
        if($send['caption']=='-' or $send['caption']==''){
            if($send['sendtype']=='photo'){
                bot('sendPhoto',[
                    'chat_id'=>$users['id'],
                    'photo'=>$send['media'],
                    'reply_markup'=>$menu,
                ]);
            }
            if($send['sendtype']=='video'){
                bot('sendVideo',[
                    'chat_id'=>$users['id'],
                    'video'=>$send['media'],
                    'reply_markup'=>$menu,
                ]);
            }
            if($send['sendtype']=='document'){
                bot('sendDocument',[
                    'chat_id'=>$users['id'],
                    'reply_markup'=>$menu,
                    'document'=>$send['media']
                ]);
            }
        } else {
            if($send['sendtype']=='photo'){
                bot('sendPhoto',[
                    'chat_id'=>$users['id'],
                    'photo'=>$send['media'],
                    'reply_markup'=>$menu,
                    'caption'=>$send['caption'],
                    'parse_mode'=>'html'
                ]);
            }
            if($send['sendtype']=='video'){
                bot('sendVideo',[
                    'chat_id'=>$users['id'],
                    'video'=>$send['media'],
                    'caption'=>$send['caption'],
                    'reply_markup'=>$menu,
                    'parse_mode'=>'html'
                ]);
            }
            if($send['sendtype']=='document'){
                bot('sendDocument',[
                    'chat_id'=>$users['id'],
                    'document'=>$send['media'],
                    'caption'=>$send['caption'],
                    'reply_markup'=>$menu,
                    'parse_mode'=>'html'
                ]);
            }
        }
    }
    $cn = $send['count']+100;
    $pdo->exec("UPDATE send_all SET count = '$cn' WHERE id = '85' LIMIT 1");
    if($send['count'] + 100 >= $pdo->query("SELECT id FROM users")->rowcount()){
        bot('sendMessage',[
            'chat_id'=>$send['from_id'],
            'text'=>'پیام شما با موفقیت به همه اعضا ارسال شد !'
        ]);
        $pdo->exec("UPDATE send_all SET type = '-', count = '0', sendtype = '-', text = '-', caption = '-', media = '-', from_id = '0' WHERE id = '85' LIMIT 1");
    }
}


if($send['type']=='ehda'){
    $query = $pdo->query("SELECT id,coin,block FROM users LIMIT 100 OFFSET {$send['count']}")->fetchAll();
    foreach($query as $users){
        $id = $users['id'];
        $ChannelLock_One=bot('getChatMember', ['chat_id'=>$channel['id'][0], 'user_id'=>$id])->result->status;
        $ChannelLock_Two=bot('getChatMember', ['chat_id'=>$channel['id'][1], 'user_id'=>$id])->result->status;
        if($ChannelLock_One!=='left' or $ChannelLock_Two!=='left'){
            if($users['block'] == 0){
                // if($users['coin'] <= 7){
                    $cn = $users['coin'] + $send['value'];
                    $pdo->exec("UPDATE users SET coin = '$cn' WHERE id = '$id' LIMIT 1");
                    bot('sendMessage',[
                        'chat_id'=>$id,
                        'text'=>'مقدار '.$send['value'].' سکه از طرف مدیران به حساب شما واریز شد!'."\n".'#همگانی',
                        'reply_markup'=>$menu,
                        ]);
                // } else {
                //     bot('sendMessage',[
                //         'chat_id'=>$id,
                //         'text'=>'/start',
                //         'reply_markup'=>$menu,
                //         ]);
                // }
            }
        }
    }
    $cn = $send['count']+100;
    $pdo->exec("UPDATE send_all SET count = '$cn' WHERE id = '85' LIMIT 1");
    if($send['count'] + 100 >= $pdo->query("SELECT id FROM users")->rowcount()){
        bot('sendMessage',[
            'chat_id'=>$send['from_id'],
            'text'=>'به همه اهدا شد !'
        ]);
        $pdo->exec("UPDATE send_all SET type = '-', count = '0', value = '0', from_id = '0' WHERE id = '85' LIMIT 1");
    }
}
// if(date("H:i")=="23:06"){
//     bot('sendmessage',[
//         'chat_id'=>1604140942,
//         'text'=>"rt".date('H:i:s Y/m/d')
//         ]);
// }



if($dater=="23:59" and $logs['last_gif_sent'] !==date("Y/m/d")){
    
$logs = json_decode(file_get_contents('data/logs.json'), true);
$bob  = strtotime("+1 days");
$tt   =  date("Y/m/d",$bob);
$logs['next_gift_weekly'] = $tt;
$logs['last_gif_sent'] = date("Y/m/d");
file_put_contents('data/logs.json', json_encode($logs, 448));
            
    $query = $pdo->query("SELECT * FROM users WHERE daily_subset > '0' AND last_subset='$date'  ORDER BY daily_subset DESC LIMIT 5")->fetchAll();
        if(count($query) > 0){
            $list .= "🎁 چالش  زیرمجموعه گیری برگزار شد و  جوایز به کاربران فعال با بیشترین زیرمجموعه اهدا شد :\n\n";
            $i = 0;
            foreach($query as $result){
                $i = $i + 1; 
                $id    = $result['id'];
                $point = 0;
               switch($i){
                   case'1' :$point = $result['daily_subset'] * 50;$nf = 'اول';break;case'2':$point =$result['daily_subset'] * 50;$nf = 'دوم';break;case'3': $point= $result['daily_subset'] * 50;$nf = 'سوم';break;case'4': $point= $result['daily_subset'] * 50;$nf = 'چهارم';break;case'5':$point=$result['daily_subset'] * 50;$nf = 'پنجم';break;
               }
                 
                 $cn = $result['silver']+$point;
                 $pdo->exec("UPDATE users SET silver = '$cn' WHERE id = '$id' LIMIT 1");

                $subset= $result['daily_subset'];
           
                $list .= "💰 نفر $nf [$id](tg://user?id=$id) \n👥  زیرمجموعه ها : *$subset*\n💎 هدیه : $point نقره\n\n"."➖➖➖➖➖➖➖"."\n\n";
 bot('sendmessage',[
            'chat_id'=>"$id",
            'text'=>"🎖 پژواکی عزیز شما در چالش زیرمجموعه گیری نفر $nf شدید و به همین خاطر به شما $point نقره اهدا  شد!

☺️❤️با زیرمجموعه گیری های مداوم در روز های آینده می توانید برنده سکه های بیشتری باشید!\nبا مراجعه به پژواک کلاب می توانید نقره های خود را تبدیل به سکه کنید!"]);
            }
           
              

       $msg_id = bot('sendmessage',[
            'chat_id'=>"@pejvakevents",
            'text'=>$list."\n\n🔰 تاریخ برگزاری چالش بعدی : $tt\n\n🤨 تو هم میخوای اسمت توی لیست باشه؟\n😎کاری نداره؟! کافیه از بخش زیرمجموعه گیری👥 دوستاتو دعوت کنی!\n\n🆔 @".$channel['username'],
            'parse_mode'=>"markdown",
            'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [['text'=>"🎁 مقدار جوایز اعطا شده",'callback_data'=>"ValueOfGifs"]],
                    [['text'=>"💎 رفتن به ربات $brand_name",'url'=>"T.me/".$channel['bot_id']]]
                ]
            ])])->result->message_id;
            $da = date("Y-m-d H:i:s");
             try {
                $pdo->exec("INSERT INTO history_subset (data,date,msg_id) VALUES ('{$list}','{$da}','$msg_id')");
} catch(PDOException $e){
     file_put_contents('e.txt',$e->getMessage());
    die();
}

            
        } 

 $pdo->exec("UPDATE users SET daily_subset = '0'");
    
    bot('sendmessage',[
        'chat_id'=>1604140942,
        'text'=>"رئیس قرعه کشی روزانه برگزار شد و زیرمجموعه های روزانه ریست شد!"
        ]);
        
                    $pdo = null;  
}
echo "chon ".$dater;
// bot('sendmessage',[
//         'chat_id'=>1604140942,
//         'text'=>"رئیس ، زیرمجموعه های روزانه ریست شدند!"
//         ]);
// if($dater=="01:07"){
    
//     // $pdo->exec("UPDATE users SET daily_subset = '0'");
    
//     bot('sendmessage',[
//         'chat_id'=>1604140942,
//         'text'=>"$dater"
//         ]);
        
// }
$pdo = null;