<?php
require_once "auth/check_login.php";
require_once "../config/db.php";

$admin_id = $_SESSION['admin_id'];
$message = '';
$message_type = '';

// ===== HANDLE PROFILE IMAGE UPLOAD =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (in_array($file_type, $allowed_types)) {
            if ($file['size'] <= 5 * 1024 * 1024) { // 5MB
                $upload_dir = "../uploads/profiles/";
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = "profile_{$admin_id}_" . time() . "." . $extension;
                $upload_path = $upload_dir . $new_filename;
                
                // ลบรูปเดิม
                $stmt = $conn->prepare("SELECT profile_image FROM admins WHERE id = ?");
                $stmt->execute([$admin_id]);
                $old_image = $stmt->fetchColumn();
                
                if ($old_image && file_exists($upload_dir . $old_image)) {
                    unlink($upload_dir . $old_image);
                }
                
                // อัปโหลดไฟล์ใหม่
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    // ปรับขนาดรูป
                    try {
                        $max_size = 500;
                        
                        switch($file_type) {
                            case 'image/jpeg':
                            case 'image/jpg':
                                $source = imagecreatefromjpeg($upload_path);
                                break;
                            case 'image/png':
                                $source = imagecreatefrompng($upload_path);
                                break;
                            case 'image/gif':
                                $source = imagecreatefromgif($upload_path);
                                break;
                            default:
                                $source = null;
                        }
                        
                        if ($source) {
                            $width = imagesx($source);
                            $height = imagesy($source);
                            
                            if ($width > $max_size || $height > $max_size) {
                                if ($width > $height) {
                                    $new_width = $max_size;
                                    $new_height = floor($height * ($max_size / $width));
                                } else {
                                    $new_height = $max_size;
                                    $new_width = floor($width * ($max_size / $height));
                                }
                                
                                $thumb = imagecreatetruecolor($new_width, $new_height);
                                
                                if ($file_type == 'image/png' || $file_type == 'image/gif') {
                                    imagealphablending($thumb, false);
                                    imagesavealpha($thumb, true);
                                }
                                
                                imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                                
                                switch($file_type) {
                                    case 'image/jpeg':
                                    case 'image/jpg':
                                        imagejpeg($thumb, $upload_path, 90);
                                        break;
                                    case 'image/png':
                                        imagepng($thumb, $upload_path, 9);
                                        break;
                                    case 'image/gif':
                                        imagegif($thumb, $upload_path);
                                        break;
                                }
                                
                                imagedestroy($thumb);
                            }
                            
                            imagedestroy($source);
                        }
                    } catch (Exception $e) {
                        // ถ้าปรับขนาดไม่ได้ ใช้ไฟล์เดิม
                    }
                    
                    // บันทึกลง database
                    $stmt = $conn->prepare("UPDATE admins SET profile_image = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$new_filename, $admin_id]);
                    
                    $message = 'อัปโหลดรูปโปรไฟล์สำเร็จ';
                    $message_type = 'success';
                } else {
                    $message = 'อัปโหลดรูปไม่สำเร็จ';
                    $message_type = 'danger';
                }
            } else {
                $message = 'ไฟล์ใหญ่เกินไป (สูงสุด 5MB)';
                $message_type = 'danger';
            }
        } else {
            $message = 'ไฟล์ไม่ถูกต้อง (รองรับเฉพาะ JPG, PNG, GIF)';
            $message_type = 'danger';
        }
    }
}

// ===== HANDLE PROFILE IMAGE REMOVAL =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_image'])) {
    $stmt = $conn->prepare("SELECT profile_image FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $old_image = $stmt->fetchColumn();
    
    if ($old_image) {
        $upload_dir = "../uploads/profiles/";
        $file_path = $upload_dir . $old_image;
        
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        $stmt = $conn->prepare("UPDATE admins SET profile_image = NULL, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$admin_id]);
        
        $message = 'ลบรูปโปรไฟล์สำเร็จ';
        $message_type = 'success';
    }
}

// ===== HANDLE PROFILE UPDATE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    
    if (!empty($name)) {
        $stmt = $conn->prepare("UPDATE admins SET name = ?, updated_at = NOW() WHERE id = ?");
        
        if ($stmt->execute([$name, $admin_id])) {
            $_SESSION['admin_name'] = $name;
            $message = 'บันทึกข้อมูลโปรไฟล์สำเร็จ';
            $message_type = 'success';
        } else {
            $message = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
            $message_type = 'danger';
        }
    } else {
        $message = 'กรุณากรอกชื่อ-นามสกุล';
        $message_type = 'danger';
    }
}

