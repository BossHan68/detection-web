<?php
session_start();
require_once "../config/db.php";

$username = $_POST['username'] ?? $_POST['student_id']; // รองรับทั้ง 2 field
$password = $_POST['password'];

$stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['admin_id']   = $user['id'];
    $_SESSION['admin_name'] = $user['name'];
    header("Location: dashboard.php");
    exit;
} else {
    header("Location: login.php?error=1");
    exit;
}