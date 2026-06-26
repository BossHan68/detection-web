<?php
require_once "auth/check_login.php";
require_once "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: settings.php");
    exit;
}

$name = trim($_POST['name']);
$admin_id = $_SESSION['admin_id'];

// Validate
if (empty($name)) {
    header("Location: settings.php?error=empty_name");
    exit;
}

try {
    // Update profile
    $stmt = $conn->prepare("UPDATE admins SET name = ? WHERE id = ?");
    
    if ($stmt->execute([$name, $admin_id])) {
        // Update session
        $_SESSION['admin_name'] = $name;
        
        header("Location: profile.php?success=1");
        exit;
    } else {
        header("Location: profile.php?error=1");
        exit;
    }
} catch (PDOException $e) {
    error_log("Update Profile Error: " . $e->getMessage());
    header("Location: profile.php?error=1");
    exit;
}
?>