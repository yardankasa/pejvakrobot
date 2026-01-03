<?php
// ticket_bot_handler_integrated.php
// این فایل توسط bot.php اصلی فراخوانی می‌شود و نباید به صورت مستقیم وبهوک شود.

function handle_ticket_update($update) {
    if (!isset($update->message) || !isset($update->message->reply_to_message) || empty($update->message->text)) {
        return; // اگر پیام، ریپلای یا متنی نیست، ادامه نده
    }

    try {
        $pdor = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdor->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        return; // در صورت خطا در اتصال به دیتابیس، ادامه نده
    }
    
    $message = $update->message;
    $chat_id = $message->chat->id;
    $text = $message->text;
    
    $stmt = $pdor->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$chat_id]);
    $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin_user && ($admin_user['role'] === 'admin' || $admin_user['role'] === 'super_admin')) {
        $replied_text = $message->reply_to_message->text ?? '';
        
        if (preg_match('/تیکت جدید \(#(\d+)\)/', $replied_text, $matches) || preg_match('/پاسخ جدید از کاربر در تیکت #(\d+)/', $replied_text, $matches)) {
            $ticket_id = (int)$matches[1];
            
            // --- Logic from handle_admin_bot_reply ---
            $stmt_ticket = $pdor->prepare("SELECT user_id, status FROM tickets WHERE id = ?");
            $stmt_ticket->execute([$ticket_id]);
            $ticket = $stmt_ticket->fetch(PDO::FETCH_ASSOC);

            if ($ticket && $ticket['status'] !== 'closed') {
                $pdor->beginTransaction();
                $stmt_insert = $pdor->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, sender_type, message) VALUES (?, ?, 'admin', ?)");
                $stmt_insert->execute([$ticket_id, $chat_id, $text]);
                
                $stmt_update = $pdor->prepare("UPDATE tickets SET status = 'answered', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt_update->execute([$ticket_id]);
                $pdor->commit();

                // Notify user
                $user_notification_text = "پاسخ جدیدی برای تیکت شما (#$ticket_id) ثبت شد. لطفاً از طریق دکمه پشتیبانی در ربات مشاهده کنید.";
                bot('sendMessage', ['chat_id' => $ticket['user_id'], 'text' => $user_notification_text]);
                
                // Confirm to admin
                bot('sendMessage', ['chat_id' => $chat_id, 'text' => "پاسخ شما برای تیکت #$ticket_id ثبت و برای کاربر ارسال شد."]);
            }
            
            exit(); // مهم: بعد از پردازش پاسخ، اجرای اسکریپت متوقف شود
        }
    }
}
?>
