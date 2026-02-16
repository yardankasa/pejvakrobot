<?php
/**
 * Migration: Add user profile and admin display name columns to ticket system
 * Run once: php migrate_ticket_profile.php
 *
 * Adds columns for:
 * - users: first_name, last_name, photo_url (from Telegram), admin_display_name (for admins)
 * - ticket_messages: admin_display_name (which admin answered)
 */
require_once __DIR__ . '/config_ticket.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$alterations = [
    "ALTER TABLE `users` ADD COLUMN `first_name` VARCHAR(255) DEFAULT NULL AFTER `username`",
    "ALTER TABLE `users` ADD COLUMN `last_name` VARCHAR(255) DEFAULT NULL AFTER `first_name`",
    "ALTER TABLE `users` ADD COLUMN `photo_url` VARCHAR(500) DEFAULT NULL AFTER `last_name`",
    "ALTER TABLE `users` ADD COLUMN `admin_display_name` VARCHAR(100) DEFAULT NULL COMMENT 'نام نمایشی ادمین در پاسخ‌ها' AFTER `photo_url`",
    "ALTER TABLE `ticket_messages` ADD COLUMN `admin_display_name` VARCHAR(100) DEFAULT NULL COMMENT 'نام ادمین هنگام ارسال پاسخ' AFTER `sender_type`",
];

foreach ($alterations as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: " . substr($sql, 0, 60) . "...\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "SKIP (exists): " . substr($sql, 0, 50) . "...\n";
        } else {
            throw $e;
        }
    }
}

echo "Migration completed.\n";
