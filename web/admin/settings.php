<?php
require_once "auth/check_login.php";
require_once "../config/db.php";

// ดึงข้อมูลผู้ใช้
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ตั้งค่า | Fall Detection System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --sidebar-width: 260px;
        --primary-color: #4f46e5;
        --danger-color: #ef4444;
        --success-color: #10b981;
        --warning-color: #f59e0b;
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
    
    .settings-card {
        background: white;
        border-radius: 15px;
        padding: 0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        margin-bottom: 25px;
        overflow: hidden;
    }
    
    .settings-header {
        background: linear-gradient(135deg, var(--dark-bg), var(--dark-bg-2));
        color: white;
        padding: 20px 25px;
        font-weight: 600;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .settings-body {
        padding: 30px;
    }
    
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        color: var(--dark-bg);
        font-weight: 600;
        font-size: 0.95rem;
    }
    
    .form-control {
        width: 100%;
        padding: 12px 18px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: #f8fafc;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        background: white;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }
    
    .form-control:disabled {
        background: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), #7c3aed);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
    }
    
    .btn-danger {
        background: linear-gradient(135deg, var(--danger-color), #dc2626);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
    }
    
    .alert {
        border-radius: 12px;
        border: none;
        padding: 15px 20px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.3s ease;
    }
    
    .alert i {
        font-size: 1.3rem;
    }
    
    .alert-success {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: var(--success-color);
    }
    
    .alert-danger {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: var(--danger-color);
    }
    
    .alert-warning {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #b45309;
    }
    
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 30px;
    }
    
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: 0.4s;
        border-radius: 30px;
    }
    
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 22px;
        width: 22px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: 0.4s;
        border-radius: 50%;
    }
    
    input:checked + .toggle-slider {
        background: linear-gradient(135deg, var(--success-color), #059669);
    }
    
    input:checked + .toggle-slider:before {
        transform: translateX(30px);
    }
    
    .setting-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background: #f8fafc;
        border-radius: 10px;
        margin-bottom: 15px;
    }
    
    .setting-info h6 {
        margin: 0 0 5px 0;
        color: var(--dark-bg);
        font-weight: 600;
    }
    
    .setting-info p {
        margin: 0;
        color: #64748b;
        font-size: 0.9rem;
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
    
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }
        .content {
            margin-left: 0;
        }
    }
</style>
</head>