// ดึงข้อมูลผู้ใช้
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// นับเหตุการณ์
$total_events = $conn->query("SELECT COUNT(*) FROM events")->fetchColumn();
$fall_events = $conn->query("SELECT COUNT(*) FROM events WHERE status='fall'")->fetchColumn();

// กำหนด path รูปโปรไฟล์
$profile_image = $user['profile_image'] ?? null;
$default_avatar = substr($user['name'], 0, 1);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>โปรไฟล์ | Fall Detection System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --sidebar-width: 260px;
        --primary-color: #4f46e5;
        --danger-color: #ef4444;
        --success-color: #10b981;
        --dark-bg: #1e293b;
        --dark-bg-2: #0f172a;
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body { 
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        position: fixed;
        background: var(--dark-bg-2);
        color: #fff;
        box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        z-index: 1000;
        overflow-y: auto;
    }
    
    .sidebar-header {
        padding: 25px 20px;
        background: linear-gradient(135deg, var(--primary-color), #7c3aed);
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .sidebar-header h5 {
        font-size: 1.3rem;
        font-weight: 700;
        margin: 0;
        color: #fff;
    }
    
    .sidebar-header p {
        font-size: 0.85rem;
        opacity: 0.8;
        margin: 5px 0 0 0;
    }
    
    .sidebar-menu {
        padding: 15px 0;
    }
    
    .sidebar a {
        color: #cbd5e1;
        text-decoration: none;
        display: flex;
        align-items: center;
        padding: 14px 25px;
        margin: 4px 12px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }
    
    .sidebar a i {
        width: 24px;
        margin-right: 12px;
        font-size: 1.1rem;
    }
    
    .sidebar a:hover {
        background: rgba(255,255,255,0.1);
        color: #fff;
        transform: translateX(5px);
    }
    
    .sidebar a.active {
        background: linear-gradient(135deg, var(--primary-color), #7c3aed);
        color: #fff;
        box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4);
    }
    
    .sidebar a.logout {
        color: #fca5a5;
        margin-top: 20px;
    }
    
    .sidebar a.logout:hover {
        background: rgba(239, 68, 68, 0.15);
        color: var(--danger-color);
    }
    
    .content {
        margin-left: var(--sidebar-width);
        padding: 30px;
        min-height: 100vh;
    }
    
    .top-bar {
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(10px);
        padding: 20px 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .top-bar h4 {
        margin: 0 0 5px 0;
        color: var(--dark-bg);
        font-weight: 700;
    }
    
    /* ===== Profile Avatar with Upload ===== */
    .profile-header {
        background: white;
        border-radius: 15px;
        padding: 40px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        margin-bottom: 25px;
        text-align: center;
    }
    
    .profile-avatar-container {
        position: relative;
        width: 150px;
        height: 150px;
        margin: 0 auto 20px;
    }
    
    .profile-avatar {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        box-shadow: 0 8px 25px rgba(79, 70, 229, 0.3);
        border: 4px solid white;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .profile-avatar:hover {
        transform: scale(1.05);
        box-shadow: 0 12px 35px rgba(79, 70, 229, 0.5);
    }
    
    .profile-avatar.placeholder {
        background: linear-gradient(135deg, var(--primary-color), #7c3aed);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3.5rem;
        font-weight: 700;
    }
    
    /* ===== Image Modal for Full View ===== */
    .image-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.95);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        animation: fadeIn 0.3s ease;
    }
    
    .image-modal.show {
        display: flex;
    }
    
    .image-modal-content {
        max-width: 90%;
        max-height: 90%;
        border-radius: 15px;
        box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
        animation: zoomIn 0.3s ease;
    }
    
    .image-modal-close {
        position: absolute;
        top: 30px;
        right: 30px;
        font-size: 3rem;
        color: white;
        cursor: pointer;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        transition: all 0.3s ease;
    }
    
    .image-modal-close:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: rotate(90deg);
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    
    @keyframes zoomIn {
        from {
            transform: scale(0.5);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
    
    .upload-overlay {
        position: absolute;
        bottom: 5px;
        right: 5px;
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, var(--primary-color), #7c3aed);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4);
        transition: all 0.3s ease;
        border: 3px solid white;
    }
    
    .upload-overlay:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(79, 70, 229, 0.6);
    }
    
    .upload-overlay i {
        color: white;
        font-size: 1.2rem;
    }
    
    .profile-name {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--dark-bg);
        margin-bottom: 8px;
    }
    
    .profile-username {
        color: #64748b;
        font-size: 1.1rem;
        margin-bottom: 20px;
    }
    
    .profile-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 20px;
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: var(--success-color);
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .info-card {
        background: white;
        border-radius: 15px;
        padding: 0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        margin-bottom: 25px;
        overflow: hidden;
    }
    
    .info-card-header {
        background: linear-gradient(135deg, var(--dark-bg), var(--dark-bg-2));
        color: white;
        padding: 20px 25px;
        font-weight: 600;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .info-card-body {
        padding: 25px;
    }
    
    .info-row {
        display: flex;
        padding: 15px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        flex: 0 0 150px;
        color: #64748b;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .info-value {
        flex: 1;
        color: var(--dark-bg);
        font-weight: 500;
    }
    
    .btn-edit {
        background: linear-gradient(135deg, var(--primary-color), #7c3aed);
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
    }
    
    .btn-edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
        color: white;
    }
    
    .btn-remove {
        background: linear-gradient(135deg, var(--danger-color), #dc2626);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
        margin-top: 10px;
    }
    
    .btn-remove:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
    }
    
    .stat-box {
        text-align: center;
        padding: 20px;
        background: #f8fafc;
        border-radius: 12px;
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin: 0 auto 15px;
    }
    
    .stat-icon.primary {
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: var(--primary-color);
    }
    
    .stat-icon.danger {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: var(--danger-color);
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        color: var(--dark-bg);
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: #64748b;
        font-size: 0.9rem;
    }
    
    .activity-item {
        display: flex;
        gap: 15px;
        padding: 15px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
    .activity-icon {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    
    .activity-icon.login {
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: var(--primary-color);
    }
    
    .activity-icon.event {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: var(--success-color);
    }
    
    .alert {
        border-radius: 12px;
        border: none;
        padding: 15px 20px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.3s ease;
    }
    
    .alert-success {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: var(--success-color);
    }
    
    .alert-danger {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: var(--danger-color);
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
        .sidebar {
            width: 0;
            transform: translateX(-100%);
        }
        
        .content {
            margin-left: 0;
            padding: 15px;
        }
        
        .profile-header {
            padding: 25px 20px;
        }
        
        .info-row {
            flex-direction: column;
            gap: 8px;
        }
        
        .info-label {
            flex: none;
        }
    }
</style>
</head>

<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-shield-alt" style="font-size: 2rem; margin-bottom: 10px;"></i>
        <h5>Fall Detection</h5>
        <p>ระบบตรวจจับการล้ม</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="dashboard.php">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="events.php">
            <i class="fas fa-list-alt"></i>
            <span>เหตุการณ์ล้ม</span>
        </a>
        <a href="profile.php" class="active">
            <i class="fas fa-user-circle"></i>
            <span>โปรไฟล์</span>
        </a>
        <a href="settings.php">
            <i class="fas fa-cog"></i>
            <span>ตั้งค่า</span>
        </a>
        <a href="logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>ออกจากระบบ</span>
        </a>
    </div>
</div>

<!-- Content -->
<div class="content">
    <!-- Top Bar -->
    <div class="top-bar">
        <h4><i class="fas fa-user-circle"></i> โปรไฟล์ของฉัน</h4>
        <small class="text-muted">จัดการข้อมูลส่วนตัวของคุณ</small>
    </div>

    <!-- Alert Messages -->
    <?php if($message): ?>
    <div class="alert alert-<?= $message_type ?>">
        <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <span><?= $message ?></span>
    </div>
    <?php endif; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="profile-avatar-container">
                <?php if($profile_image && file_exists("../uploads/profiles/" . $profile_image)): ?>
                    <img src="../uploads/profiles/<?= htmlspecialchars($profile_image) ?>?t=<?= time() ?>" 
                         alt="Profile" 
                         class="profile-avatar" 
                         id="profileAvatarImg"
                         onclick="openImageModal(this.src)"
                         title="คลิกเพื่อดูขนาดเต็ม">
                <?php else: ?>
                    <div class="profile-avatar placeholder" id="profileAvatarPlaceholder">
                        <?= $default_avatar ?>
                    </div>
                <?php endif; ?>
                
                <label for="profileImageInput" class="upload-overlay" title="เปลี่ยนรูปโปรไฟล์">
                    <i class="fas fa-camera" id="uploadIcon"></i>
                </label>
                <input type="file" 
                       name="profile_image"
                       id="profileImageInput" 
                       accept="image/jpeg,image/jpg,image/png,image/gif"
                       onchange="document.getElementById('uploadForm').submit()">
            </div>
        </form>
        
        <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="profile-username">@<?= htmlspecialchars($user['username']) ?></div>
        <div class="profile-badge">
            <i class="fas fa-shield-alt"></i>
            <span>ผู้ดูแลระบบ</span>
        </div>
        
        <?php if($profile_image): ?>
        <div>
            <form method="POST" style="display: inline;" onsubmit="return confirm('คุณต้องการลบรูปโปรไฟล์หรือไม่?')">
                <input type="hidden" name="remove_image" value="1">
                <button type="submit" class="btn-remove">
                    <i class="fas fa-trash"></i> ลบรูปโปรไฟล์
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 25px;">
            <a href="settings.php" class="btn-edit">
                <i class="fas fa-key"></i>
                <span>เปลี่ยนรหัสผ่าน</span>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- ข้อมูลส่วนตัว -->
        <div class="col-md-7">
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-id-card"></i>
                    ข้อมูลส่วนตัว
                </div>
                <div class="info-card-body">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-user"></i> ชื่อ-นามสกุล
                            </div>
                            <div class="info-value">
                                <input 
                                    type="text" 
                                    name="name" 
                                    class="form-control" 
                                    value="<?= htmlspecialchars($user['name']) ?>" 
                                    required
                                    style="max-width: 400px;"
                                >
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-at"></i> Username
                            </div>
                            <div class="info-value">
                                <?= htmlspecialchars($user['username']) ?>
                                <small class="text-muted" style="display: block; font-size: 0.8rem; margin-top: 3px;">
                                    <i class="fas fa-info-circle"></i> ไม่สามารถเปลี่ยนได้
                                </small>
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-shield-alt"></i> สิทธิ์
                            </div>
                            <div class="info-value">
                                <span class="badge bg-success">ผู้ดูแลระบบ</span>
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-calendar-plus"></i> วันที่สมัคร
                            </div>
                            <div class="info-value">
                                <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?> น.
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-clock"></i> อัพเดทล่าสุด
                            </div>
                            <div class="info-value">
                                <?= $user['updated_at'] ? date('d/m/Y H:i', strtotime($user['updated_at'])) : '-' ?> น.
                            </div>
                        </div>

                        <div style="padding-top: 20px; border-top: 1px solid #f1f5f9; margin-top: 10px;">
                            <button type="submit" class="btn-edit">
                                <i class="fas fa-save"></i>
                                <span>บันทึกการเปลี่ยนแปลง</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- กิจกรรมล่าสุด -->
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-history"></i>
                    กิจกรรมล่าสุด
                </div>
                <div class="info-card-body">
                    <div class="activity-item">
                        <div class="activity-icon login">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: var(--dark-bg);">
                                เข้าสู่ระบบ
                            </div>
                            <div style="font-size: 0.85rem; color: #64748b;">
                                วันนี้ เวลา <?= date('H:i') ?> น.
                            </div>
                        </div>
                    </div>
                    
                    <div class="activity-item">
                        <div class="activity-icon event">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: var(--dark-bg);">
                                ดูข้อมูลเหตุการณ์
                            </div>
                            <div style="font-size: 0.85rem; color: #64748b;">
                                วันนี้ เวลา <?= date('H:i', strtotime('-30 minutes')) ?> น.
                            </div>
                        </div>
                    </div>
                    
                    <div class="activity-item">
                        <div class="activity-icon login">
                            <i class="fas fa-desktop"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: var(--dark-bg);">
                                เข้าชม Dashboard
                            </div>
                            <div style="font-size: 0.85rem; color: #64748b;">
                                วันนี้ เวลา <?= date('H:i', strtotime('-1 hour')) ?> น.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- สถิติ -->
        <div class="col-md-5">
            <div class="info-card" style="margin-bottom: 20px;">
                <div class="info-card-header">
                    <i class="fas fa-chart-bar"></i>
                    สถิติการใช้งาน
                </div>
                <div class="info-card-body">
                    <div class="stat-box" style="margin-bottom: 20px;">
                        <div class="stat-icon primary">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="stat-number"><?= number_format($total_events) ?></div>
                        <div class="stat-label">เหตุการณ์ทั้งหมด</div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-icon danger">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-number"><?= number_format($fall_events) ?></div>
                        <div class="stat-label">พบการล้ม</div>
                    </div>
                </div>
            </div>

            <!-- ข้อมูลระบบ -->
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-info-circle"></i>
                    ข้อมูลระบบ
                </div>
                <div class="info-card-body">
                    <div style="padding: 15px; background: #f8fafc; border-radius: 10px; margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #64748b;">
                                <i class="fas fa-database"></i> Database
                            </span>
                            <span style="font-weight: 600; color: var(--success-color);">
                                <i class="fas fa-check-circle"></i> Online
                            </span>
                        </div>
                    </div>
                    
                    <div style="padding: 15px; background: #f8fafc; border-radius: 10px; margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #64748b;">
                                <i class="fas fa-server"></i> API Status
                            </span>
                            <span style="font-weight: 600; color: var(--success-color);">
                                <i class="fas fa-check-circle"></i> Active
                            </span>
                        </div>
                    </div>
                    
                    <div style="padding: 15px; background: #f8fafc; border-radius: 10px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #64748b;">
                                <i class="fas fa-code-branch"></i> Version
                            </span>
                            <span style="font-weight: 600; color: var(--dark-bg);">
                                v3.0.0
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal for Full View -->
<div id="imageModal" class="image-modal" onclick="closeImageModal()">
    <span class="image-modal-close" title="ปิด">&times;</span>
    <img id="modalImage" class="image-modal-content" onclick="event.stopPropagation()">
</div>

<script>
// แสดง loading เมื่ออัปโหลด
document.getElementById('profileImageInput').addEventListener('change', function() {
    if (this.files && this.files[0]) {
        document.getElementById('uploadIcon').className = 'fas fa-spinner fa-spin';
    }
});

// ฟังก์ชันเปิด Modal แสดงรูปเต็มจอ
function openImageModal(imageSrc) {
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    
    modal.classList.add('show');
    modalImage.src = imageSrc;
    
    // ป้องกันการ scroll
    document.body.style.overflow = 'hidden';
}

// ฟังก์ชันปิด Modal
function closeImageModal() {
    const modal = document.getElementById('imageModal');
    modal.classList.remove('show');
    
    // เปิดการ scroll กลับ
    document.body.style.overflow = 'auto';
}

// กด ESC เพื่อปิด Modal
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeImageModal();
    }
});

console.log('✅ Profile page loaded with image viewer');
</script>

</body>
</html>