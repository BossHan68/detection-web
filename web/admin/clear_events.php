<?php
require_once "auth/check_login.php";
require_once "../config/db.php";

try {
    // ลบข้อมูลเหตุการณ์ทั้งหมด
    $conn->exec("TRUNCATE TABLE events");
    
    // ลบรูปภาพทั้งหมด (ถ้ามี)
    $image_dir = "../uploads/images/";
    if (is_dir($image_dir)) {
        $files = glob($image_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    header("Location: dashboard.php?success=clear_events");
    exit;
} catch (PDOException $e) {
    error_log("Clear Events Error: " . $e->getMessage());
    header("Location: settings.php?error=clear_failed");
    exit;
}
?>