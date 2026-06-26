<?php
require_once "../config/db.php";

$name     = trim($_POST['name']);
$username = trim($_POST['username']);
$password = $_POST['password'];

// Validate ข้อมูล
if (empty($name) || empty($username) || empty($password)) {
    header("Location: register.php?error=empty");
    exit;
}

// ตรวจสอบความแข็งแรงของรหัสผ่าน
if (strlen($password) < 6) {
    header("Location: register.php?error=weak_password");
    exit;
}

// Hash รหัสผ่าน
$hash = password_hash($password, PASSWORD_DEFAULT);

// เช็ค username ซ้ำ
$check = $conn->prepare("SELECT id FROM admins WHERE username = ?");
$check->execute([$username]);

if ($check->rowCount() > 0) {
    // Username ซ้ำ - redirect กลับพร้อมข้อความแจ้งเตือน
    header("Location: register.php?error=duplicate");
    exit;
}

// บันทึกข้อมูล
try {
    $stmt = $conn->prepare(
        "INSERT INTO admins (name, username, password, created_at)
         VALUES (?, ?, ?, NOW())"
    );
    
    if ($stmt->execute([$name, $username, $hash])) {
        // สมัครสำเร็จ
        header("Location: login.php?register=success");
        exit;
    } else {
        // บันทึกไม่สำเร็จ
        header("Location: register.php?error=save_failed");
        exit;
    }
} catch (PDOException $e) {
    // จัดการ error จาก database
    error_log("Register Error: " . $e->getMessage());
    
    // ตรวจสอบว่าเป็น duplicate key error หรือไม่
    if ($e->getCode() == 23000) {
        header("Location: register.php?error=duplicate");
    } else {
        header("Location: register.php?error=database");
    }
    exit;
}
?>