<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h5><i class="fas fa-shield-alt"></i> Fall Detection</h5>
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
        <a href="profile.php">
            <i class="fas fa-user-circle"></i>
            <span>โปรไฟล์</span>
        </a>
        <a href="settings.php" class="active">
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
        <h4><i class="fas fa-cog"></i> ตั้งค่าระบบ</h4>
        <small class="text-muted">จัดการการตั้งค่าและความปลอดภัย</small>
    </div>

    <?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span>
            <?php
            if($_GET['success'] == 'profile') echo 'บันทึกข้อมูลโปรไฟล์สำเร็จ';
            elseif($_GET['success'] == 'password') echo 'เปลี่ยนรหัสผ่านสำเร็จ';
            else echo 'บันทึกการตั้งค่าสำเร็จ';
            ?>
        </span>
    </div>
    <?php endif; ?>

    <?php if(isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <span>
            <?php
            if($_GET['error'] == 'password_mismatch') echo 'รหัสผ่านไม่ตรงกัน';
            elseif($_GET['error'] == 'wrong_password') echo 'รหัสผ่านเดิมไม่ถูกต้อง';
            else echo 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
            ?>
        </span>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- แก้ไขโปรไฟล์ -->
        <div class="col-lg-6">
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fas fa-user-edit"></i>
                    แก้ไขข้อมูลส่วนตัว
                </div>
                <div class="settings-body">
                    <form method="POST" action="update_profile.php">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user"></i> ชื่อ-นามสกุล
                            </label>
                            <input 
                                type="text" 
                                name="name" 
                                class="form-control" 
                                value="<?= htmlspecialchars($user['name']) ?>" 
                                required
                            >
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-at"></i> Username
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                value="<?= htmlspecialchars($user['username']) ?>" 
                                disabled
                            >
                            <small class="text-muted" style="font-size: 0.85rem; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Username ไม่สามารถเปลี่ยนได้
                            </small>
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i>
                            <span>บันทึกการเปลี่ยนแปลง</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- เปลี่ยนรหัสผ่าน -->
        <div class="col-lg-6">
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fas fa-key"></i>
                    เปลี่ยนรหัสผ่าน
                </div>
                <div class="settings-body">
                    <form method="POST" action="change_password.php" id="passwordForm">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-lock"></i> รหัสผ่านเดิม
                            </label>
                            <input 
                                type="password" 
                                name="old_password" 
                                class="form-control" 
                                placeholder="กรอกรหัสผ่านเดิม"
                                required
                            >
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-key"></i> รหัสผ่านใหม่
                            </label>
                            <input 
                                type="password" 
                                name="new_password" 
                                class="form-control" 
                                placeholder="กรอกรหัสผ่านใหม่"
                                id="newPassword"
                                required
                            >
                            <small class="text-muted" style="font-size: 0.85rem; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> อย่างน้อย 6 ตัวอักษร
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-check-circle"></i> ยืนยันรหัสผ่านใหม่
                            </label>
                            <input 
                                type="password" 
                                name="confirm_password" 
                                class="form-control" 
                                placeholder="กรอกรหัสผ่านใหม่อีกครั้ง"
                                id="confirmPassword"
                                required
                            >
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-shield-alt"></i>
                            <span>เปลี่ยนรหัสผ่าน</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ตั้งค่าการแจ้งเตือน -->
        <div class="col-lg-12">
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fas fa-bell"></i>
                    ตั้งค่าการแจ้งเตือน
                </div>
                <div class="settings-body">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h6><i class="fas fa-exclamation-triangle"></i> แจ้งเตือนเมื่อพบการล้ม</h6>
                            <p>รับการแจ้งเตือนทันทีเมื่อระบบตรวจพบการล้ม</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h6><i class="fas fa-envelope"></i> แจ้งเตือนผ่าน LINN OA</h6>
                            <p>ส่งการแจ้งเตือนไปยังLINEของคุณ</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h6><i class="fas fa-volume-up"></i> เสียงแจ้งเตือน</h6>
                            <p>เปิดเสียงเตือนบนเว็บเบราว์เซอร์</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- โซนอันตราย -->
        <div class="col-lg-12">
            <div class="settings-card">
                <div class="settings-header" style="background: linear-gradient(135deg, var(--danger-color), #dc2626);">
                    <i class="fas fa-exclamation-triangle"></i>
                    โซนอันตราย
                </div>
                <div class="settings-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>การกระทำในส่วนนี้ไม่สามารถย้อนกลับได้ กรุณาดำเนินการด้วยความระมัดระวัง</span>
                    </div>
                    
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <button type="button" class="btn-danger" onclick="confirmClearEvents()">
                            <i class="fas fa-trash-alt"></i>
                            <span>ลบข้อมูลเหตุการณ์ทั้งหมด</span>
                        </button>
                        
                        <button type="button" class="btn-danger" onclick="confirmDeleteAccount()">
                            <i class="fas fa-user-times"></i>
                            <span>ลบบัญชีผู้ใช้</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ตรวจสอบรหัสผ่าน
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPass = document.getElementById('newPassword').value;
    const confirmPass = document.getElementById('confirmPassword').value;
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('รหัสผ่านไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง');
        return false;
    }
    
    if (newPass.length < 6) {
        e.preventDefault();
        alert('รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร');
        return false;
    }
});

// ยืนยันการลบข้อมูล
function confirmClearEvents() {
    if (confirm('คุณแน่ใจหรือไม่ที่จะลบข้อมูลเหตุการณ์ทั้งหมด?\n\nการกระทำนี้ไม่สามารถย้อนกลับได้!')) {
        if (confirm('กรุณายืนยันอีกครั้ง - ข้อมูลทั้งหมดจะถูกลบอย่างถาวร')) {
            window.location.href = 'clear_events.php';
        }
    }
}

function confirmDeleteAccount() {
    if (confirm('คุณแน่ใจหรือไม่ที่จะลบบัญชีผู้ใช้?\n\nบัญชีและข้อมูลทั้งหมดจะถูกลบอย่างถาวร!')) {
        const password = prompt('กรุณากรอกรหัสผ่านของคุณเพื่อยืนยัน:');
        if (password) {
            window.location.href = 'delete_account.php?confirm=' + encodeURIComponent(password);
        }
    }
}

// Toggle switches
document.querySelectorAll('.toggle-switch input').forEach(toggle => {
    toggle.addEventListener('change', function() {
        console.log('Toggle changed:', this.checked);
        // ที่นี่สามารถส่ง AJAX เพื่อบันทึกการตั้งค่าได้
    });
});
</script>

</body>
</html>