<?php
require_once "auth/check_login.php";
require_once "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: settings.php");
    exit;
}

$old_password = $_POST['old_password'];
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];
$admin_id = $_SESSION['admin_id'];

// Validate
if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
    header("Location: settings.php?error=empty_fields");
    exit;
}

// ตรวจสอบรหัสผ่านใหม่ตรงกันไหม
if ($new_password !== $confirm_password) {
    header("Location: settings.php?error=password_mismatch");
    exit;
}

// ตรวจสอบความยาวรหัสผ่าน
if (strlen($new_password) < 6) {
    header("Location: settings.php?error=password_too_short");
    exit;
}

try {
    // ดึงข้อมูลผู้ใช้
    $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: settings.php?error=user_not_found");
        exit;
    }
    
    // ตรวจสอบรหัสผ่านเดิม
    if (!password_verify($old_password, $user['password'])) {
        header("Location: settings.php?error=wrong_password");
        exit;
    }
    
    // Hash รหัสผ่านใหม่
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update รหัสผ่าน
    $update = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
    
    if ($update->execute([$new_hash, $admin_id])) {
        header("Location: settings.php?success=password");
        exit;
    } else {
        header("Location: settings.php?error=update_failed");
        exit;
    }
} catch (PDOException $e) {
    error_log("Change Password Error: " . $e->getMessage());
    header("Location: settings.php?error=database");
    exit;
}
?>