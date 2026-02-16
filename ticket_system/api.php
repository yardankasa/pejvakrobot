<?php
// api.php v3.2 - Enhanced Features & Improved Security

// --- Advanced Error Handling ---
ob_start();

function shutdown_handler() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        ob_get_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        // Use a temporary log file in case the main log function fails
        file_put_contents('api_fatal_error.log', '['.date('Y-m-d H:i:s')."] FATAL: {$error['message']} in {$error['file']} on line {$error['line']}\n", FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'یک خطای بحرانی در سرور رخ داد. لطفا لاگ را بررسی کنید.']);
    }
}
register_shutdown_function('shutdown_handler');

try {
    header('Content-Type: application/json');
    require 'config_ticket.php';

    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    function log_message($message) {
        $timestamp = date("Y-m-d H:i:s");
        $log_entry = is_string($message) ? $message : print_r($message, true);
        file_put_contents('api.log', "[$timestamp] " . $log_entry . "\n", FILE_APPEND);
    }

    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- New Security Function ---
    function validateTelegramData($initDataStr, $botToken) {
        if (empty($initDataStr) || empty($botToken)) {
             return false;
        }
        parse_str($initDataStr, $initDataArray);
        $check_hash = $initDataArray['hash'] ?? '';
        unset($initDataArray['hash']);
        ksort($initDataArray);
        $data_check_string = '';
        foreach ($initDataArray as $key => $value) {
            $data_check_string .= $key . '=' . $value . "\n";
        }
        $data_check_string = rtrim($data_check_string, "\n");
        $secret_key = hash_hmac('sha256', 'WebAppData', $botToken, true);
        $calculated_hash = hash_hmac('sha256', $data_check_string, $secret_key);
        return hash_equals($calculated_hash, $check_hash);
    }
    // --- End New Security Function ---

    $action = $_GET['action'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Invalid request method.', 405); }
    $initDataStr = $_POST['initData'] ?? '';
    if (empty($initDataStr)) { throw new Exception('Access Denied: No initData.', 403); }

    // --- Use New Validation ---
    
    // if (!defined('BOT_TOKEN') || !validateTelegramData($initDataStr, BOT_TOKEN)) {
    //      throw new Exception('Access Denied: Invalid initData signature.', 403);
    // }
    // --- End Use New Validation ---

    parse_str($initDataStr, $initData);
    $user_data = json_decode($initData['user'], true);
    if (!isset($user_data['id'])) { throw new Exception('Access Denied: Invalid user data.', 403); }

    $user_id = $user_data['id'];
    $user_name = trim(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? ''));
    $username = $user_data['username'] ?? null;

    $stmt = $pdo->prepare("SELECT role, is_banned FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_record = $stmt->fetch(PDO::FETCH_ASSOC);

    $first_name = $user_data['first_name'] ?? null;
    $last_name = $user_data['last_name'] ?? null;
    $photo_url = $user_data['photo_url'] ?? null;

    if (!$user_record) {
        $role = in_array($user_id, SUPER_ADMIN_IDS) ? 'super_admin' : 'user';
        try {
            $stmt = $pdo->prepare("INSERT INTO users (user_id, user_name, username, first_name, last_name, photo_url, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $user_name, $username, $first_name, $last_name, $photo_url, $role]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET user_name=?, username=?, first_name=?, last_name=?, photo_url=? WHERE user_id=?");
                    $stmt->execute([$user_name, $username, $first_name, $last_name, $photo_url, $user_id]);
                } catch (PDOException $e2) {
                    $stmt = $pdo->prepare("UPDATE users SET user_name=?, username=? WHERE user_id=?");
                    $stmt->execute([$user_name, $username, $user_id]);
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (user_id, user_name, username, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $user_name, $username, $role]);
            }
        }
        $user_record = ['role' => $role, 'is_banned' => 0];
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET user_name=?, username=?, first_name=?, last_name=?, photo_url=? WHERE user_id=?");
            $stmt->execute([$user_name, $username, $first_name, $last_name, $photo_url, $user_id]);
        } catch (PDOException $e) {
            $stmt = $pdo->prepare("UPDATE users SET user_name=?, username=? WHERE user_id=?");
            $stmt->execute([$user_name, $username, $user_id]);
        }
    }

    if ($user_record['is_banned'] && !in_array($user_record['role'], ['admin', 'super_admin'])) { throw new Exception('شما از سیستم پشتیبانی مسدود شده‌اید.', 403); }

    $is_admin = ($user_record['role'] === 'admin' || $user_record['role'] === 'super_admin');
    $is_super_admin = ($user_record['role'] === 'super_admin');

    $request_data = $_POST;
    switch ($action) {
        case 'initialize':
            $user_response = array_merge($user_data, [
                'photo_url' => $photo_url ?? $user_data['photo_url'] ?? null
            ]);
            $admin_display = null;
            try {
                $stmt = $pdo->prepare("SELECT admin_display_name FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $admin_display = $stmt->fetchColumn();
            } catch (PDOException $e) { /* column may not exist yet */ }
            echo json_encode(['status' => 'success', 'data' => [
                'role' => $user_record['role'],
                'user' => $user_response,
                'admin_display_name' => $admin_display
            ]]);
            break;
        case 'get_dashboard_stats': get_dashboard_stats($pdo, $is_admin); break;
        case 'get_tickets': get_tickets($pdo, $user_id); break;
        case 'get_all_tickets': get_all_tickets($pdo, $is_admin, $request_data); break;
        case 'get_ticket_details': get_ticket_details($pdo, $user_id, $_GET['ticket_id'] ?? 0, $is_admin); break;
        case 'create_ticket': create_ticket($pdo, $user_id, $user_name, $request_data, $_FILES['attachment'] ?? null); break;
        case 'add_reply': add_reply($pdo, $user_id, $request_data, $_FILES['attachment'] ?? null, $is_admin); break;
        case 'ban_user': toggle_ban_user($pdo, $is_admin, $request_data['user_id_to_toggle'] ?? 0, true); break;
        case 'unban_user': toggle_ban_user($pdo, $is_admin, $request_data['user_id_to_toggle'] ?? 0, false); break;
        case 'reopen_ticket': reopen_ticket($pdo, $user_id, $request_data['ticket_id'] ?? 0, $is_admin); break;
        case 'get_users': get_users($pdo, $is_super_admin, $request_data); break;
        case 'send_direct_message': send_direct_message($pdo, $is_admin, $request_data); break;
        case 'set_user_role': set_user_role($pdo, $is_super_admin, $request_data); break;
        case 'set_admin_display_name': set_admin_display_name($pdo, $is_admin, $user_id, $request_data); break;
        case 'get_admin_display_name': get_admin_display_name($pdo, $user_id); break;
        default: throw new Exception('Invalid action.', 400);
    }

} catch (Exception $e) {
    ob_get_clean();
    log_message("Caught Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $http_code = method_exists($e, 'getCode') && is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 500;
    if (!headers_sent()) {
        http_response_code($http_code);
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

function get_dashboard_stats($pdo, $is_admin) {
    if (!$is_admin) { throw new Exception('Access Denied.', 403); }
    $stats = [];
    $stats['open_tickets'] = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open', 'answered')")->fetchColumn();
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['tickets_today'] = $pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    echo json_encode(['status' => 'success', 'data' => $stats]);
}

function get_tickets($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id, title, department, status, updated_at FROM tickets WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$user_id]);
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function get_all_tickets($pdo, $is_admin, $data) {
    if (!$is_admin) { throw new Exception('Access Denied.', 403); }
    $page = isset($data['page']) ? (int)$data['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $params = [];
    $sql = "SELECT SQL_CALC_FOUND_ROWS t.id, t.user_id, u.user_name, u.username, u.is_banned, t.title, t.department, t.status, t.updated_at, t.created_at FROM tickets t JOIN users u ON t.user_id = u.user_id WHERE 1=1";
    if (!empty($data['search'])) {
        $sql .= " AND (t.title LIKE ? OR u.user_name LIKE ? OR u.username LIKE ? OR t.id = ?)";
        $params[] = '%' . $data['search'] . '%';
        $params[] = '%' . $data['search'] . '%';
        $params[] = '%' . $data['search'] . '%';
        $params[] = $data['search'];
    }
    if (!empty($data['status'])) { $sql .= " AND t.status = ?"; $params[] = $data['status']; }
    if (!empty($data['department'])) { $sql .= " AND t.department = ?"; $params[] = $data['department']; }
    $sql .= " ORDER BY t.updated_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_records = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    foreach ($tickets as &$t) { $t['first_name'] = null; $t['last_name'] = null; $t['photo_url'] = null; }
    echo json_encode(['status' => 'success', 'data' => ['tickets' => $tickets, 'total_pages' => ceil($total_records / $limit), 'current_page' => $page]]);
}

function get_ticket_details($pdo, $user_id, $ticket_id, $is_admin) {
    $sql = "SELECT t.*, u.user_name, u.username, u.is_banned FROM tickets t JOIN users u ON t.user_id = u.user_id WHERE t.id = ?";
    $params = [$ticket_id];
    if (!$is_admin) { $sql .= " AND t.user_id = ?"; $params[] = $user_id; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) { throw new Exception('تیکت یافت نشد.', 404); }
    $ticket['first_name'] = null; $ticket['last_name'] = null; $ticket['photo_url'] = null;
    try {
        $stmt_ext = $pdo->prepare("SELECT first_name, last_name, photo_url FROM users WHERE user_id = ?");
        $stmt_ext->execute([$ticket['user_id']]);
        $ext = $stmt_ext->fetch(PDO::FETCH_ASSOC);
        if ($ext) { $ticket['first_name'] = $ext['first_name']; $ticket['last_name'] = $ext['last_name']; $ticket['photo_url'] = $ext['photo_url']; }
    } catch (PDOException $e) { /* columns may not exist */ }
    try {
        $stmt = $pdo->prepare("SELECT tm.id, tm.sender_id, tm.sender_type, tm.admin_display_name, tm.message, tm.created_at, ta.file_path, ta.original_name, ta.file_type FROM ticket_messages tm LEFT JOIN ticket_attachments ta ON tm.id = ta.message_id WHERE tm.ticket_id = ? ORDER BY tm.created_at ASC");
        $stmt->execute([$ticket_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("SELECT tm.id, tm.sender_id, tm.sender_type, tm.message, tm.created_at, ta.file_path, ta.original_name, ta.file_type FROM ticket_messages tm LEFT JOIN ticket_attachments ta ON tm.id = ta.message_id WHERE tm.ticket_id = ? ORDER BY tm.created_at ASC");
        $stmt->execute([$ticket_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($messages as &$m) { $m['admin_display_name'] = null; }
    }
    $other_tickets = [];
    if ($is_admin) {
        $stmt_other = $pdo->prepare("SELECT id, title, status FROM tickets WHERE user_id = ? AND id != ? ORDER BY updated_at DESC LIMIT 5");
        $stmt_other->execute([$ticket['user_id'], $ticket_id]);
        $other_tickets = $stmt_other->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['status' => 'success', 'data' => ['ticket' => $ticket, 'messages' => $messages, 'other_tickets' => $other_tickets]]);
}

function handle_attachment($pdo, $message_id, $file) {
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $max_size = 5 * 1024 * 1024;
        if ($file['size'] > $max_size) { throw new Exception('حجم فایل نمی‌تواند بیشتر از 5 مگابایت باشد.', 400); }
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime_type, $allowed_types)) { throw new Exception('فقط فایل‌های تصویری (JPG, PNG, GIF, WEBP) مجاز هستند.', 400); }
        $upload_dir = 'uploads/';
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safe_filename = uniqid('file_', true) . '.' . $file_ext;
        $destination = $upload_dir . $safe_filename;
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $stmt = $pdo->prepare("UPDATE ticket_messages SET has_attachment = 1 WHERE id = ?");
            $stmt->execute([$message_id]);
            $stmt = $pdo->prepare("INSERT INTO ticket_attachments (message_id, file_path, original_name, file_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$message_id, $destination, basename($file['name']), $mime_type]);
            return true;
        }
    }
    return false;
}

function create_ticket($pdo, $user_id, $user_name, $data, $file) {
    $stmt = $pdo->prepare("SELECT id FROM tickets WHERE user_id = ? AND status IN ('open', 'answered')");
    $stmt->execute([$user_id]);
    if ($stmt->fetch()) { throw new Exception('شما یک تیکت فعال دارید.', 409); }
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO tickets (user_id, user_name, title, department) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $user_name, $data['title'], $data['department']]);
    $ticket_id = $pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, sender_type, message) VALUES (?, ?, 'user', ?)");
    $stmt->execute([$ticket_id, $user_id, $data['message']]);
    $message_id = $pdo->lastInsertId();
    if ($file) handle_attachment($pdo, $message_id, $file);
    $pdo->commit();
    notify_admins("تیکت جدید (#$ticket_id): {$data['title']}\nتوسط: $user_name");
    echo json_encode(['status' => 'success', 'message' => 'تیکت با موفقیت ایجاد شد.']);
}

function add_reply($pdo, $sender_id, $data, $file, $is_admin) {
    $ticket_id = $data['ticket_id'] ?? 0;
    $message = $data['message'] ?? '';
    $sender_type = $is_admin ? 'admin' : 'user';
    $admin_display_name = $is_admin ? ($data['admin_display_name'] ?? null) : null;
    $sql = "SELECT user_id, status FROM tickets WHERE id = ?";
    $params = [$ticket_id];
    if (!$is_admin) { $sql .= " AND user_id = ?"; $params[] = $sender_id; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) { throw new Exception('تیکت یافت نشد.', 404); }
    if ($ticket['status'] === 'closed') { throw new Exception('این تیکت بسته شده است.', 403); }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, sender_type, admin_display_name, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$ticket_id, $sender_id, $sender_type, $admin_display_name, $message]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, sender_type, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ticket_id, $sender_id, $sender_type, $message]);
    }
    $message_id = $pdo->lastInsertId();
    if ($file) handle_attachment($pdo, $message_id, $file);
    if ($is_admin) {
        $close_ticket = isset($data['close_ticket']) && $data['close_ticket'] === 'true';
        $new_status = $close_ticket ? 'closed' : 'answered';
        notify_user($ticket['user_id'], "پاسخ جدیدی برای تیکت شما (#$ticket_id) ثبت شد.");
    } else {
        $new_status = 'open';
        notify_admins("پاسخ جدید از کاربر در تیکت #$ticket_id");
    }
    $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$new_status, $ticket_id]);
    $pdo->commit();

    // --- Fetch and return the newly created message ---
    try {
        $stmt_new_msg = $pdo->prepare("SELECT tm.id, tm.sender_id, tm.sender_type, tm.admin_display_name, tm.message, tm.created_at, ta.file_path, ta.original_name, ta.file_type FROM ticket_messages tm LEFT JOIN ticket_attachments ta ON tm.id = ta.message_id WHERE tm.id = ?");
        $stmt_new_msg->execute([$message_id]);
        $new_message = $stmt_new_msg->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt_new_msg = $pdo->prepare("SELECT tm.id, tm.sender_id, tm.sender_type, tm.message, tm.created_at, ta.file_path, ta.original_name, ta.file_type FROM ticket_messages tm LEFT JOIN ticket_attachments ta ON tm.id = ta.message_id WHERE tm.id = ?");
        $stmt_new_msg->execute([$message_id]);
        $new_message = $stmt_new_msg->fetch(PDO::FETCH_ASSOC);
        if ($new_message) $new_message['admin_display_name'] = null;
    }

    echo json_encode(['status' => 'success', 'message' => 'پاسخ شما ثبت شد.', 'data' => ['new_message' => $new_message]]);
}

