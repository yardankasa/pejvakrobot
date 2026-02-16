<?php
/**
 * Migration: Add time and time_vip columns to panel table
 * Run this once if you get "Unknown column 'time'" error
 */
require_once __DIR__ . '/config.php';

try {
    $pdo->exec("ALTER TABLE `panel` ADD COLUMN `time` INT(11) DEFAULT 0 COMMENT 'زمان ارسال سورس رایگان بعدی (timestamp)'");
    echo "Added column 'time' to panel table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column 'time' already exists.\n";
    } else {
        throw $e;
    }
}

try {
    $pdo->exec("ALTER TABLE `panel` ADD COLUMN `time_vip` INT(11) DEFAULT 0 COMMENT 'زمان ارسال سورس VIP بعدی (timestamp)'");
    echo "Added column 'time_vip' to panel table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column 'time_vip' already exists.\n";
    } else {
        throw $e;
    }
}

echo "Migration completed.\n";
