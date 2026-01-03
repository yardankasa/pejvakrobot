<?php
// bot.php v3.1 - Debugging Version

// VERY FIRST LINE: Try to log that the script was executed at all.
// This helps diagnose parse errors in included files like config.php
file_put_contents('bot_startup_error.log', '[' . date('Y-m-d H:i:s') . "] bot.php script execution started.\n", FILE_APPEND);

// Now, wrap the require in a try-catch block to specifically catch parse errors
try {
    require 'config.php';
} catch (Throwable $e) { // Catch any error, including ParseError
    file_put_contents('bot_startup_error.log', '[' . date('Y-m-d H:i:s') . "] FATAL: Error in config.php: " . $e->getMessage() . "\n", FILE_APPEND);
    exit(); // Stop execution immediately
}

file_put_contents('bot_startup_error.log', '[' . date('Y-m-d H:i:s') . "] config.php loaded successfully.\n", FILE_APPEND);

// --- Get Update from Telegram ---
$update = json_decode(file_get_contents("php://input"), TRUE);

if (!$update) {
    file_put_contents('bot_startup_error.log', '[' . date('Y-m-d H:i:s') . "] Received an empty update from Telegram.\n", FILE_APPEND);
    exit();
}

// --- Database Connection ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    file_put_contents('bot_startup_error.log', '[' . date('Y-m-d H:i:s') . "] DB Connection Failed: " . $e->getMessage() . "\n", FILE_APPEND);
    exit();
}
file_put_contents('bot_startup_error.log', '[' . date('Y-m-d H:i:s') . "] Database connected successfully.\n", FILE_APPEND);


// --- Message Handler ---
if (isset($update["message"])) {
    $message = $update["message"];
    $chat_id = $message["chat"]["id"];
    $text = $message["text"] ?? '';



    if (isset($message['reply_to_message']) && !empty($text)) {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$chat_id]);
        $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin_user && ($admin_user['role'] === 'admin' || $admin_user['role'] === 'super_admin')) {
            $replied_text = $message['reply_to_message']['text'] ?? '';
            if (preg_match('/#(\d+)/', $replied_text, $matches)) {
                $ticket_id = (int)$matches[1];
                handle_admin_bot_reply($pdo, $ticket_id, $chat_id, $text);
                sendMessage($chat_id, "پاسخ شما برای تیکت #$ticket_id ثبت شد.");
                exit();
            }
        }
    }
}

function handle_admin_bot_reply($pdo, $ticket_id, $admin_id, $message) {
    $stmt = $pdo->prepare("SELECT user_id, status FROM tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ticket && $ticket['status'] !== 'closed') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, sender_type, message) VALUES (?, ?, 'admin', ?)");
        $stmt->execute([$ticket_id, $admin_id, $message]);
        
        $stmt = $pdo->prepare("UPDATE tickets SET status = 'answered', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $pdo->commit();

        notify_user($ticket['user_id'], "پاسخ جدیدی برای تیکت شما (#$ticket_id) ثبت شد.");
    }
}

function sendMessage($chat_id, $text, $reply_markup = null) {
    $params = ['chat_id' => $chat_id, 'text' => $text];
    if ($reply_markup) { $params['reply_markup'] = $reply_markup; }
    
    $ch = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function notify_user($chat_id, $message) {
    sendMessage($chat_id, $message);
}