function toggle_ban_user($pdo, $is_admin, $user_id_to_toggle, $ban_status) {
    if (!$is_admin) { throw new Exception('Access Denied.', 403); }
    $stmt = $pdo->prepare("UPDATE users SET is_banned = ? WHERE user_id = ?");
    $stmt->execute([$ban_status ? 1 : 0, $user_id_to_toggle]);
    $action = $ban_status ? 'مسدود' : 'رفع مسدودیت';
    echo json_encode(['status' => 'success', 'message' => "کاربر با موفقیت $action شد."]);
}

function reopen_ticket($pdo, $user_id, $ticket_id, $is_admin) {
    $sql = "SELECT user_id, status FROM tickets WHERE id = ?";
    $params = [$ticket_id];
    if (!$is_admin) { $sql .= " AND user_id = ?"; $params[] = $user_id; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) { throw new Exception('تیکت یافت نشد.', 404); }
    if ($ticket['status'] !== 'closed') { throw new Exception('این تیکت از قبل باز است.', 400); }
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'open' WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $message = $is_admin ? "تیکت توسط مدیر بازگشایی شد." : "تیکت توسط کاربر بازگشایی شد.";
    $sender_type = $is_admin ? 'admin' : 'user';
    try {
        $admin_display = null;
        if ($is_admin) {
            $stmt_ad = $pdo->prepare("SELECT admin_display_name FROM users WHERE user_id = ?");
            $stmt_ad->execute([$user_id]);
            $admin_display = $stmt_ad->fetchColumn();
        }
        $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, sender_type, admin_display_name, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$ticket_id, $user_id, $sender_type, $admin_display, $message]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, sender_type, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ticket_id, $user_id, $sender_type, $message]);
    }
    echo json_encode(['status' => 'success', 'message' => 'تیکت با موفقیت باز شد.']);
}

function get_users($pdo, $is_super_admin, $data) {
    if (!$is_super_admin) { throw new Exception('Access Denied.', 403); }
    $page = isset($data['page']) ? (int)$data['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $sql = "SELECT SQL_CALC_FOUND_ROWS user_id, user_name, username, role, is_banned FROM users WHERE 1=1";
    $params = [];
    if (!empty($data['search'])) {
        $sql .= " AND (user_name LIKE ? OR username LIKE ? OR user_id = ?)";
        $params[] = '%' . $data['search'] . '%';
        $params[] = '%' . $data['search'] . '%';
        $params[] = $data['search'];
    }
    $sql .= " ORDER BY user_id DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_records = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    echo json_encode(['status' => 'success', 'data' => ['users' => $users, 'total_pages' => ceil($total_records / $limit), 'current_page' => $page]]);
}

function send_direct_message($pdo, $is_admin, $data) {
    if (!$is_admin) { throw new Exception('Access Denied.', 403); }
    $target_user_id = $data['user_id'] ?? 0;
    $message = $data['message'] ?? '';
    if (empty($target_user_id) || empty($message)) { throw new Exception('شناسه کاربر و پیام الزامی است.', 400); }
    notify_user($target_user_id, "پیام از طرف پشتیبانی:\n\n" . $message);
    echo json_encode(['status' => 'success', 'message' => 'پیام با موفقیت ارسال شد.']);
}

function set_user_role($pdo, $is_super_admin, $data) {
    if (!$is_super_admin) { throw new Exception('Access Denied.', 403); }
    $target_user_id = $data['user_id'] ?? 0;
    $role = $data['role'] ?? 'user';
    if (!in_array($role, ['user', 'admin'])) { throw new Exception('نقش نامعتبر است.', 400); }
    if (in_array($target_user_id, SUPER_ADMIN_IDS)) { throw new Exception('امکان تغییر نقش سوپر ادمین وجود ندارد.', 403); }
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
    $stmt->execute([$role, $target_user_id]);
    echo json_encode(['status' => 'success', 'message' => 'نقش کاربر با موفقیت تغییر کرد.']);
}

function set_admin_display_name($pdo, $is_admin, $user_id, $data) {
    if (!$is_admin) { throw new Exception('Access Denied.', 403); }
    $name = trim($data['admin_display_name'] ?? '');
    if (strlen($name) > 100) { throw new Exception('نام نمایشی نباید بیشتر از ۱۰۰ کاراکتر باشد.', 400); }
    try {
        $stmt = $pdo->prepare("UPDATE users SET admin_display_name = ? WHERE user_id = ? AND role IN ('admin', 'super_admin')");
        $stmt->execute([$name ?: null, $user_id]);
    } catch (PDOException $e) {
        throw new Exception('ستون admin_display_name وجود ندارد. لطفاً migrate_ticket_profile.php را اجرا کنید.', 500);
    }
    echo json_encode(['status' => 'success', 'message' => 'نام نمایشی ذخیره شد.', 'data' => ['admin_display_name' => $name ?: null]]);
}

function get_admin_display_name($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT admin_display_name FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $name = $stmt->fetchColumn();
    echo json_encode(['status' => 'success', 'data' => ['admin_display_name' => $name]]);
}

function notify_admins($message) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE role IN ('admin', 'super_admin')");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $admin_id) {
        notify_user($admin_id, $message);
    }
}

function notify_user($chat_id, $message) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage?chat_id=" . $chat_id . "&text=" . urlencode($message);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}

ob_end_flush();
?>