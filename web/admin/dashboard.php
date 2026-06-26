<?php
require_once "auth/check_login.php";
require_once "../config/db.php";

// ดึงข้อมูลผู้ใช้ พร้อมรูปโปรไฟล์
$admin_id = $_SESSION['admin_id'];
$admin_query = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$admin_query->execute([$admin_id]);
$admin_data = $admin_query->fetch(PDO::FETCH_ASSOC);

// เช็ครูปโปรไฟล์
$profile_image = '';
if (!empty($admin_data['profile_image']) && file_exists("../uploads/profiles/" . $admin_data['profile_image'])) {
    $profile_image = "../uploads/profiles/" . $admin_data['profile_image'];
}

// ดึงข้อมูล
$total = $conn->query("SELECT COUNT(*) FROM events")->fetchColumn();
$fall  = $conn->query("SELECT COUNT(*) FROM events WHERE status='fall'")->fetchColumn();
$normal = $total - $fall;
$last  = $conn->query("SELECT * FROM events ORDER BY created_at DESC LIMIT 1")
              ->fetch(PDO::FETCH_ASSOC);

// ดึงข้อมูลสำหรับกราฟ (7 วันล่าสุด)
$chart_data = $conn->query("
    SELECT DATE(created_at) as date, 
           SUM(CASE WHEN status='fall' THEN 1 ELSE 0 END) as fall_count,
           COUNT(*) as total_count
    FROM events 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลสำหรับกราฟเปรียบเทียบรายชั่วโมง (วันนี้)
$hourly_data = $conn->query("
    SELECT HOUR(created_at) as hour,
           SUM(CASE WHEN status='fall' THEN 1 ELSE 0 END) as fall_count,
           COUNT(*) as total_count
    FROM events 
    WHERE DATE(created_at) = CURDATE()
    GROUP BY HOUR(created_at)
    ORDER BY hour ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | Fall Detection System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

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
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .top-bar h4 {
        margin: 0;
        color: var(--dark-bg);
        font-weight: 700;
    }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .user-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), #7c3aed);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 1.2rem;
        overflow: hidden;
        border: 3px solid white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
    }
    
    .user-avatar:hover {
        transform: scale(1.12);
        box-shadow: 0 6px 24px rgba(79, 70, 229, 0.5);
        border-color: #c7d2fe;
    }

    .user-avatar-wrap {
        position: relative;
        display: inline-block;
    }

    .user-avatar-wrap::after {
        content: '✏️ แก้ไขโปรไฟล์';
        position: absolute;
        bottom: -34px;
        right: 0;
        background: #1e293b;
        color: #e2e8f0;
        font-size: 11px;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 8px;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease, transform 0.2s ease;
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 100;
    }

    .user-avatar-wrap:hover::after {
        opacity: 1;
        transform: translateY(0);
    }
    
    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border: 1px solid rgba(255,255,255,0.3);
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), #7c3aed);
    }
    
    .stat-card.danger::before {
        background: linear-gradient(90deg, var(--danger-color), #dc2626);
    }
    
    .stat-card.success::before {
        background: linear-gradient(90deg, var(--success-color), #059669);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin-bottom: 15px;
    }
    
    .stat-icon.primary {
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: var(--primary-color);
    }
    
    .stat-icon.danger {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: var(--danger-color);
    }
    
    .stat-icon.success {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: var(--success-color);
    }
    
    .stat-card h6 {
        color: #64748b;
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-card h2 {
        color: var(--dark-bg);
        font-weight: 800;
        font-size: 2.5rem;
        margin: 0;
    }
    
    .stat-card .badge {
        font-size: 1rem;
        padding: 8px 16px;
    }
    
    .info-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    
    .info-card-header {
        padding: 20px 25px;
        background: linear-gradient(135deg, var(--primary-color), #7c3aed);
        color: white;
        font-weight: 700;
        font-size: 1.1rem;
    }
    
    .info-card-body {
        padding: 25px;
    }
    
    .event-item {
        padding: 20px;
        border-radius: 12px;
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .event-item.fall {
        border-color: rgba(239, 68, 68, 0.3);
        background: linear-gradient(135deg, #fff5f5, #fff);
    }
    
    .event-item:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    .pulse {
        animation: pulse 2s ease-in-out infinite;
    }
    
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.7;
        }
    }
    
    /* ===== STOCK-STYLE CHART ===== */
    .chart-container {
        background: #0f172a;
        padding: 30px;
        border-radius: 20px;
        box-shadow: 0 8px 40px rgba(0,0,0,0.25);
        margin-top: 30px;
        border: 1px solid rgba(255,255,255,0.07);
        position: relative;
        overflow: hidden;
    }

    .chart-container::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, #4f46e5, #ef4444, #10b981);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
        padding-bottom: 18px;
        border-bottom: 1px solid rgba(255,255,255,0.08);
    }

    .chart-title h5 {
        margin: 0 0 4px 0;
        color: #f1f5f9;
        font-weight: 700;
        font-size: 1.2rem;
    }

    .chart-title small {
        color: #64748b;
        font-size: 0.82rem;
    }

    .chart-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .chart-title-icon {
        width: 42px; height: 42px;
        background: linear-gradient(135deg, rgba(79,70,229,0.3), rgba(124,58,237,0.3));
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        color: #818cf8;
        font-size: 1.1rem;
    }

    .chart-stats {
        display: flex;
        gap: 24px;
    }

    .chart-stat-item {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.07);
        border-radius: 12px;
        padding: 12px 18px;
    }

    .chart-stat-label {
        font-size: 0.72rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 4px;
    }

    .chart-stat-value {
        font-size: 1.6rem;
        font-weight: 800;
        line-height: 1;
    }

    .chart-stat-value.danger { color: #f87171; }
    .chart-stat-value.success { color: #34d399; }

    .chart-stat-change {
        font-size: 0.78rem;
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 5px;
    }

    .chart-stat-change.up { color: #34d399; }
    .chart-stat-change.down { color: #f87171; }
    .chart-stat-change.neutral { color: #94a3b8; }

    .chart-controls {
        display: flex;
        gap: 8px;
        margin-bottom: 18px;
    }

    .chart-btn {
        padding: 7px 16px;
        border: 1px solid rgba(255,255,255,0.1);
        background: rgba(255,255,255,0.05);
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 600;
        color: #94a3b8;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .chart-btn:hover {
        background: rgba(255,255,255,0.1);
        color: #e2e8f0;
    }

    .chart-btn.active {
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(79,70,229,0.4);
    }

    .chart-legend {
        display: flex;
        gap: 20px;
        margin-top: 16px;
        padding-top: 14px;
        border-top: 1px solid rgba(255,255,255,0.07);
        flex-wrap: wrap;
    }

    .chart-legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
        color: #94a3b8;
    }

    .chart-legend-dot {
        width: 10px; height: 10px;
        border-radius: 50%;
        box-shadow: 0 0 8px currentColor;
    }

    .chart-summary-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .chart-summary-pill {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 100px;
        font-size: 0.83rem;
        font-weight: 600;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.08);
        color: #cbd5e1;
    }

    .chart-summary-pill .pill-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
    }
    
    .btn {
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    
    /* Camera Control Section */
    .camera-control {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }
    
    .camera-control h5 {
        color: var(--dark-bg);
        font-weight: 700;
        margin-bottom: 20px;
    }
    
    .camera-status {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: #f8fafc;
        border-radius: 10px;
        margin-bottom: 15px;
    }
    
    .status-indicator {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #94a3b8;
        animation: none;
    }
    
    .status-indicator.active {
        background: var(--success-color);
        animation: pulse 2s ease-in-out infinite;
    }
    
    .status-indicator.danger {
        background: var(--danger-color);
        animation: pulse 1s ease-in-out infinite;
    }
    
    .btn-camera {
        background: linear-gradient(135deg, var(--success-color), #059669);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    
    .btn-camera:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    }
    
    .btn-camera.stop {
        background: linear-gradient(135deg, var(--danger-color), #dc2626);
    }
    
    .btn-camera.stop:hover {
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
    }
    
    /* Alert Banner */
    .alert-banner {
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, var(--danger-color), #dc2626);
        color: white;
        padding: 20px 30px;
        border-radius: 15px;
        box-shadow: 0 8px 30px rgba(239, 68, 68, 0.4);
        z-index: 9999;
        display: none;
        animation: slideInRight 0.5s ease;
    }
    
    .alert-banner.show {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .alert-banner i {
        font-size: 2rem;
        animation: shake 0.5s ease infinite;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    
    .alert-banner .alert-text h4 {
        margin: 0 0 5px 0;
        font-weight: 700;
    }
    
    .alert-banner .alert-text p {
        margin: 0;
        font-size: 0.9rem;
        opacity: 0.9;
    }
</style>
</head>

<body>

<!-- Alert Banner -->
<div id="alertBanner" class="alert-banner">
    <i class="fas fa-exclamation-triangle"></i>
    <div class="alert-text">
        <h4>⚠️ ตรวจพบการล้ม!</h4>
        <p>กรุณาตรวจสอบทันที</p>
    </div>
</div>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-shield-alt" style="font-size: 2rem; margin-bottom: 10px;"></i>
        <h5>Fall Detection</h5>
        <p>ระบบตรวจจับการล้ม</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="dashboard.php" class="active">
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
        <div>
            <h4><i class="fas fa-chart-line"></i> Dashboard Overview</h4>
            <small class="text-muted">ภาพรวมระบบตรวจจับการล้ม</small>
        </div>
        <div class="user-info">
            <div style="text-align: right;">
                <div style="font-weight: 600; color: var(--dark-bg);">
                    <?= htmlspecialchars($admin_data['name']) ?>
                </div>
                <small class="text-muted">Administrator</small>
            </div>
            <a href="profile.php" style="text-decoration: none;">
                <div class="user-avatar-wrap">
                    <div class="user-avatar" title="คลิกเพื่อแก้ไขโปรไฟล์">
                        <?php if ($profile_image): ?>
                            <img src="<?= htmlspecialchars($profile_image) ?>" alt="Profile">
                        <?php else: ?>
                            <?= strtoupper(substr($admin_data['name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Camera Control -->
    <div class="camera-control">
        <h5><i class="fas fa-video"></i> ควบคุมระบบตรวจจับ</h5>
        
        <div class="camera-status">
            <div class="status-indicator" id="statusIndicator"></div>
            <div style="flex: 1;">
                <div style="font-weight: 600; color: var(--dark-bg);" id="statusText">
                    ระบบปิดอยู่
                </div>
                <div style="color: #64748b; font-size: 0.85rem;">
                    คลิกปุ่มด้านล่างเพื่อเปิดระบบ
                </div>
            </div>
        </div>
        
        <button id="toggleCameraBtn" class="btn-camera" onclick="toggleCamera()">
            <i class="fas fa-play"></i>
            <span id="btnText">เปิดระบบตรวจจับ</span>
        </button>
        
        <div style="margin-top: 15px; padding: 12px; background: #fef3c7; border-radius: 8px; font-size: 0.9rem;">
            <i class="fas fa-info-circle" style="color: #f59e0b;"></i>
            <strong style="color: #92400e;">หมายเหตุ:</strong> 
            <span style="color: #78350f;">กรุณาเปิดสคริปต์ Python บนเครื่องที่ติดตั้งกล้องก่อนใช้งาน</span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h6>เหตุการณ์ทั้งหมด</h6>
                <h2 id="totalEvents"><?= $total ?></h2>
                <small class="text-muted">
                    <i class="fas fa-clock"></i> อัพเดทล่าสุด: <span id="lastUpdate">เมื่อสักครู่</span>
                </small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card danger">
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h6>พบการล้ม</h6>
                <h2 id="fallEvents"><?= $fall ?></h2>
                <small class="text-muted">
                    <i class="fas fa-percentage"></i> 
                    <span id="fallPercent"><?= $total > 0 ? round(($fall/$total)*100, 1) : 0 ?>%</span> ของทั้งหมด
                </small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card success">
                <div class="stat-icon success">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h6>สถานะระบบ</h6>
                <div style="padding: 10px 0;">
                    <span class="badge bg-success pulse" id="systemStatus">
                        <i class="fas fa-circle"></i> Online
                    </span>
                </div>
                <small class="text-muted">
                    <i class="fas fa-check-circle"></i> ทำงานปกติ
                </small>
            </div>
        </div>
    </div>

    <!-- Charts and Recent Events -->
    <div class="row g-4">
        <!-- Recent Event -->
        <div class="col-md-6">
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-bell"></i>
                    เหตุการณ์ล่าสุด
                </div>
                <div class="info-card-body" id="recentEventContainer">
                    <?php if ($last): ?>
                        <div class="event-item <?= $last['status']=='fall' ? 'fall' : 'normal' ?>">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <?php if($last['status']=='fall'): ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-exclamation-circle"></i> พบการล้ม
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle"></i> สถานะปกติ
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="color: #64748b; font-size: 0.9rem;">
                                    <i class="fas fa-clock"></i>
                                    <?= date('d/m/Y H:i:s', strtotime($last['created_at'])) ?>
                                </div>
                                <?php if($last['device_id']): ?>
                                    <div style="color: #64748b; font-size: 0.85rem; margin-top: 4px;">
                                        <i class="fas fa-video"></i> อุปกรณ์: <?= $last['device_id'] ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="events.php" class="btn btn-primary w-100 mt-3">
                            <i class="fas fa-list"></i> ดูเหตุการณ์ทั้งหมด
                        </a>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="mt-3">ยังไม่มีข้อมูลเหตุการณ์</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="col-md-6">
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-chart-pie"></i>
                    สรุปข้อมูล
                </div>
                <div class="info-card-body">
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #64748b;">
                                <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                                สถานะปกติ
                            </span>
                            <strong style="color: var(--success-color);" id="normalEvents"><?= $normal ?></strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" id="normalProgress" style="width: <?= $total > 0 ? ($normal/$total)*100 : 0 ?>%"></div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #64748b;">
                                <i class="fas fa-exclamation-triangle" style="color: var(--danger-color);"></i>
                                การล้ม
                            </span>
                            <strong style="color: var(--danger-color);" id="fallEventsQuick"><?= $fall ?></strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-danger" id="fallProgress" style="width: <?= $total > 0 ? ($fall/$total)*100 : 0 ?>%"></div>
                        </div>
                    </div>

                    <div class="alert alert-info mb-0" style="border-radius: 10px;">
                        <i class="fas fa-info-circle"></i>
                        <strong>อัตราการตรวจพบ:</strong> 
                        <span id="detectionRate"><?= $total > 0 ? round(($fall/$total)*100, 1) : 0 ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock-Style Chart -->
    <?php if(count($chart_data) > 0): ?>
    <div class="chart-container">
        <!-- Chart Header -->
        <div class="chart-header">
            <div class="chart-title">
                <div class="chart-title-icon">
                    <i class="fas fa-chart-area"></i>
                </div>
                <div>
                    <h5>📊 สถิติการตรวจจับแบบ Real-Time</h5>
                    <small>แนวโน้มเหตุการณ์ · อัปเดตทุก 3 วินาที</small>
                </div>
            </div>
            <div class="chart-stats">
                <div class="chart-stat-item">
                    <div class="chart-stat-label">🔴 ล้มสูงสุด/วัน</div>
                    <div class="chart-stat-value danger">
                        <?= !empty($chart_data) ? max(array_column($chart_data, 'fall_count')) : 0 ?>
                    </div>
                    <div class="chart-stat-change down">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>ครั้ง (สูงสุด)</span>
                    </div>
                </div>
                <div class="chart-stat-item">
                    <div class="chart-stat-label">🟢 รวม 7 วัน</div>
                    <div class="chart-stat-value success">
                        <?= !empty($chart_data) ? array_sum(array_column($chart_data, 'total_count')) : 0 ?>
                    </div>
                    <div class="chart-stat-change neutral">
                        <i class="fas fa-calendar-week"></i>
                        <span>เหตุการณ์</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Pills -->
        <div class="chart-summary-bar">
            <div class="chart-summary-pill">
                <div class="pill-dot" style="background:#f87171; box-shadow:0 0 6px #f87171;"></div>
                การล้ม (Fall) — แดง
            </div>
            <div class="chart-summary-pill">
                <div class="pill-dot" style="background:#818cf8; box-shadow:0 0 6px #818cf8;"></div>
                รวมทั้งหมด (Total) — ม่วง
            </div>
            <div class="chart-summary-pill">
                <div class="pill-dot" style="background:#34d399; box-shadow:0 0 6px #34d399;"></div>
                ปกติ (Normal) — เขียว
            </div>
            <div class="chart-summary-pill" style="margin-left:auto; background:rgba(79,70,229,0.12); border-color:rgba(79,70,229,0.3); color:#818cf8;">
                <i class="fas fa-info-circle"></i>
                วางเมาส์บนกราฟเพื่อดูรายละเอียด
            </div>
        </div>

        <!-- Chart Controls -->
        <div class="chart-controls">
            <button class="chart-btn active" onclick="switchChartPeriod('7d', this)">
                <i class="fas fa-calendar-week"></i> 7 วันล่าสุด
            </button>
            <button class="chart-btn" onclick="switchChartPeriod('today', this)">
                <i class="fas fa-calendar-day"></i> วันนี้ (รายชั่วโมง)
            </button>
        </div>

        <!-- ApexChart -->
        <div id="fallChart" style="min-height: 420px;"></div>

        <!-- Chart Legend -->
        <div class="chart-legend">
            <div class="chart-legend-item">
                <div class="chart-legend-dot" style="background:#f87171; color:#f87171;"></div>
                <span>การล้ม (Fall Incidents)</span>
            </div>
            <div class="chart-legend-item">
                <div class="chart-legend-dot" style="background:#818cf8; color:#818cf8;"></div>
                <span>เหตุการณ์ทั้งหมด (Total Events)</span>
            </div>
            <div class="chart-legend-item">
                <div class="chart-legend-dot" style="background:#34d399; color:#34d399;"></div>
                <span>เหตุการณ์ปกติ (Normal)</span>
            </div>
            <div style="margin-left:auto; font-size:0.78rem; color:#475569; display:flex; align-items:center; gap:6px;">
                <i class="fas fa-sync-alt" style="color:#4f46e5;"></i>
                อัปเดตอัตโนมัติทุก 3 วินาที
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// ==================== CONFIGURATION ====================
const UPDATE_INTERVAL = 3000;
let updateTimer = null;
let lastEventId = <?= $last ? $last['id'] : 0 ?>;
let currentPeriod = '7d';

// ==================== INIT ====================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 Dashboard loaded');
    
    // ตรวจสอบสถานะกล้องครั้งแรก
    checkCameraStatus();
    
    // เริ่มตรวจสอบสถานะทุก 3 วินาที
    startStatusCheck();
    
    console.log('✅ Camera status check started');
});

// ==================== CAMERA CONTROL ====================
let cameraStatus = 'stopped';
let statusCheckInterval = null;

// ตรวจสอบสถานะกล้องตอนเริ่มต้น
async function checkCameraStatus() {
    try {
        const response = await fetch('../api/camera_control.php?action=status');
        const data = await response.json();
        
        if (data.success) {
            updateCameraUI(data.status);
        }
    } catch (error) {
        console.error('Error checking camera status:', error);
    }
}

// อัพเดท UI ตามสถานะ
function updateCameraUI(status) {
    cameraStatus = status;
    
    const btn = document.getElementById('toggleCameraBtn');
    const statusIndicator = document.getElementById('statusIndicator');
    const statusText = document.getElementById('statusText');
    
    if (status === 'running') {
        btn.classList.add('stop');
        btn.innerHTML = '<i class="fas fa-stop"></i><span>หยุดระบบตรวจจับ</span>';
        btn.disabled = false;
        statusIndicator.classList.add('active');
        statusText.textContent = 'ระบบกำลังทำงาน';
        
        // เริ่มอัพเดทข้อมูล
        if (!updateTimer) startAutoUpdate();
    } else {
        btn.classList.remove('stop');
        btn.innerHTML = '<i class="fas fa-play"></i><span>เปิดระบบตรวจจับ</span>';
        btn.disabled = false;
        statusIndicator.classList.remove('active');
        statusText.textContent = 'ระบบปิดอยู่';
        
        // หยุดอัพเดทข้อมูล
        stopAutoUpdate();
    }
}

// เปิด/ปิดกล้อง
async function toggleCamera() {
    const btn = document.getElementById('toggleCameraBtn');
    const currentStatus = cameraStatus;
    
    btn.disabled = true;
    
    if (currentStatus === 'stopped') {
        // เปิดกล้อง
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>กำลังเปิดกล้อง...</span>';
        
        try {
            const response = await fetch('../api/camera_control.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=start'
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification('เปิดระบบตรวจจับสำเร็จ!', 'success');
                updateCameraUI('running');
            } else {
                showNotification('ไม่สามารถเปิดกล้องได้: ' + data.message, 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-play"></i><span>เปิดระบบตรวจจับ</span>';
            }
        } catch (error) {
            console.error('Error starting camera:', error);
            showNotification('เกิดข้อผิดพลาด: ตรวจสอบว่ารัน Python script แล้วหรือยัง', 'danger');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play"></i><span>เปิดระบบตรวจจับ</span>';
        }
    } else {
        // ปิดกล้อง
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>กำลังปิดกล้อง...</span>';
        
        try {
            const response = await fetch('../api/camera_control.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=stop'
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification('หยุดระบบตรวจจับสำเร็จ!', 'success');
                updateCameraUI('stopped');
            } else {
                showNotification('ไม่สามารถปิดกล้องได้: ' + data.message, 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-stop"></i><span>หยุดระบบตรวจจับ</span>';
            }
        } catch (error) {
            console.error('Error stopping camera:', error);
            showNotification('เกิดข้อผิดพลาด', 'danger');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-stop"></i><span>หยุดระบบตรวจจับ</span>';
        }
    }
}

// ตรวจสอบสถานะทุก 3 วินาที
function startStatusCheck() {
    if (statusCheckInterval) return;
    
    statusCheckInterval = setInterval(() => {
        checkCameraStatus();
    }, 3000);
}

// หยุดตรวจสอบสถานะ
function stopStatusCheck() {
    if (statusCheckInterval) {
        clearInterval(statusCheckInterval);
        statusCheckInterval = null;
    }
}

// ==================== AUTO UPDATE ====================
function startAutoUpdate() {
    if (updateTimer) return;
    updateTimer = setInterval(() => {
        checkNewEvents();
        updateStatistics();
    }, UPDATE_INTERVAL);
    console.log('✅ Auto update started');
}

function stopAutoUpdate() {
    if (updateTimer) {
        clearInterval(updateTimer);
        updateTimer = null;
        console.log('⏹️ Auto update stopped');
    }
}

// ==================== CHECK NEW EVENTS ====================
async function checkNewEvents() {
    try {
        const response = await fetch('../api/get_latest.php');
        const data = await response.json();
        
        if (data && data.id > lastEventId) {
            lastEventId = data.id;
            updateRecentEvent(data);
            
            if (data.status === 'fall') {
                showFallAlert(data);
                playAlertSound();
            }
        }
    } catch (error) {
        console.error('Error checking events:', error);
    }
}

// ==================== UPDATE STATISTICS ====================
async function updateStatistics() {
    try {
        const response = await fetch('../api/get_events.php');
        const events = await response.json();
        
        const total = events.length;
        const fall = events.filter(e => e.status === 'fall').length;
        const normal = total - fall;
        const fallPercent = total > 0 ? ((fall / total) * 100).toFixed(1) : 0;
        
        document.getElementById('totalEvents').textContent = total;
        document.getElementById('fallEvents').textContent = fall;
        document.getElementById('normalEvents').textContent = normal;
        document.getElementById('fallEventsQuick').textContent = fall;
        document.getElementById('fallPercent').textContent = fallPercent + '%';
        document.getElementById('detectionRate').textContent = fallPercent + '%';
        
        const normalPercent = total > 0 ? (normal / total) * 100 : 0;
        const fallProgressPercent = total > 0 ? (fall / total) * 100 : 0;
        document.getElementById('normalProgress').style.width = normalPercent + '%';
        document.getElementById('fallProgress').style.width = fallProgressPercent + '%';
        document.getElementById('lastUpdate').textContent = 'เมื่อสักครู่';
    } catch (error) {
        console.error('Error updating statistics:', error);
    }
}

// ==================== UPDATE RECENT EVENT ====================
function updateRecentEvent(event) {
    const container = document.getElementById('recentEventContainer');
    const isFall = event.status === 'fall';
    
    const eventHTML = `
        <div class="event-item ${isFall ? 'fall' : 'normal'}">
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                    <span class="badge ${isFall ? 'bg-danger' : 'bg-success'}">
                        <i class="fas ${isFall ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i>
                        ${isFall ? 'พบการล้ม' : 'สถานะปกติ'}
                    </span>
                </div>
                <div style="color: #64748b; font-size: 0.9rem;">
                    <i class="fas fa-clock"></i>
                    ${formatDateTime(event.created_at)}
                </div>
                ${event.device_id ? `
                    <div style="color: #64748b; font-size: 0.85rem; margin-top: 4px;">
                        <i class="fas fa-video"></i> อุปกรณ์: ${event.device_id}
                    </div>
                ` : ''}
            </div>
        </div>
        <a href="events.php" class="btn btn-primary w-100 mt-3">
            <i class="fas fa-list"></i> ดูเหตุการณ์ทั้งหมด
        </a>
    `;
    
    container.innerHTML = eventHTML;
}

// ==================== SHOW FALL ALERT ====================
function showFallAlert(event) {
    const banner = document.getElementById('alertBanner');
    banner.classList.add('show');
    setTimeout(() => banner.classList.remove('show'), 10000);
}

// ==================== PLAY ALERT SOUND ====================
function playAlertSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        for (let i = 0; i < 3; i++) {
            setTimeout(() => {
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                oscillator.frequency.value = 800;
                oscillator.type = 'sine';
                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.5);
            }, i * 600);
        }
    } catch (error) {
        console.error('Error playing alert sound:', error);
    }
}

// ==================== SHOW NOTIFICATION ====================
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'danger' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 15px 25px;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        z-index: 9999;
        animation: slideInUp 0.3s ease;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    setTimeout(() => {
        notification.style.animation = 'slideOutDown 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// ==================== FORMAT DATETIME ====================
function formatDateTime(dateString) {
    const date = new Date(dateString);
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear() + 543;
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    const seconds = date.getSeconds().toString().padStart(2, '0');
    return `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;
}

// ==================== WAVE INTERPOLATION ====================
// เพิ่มจุดข้อมูลระหว่างกันเพื่อให้เส้นดูคลื่น smooth มากขึ้น
function interpolateData(labels, data, factor = 3) {
    if (data.length < 2) return { labels, data };
    const newLabels = [];
    const newData = [];
    for (let i = 0; i < data.length - 1; i++) {
        newLabels.push(labels[i]);
        newData.push(data[i]);
        for (let j = 1; j < factor; j++) {
            const t = j / factor;
            // Cubic hermite interpolation สำหรับโค้งคลื่นสวย
            const p0 = i > 0 ? data[i - 1] : data[i];
            const p1 = data[i];
            const p2 = data[i + 1];
            const p3 = i < data.length - 2 ? data[i + 2] : data[i + 1];
            const val = 0.5 * (
                (2 * p1) +
                (-p0 + p2) * t +
                (2*p0 - 5*p1 + 4*p2 - p3) * t * t +
                (-p0 + 3*p1 - 3*p2 + p3) * t * t * t
            );
            newLabels.push('');
            newData.push(Math.max(0, Math.round(val * 10) / 10));
        }
    }
    newLabels.push(labels[labels.length - 1]);
    newData.push(data[data.length - 1]);
    return { labels: newLabels, data: newData };
}

// ==================== APEXCHARTS STOCK-STYLE ====================
<?php if(count($chart_data) > 0): ?>

// PHP Data
<?php if(count($hourly_data) > 0): ?>
const hourlyLabels = <?= json_encode(array_map(fn($d) => sprintf("%02d:00", $d['hour']), $hourly_data)) ?>;
const hourlyFallData = <?= json_encode(array_column($hourly_data, 'fall_count')) ?>;
const hourlyTotalData = <?= json_encode(array_column($hourly_data, 'total_count')) ?>;
<?php else: ?>
const hourlyLabels = [], hourlyFallData = [], hourlyTotalData = [];
<?php endif; ?>

const weeklyLabels = <?= json_encode(array_map(fn($d) => date('d/m', strtotime($d['date'])), $chart_data)) ?>;
const weeklyFallData = <?= json_encode(array_column($chart_data, 'fall_count')) ?>;
const weeklyTotalData = <?= json_encode(array_column($chart_data, 'total_count')) ?>;

let apexChart = null;

function buildChartOptions(labels, fallData, totalData, periodLabel) {
    const normalData = totalData.map((t, i) => t - fallData[i]);

    // Interpolate data for smoother wave appearance
    const iFall = interpolateData(labels, fallData, 4);
    const iTotal = interpolateData(labels, totalData, 4);
    const iNormal = interpolateData(labels, normalData, 4);
    const iLabels = iFall.labels;

    // Sparkline annotation: max fall point (on original index)
    const maxFall = Math.max(...fallData);
    const maxIdx = fallData.indexOf(maxFall);
    const annotationX = iLabels[maxIdx * 4]; // adjusted for interpolation

    return {
        series: [
            { name: '🔴 การล้ม', data: iFall.data },
            { name: '🟣 รวมทั้งหมด', data: iTotal.data },
            { name: '🟢 ปกติ', data: iNormal.data },
        ],
        chart: {
            type: 'line',
            height: 420,
            background: 'transparent',
            toolbar: {
                show: true,
                tools: { download: true, selection: true, zoom: true, zoomin: true, zoomout: true, pan: true, reset: true },
                theme: 'dark'
            },
            zoom: { enabled: true },
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 900,
                animateGradually: { enabled: true, delay: 120 },
                dynamicAnimation: { enabled: true, speed: 400 }
            },
            fontFamily: 'Segoe UI, sans-serif',
            dropShadow: {
                enabled: true,
                top: 0, left: 0, blur: 8, opacity: 0.3,
                color: ['#f87171', '#818cf8', '#34d399']
            }
        },
        theme: { mode: 'dark' },
        stroke: {
            curve: 'smooth',
            width: [3, 2.5, 2.5],
            dashArray: [0, 6, 0],
            lineCap: 'round',
        },
        fill: { type: 'solid', opacity: 0 },
        colors: ['#f87171', '#818cf8', '#34d399'],
        markers: {
            size: [5, 4, 4],
            colors: ['#0f172a', '#0f172a', '#0f172a'],
            strokeColors: ['#f87171', '#818cf8', '#34d399'],
            strokeWidth: 2.5,
            hover: { size: 8, sizeOffset: 2 }
        },
        xaxis: {
            categories: iLabels,
            labels: {
                formatter: val => val || '',
                style: { colors: '#64748b', fontSize: '12px', fontWeight: 500 }
            },
            axisBorder: { show: false },
            axisTicks: { show: false },
            crosshairs: {
                show: true,
                stroke: { color: '#4f46e5', width: 1, dashArray: 4 }
            },
            tooltip: { enabled: false }
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            labels: {
                style: { colors: '#64748b', fontSize: '12px' },
                formatter: val => Math.round(val) + ' ครั้ง'
            },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        grid: {
            borderColor: 'rgba(255,255,255,0.06)',
            strokeDashArray: 4,
            xaxis: { lines: { show: true } },
            yaxis: { lines: { show: true } },
            padding: { top: 0, right: 20, bottom: 0, left: 10 }
        },
        tooltip: {
            shared: true,
            intersect: false,
            theme: 'dark',
            style: { fontSize: '13px', fontFamily: 'Segoe UI, sans-serif' },
            custom: function({ series, seriesIndex, dataPointIndex, w }) {
                const label = w.globals.categoryLabels[dataPointIndex];
                if (!label) return null; // skip interpolated points
                const fall = Math.round(series[0][dataPointIndex]);
                const total = Math.round(series[1][dataPointIndex]);
                const normal = Math.round(series[2][dataPointIndex]);
                const fallRate = total > 0 ? ((fall / total) * 100).toFixed(1) : 0;

                return `
                <div style="background:#1e293b; border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:14px 18px; min-width:200px;">
                    <div style="font-size:12px; color:#94a3b8; margin-bottom:10px; letter-spacing:0.5px;">
                        📅 ${periodLabel === 'today' ? 'เวลา ' + label + ' น.' : 'วันที่ ' + label}
                    </div>
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:7px;">
                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#f87171;box-shadow:0 0 6px #f87171;"></span>
                        <span style="color:#f1f5f9; font-size:13px;">การล้ม</span>
                        <span style="margin-left:auto; font-weight:700; color:#f87171;">${fall} ครั้ง</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:7px;">
                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#818cf8;box-shadow:0 0 6px #818cf8;"></span>
                        <span style="color:#f1f5f9; font-size:13px;">รวมทั้งหมด</span>
                        <span style="margin-left:auto; font-weight:700; color:#818cf8;">${total} ครั้ง</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#34d399;box-shadow:0 0 6px #34d399;"></span>
                        <span style="color:#f1f5f9; font-size:13px;">ปกติ</span>
                        <span style="margin-left:auto; font-weight:700; color:#34d399;">${normal} ครั้ง</span>
                    </div>
                    <div style="border-top:1px solid rgba(255,255,255,0.08); padding-top:8px; font-size:12px; color:#94a3b8;">
                        ⚠️ อัตราการล้ม: <strong style="color:${fallRate > 50 ? '#f87171' : '#34d399'}">${fallRate}%</strong>
                    </div>
                </div>`;
            }
        },
        annotations: {
            points: maxFall > 0 ? [{
                x: annotationX,
                y: maxFall,
                seriesIndex: 0,
                marker: {
                    size: 7,
                    fillColor: '#f87171',
                    strokeColor: '#fff',
                    strokeWidth: 2,
                },
                label: {
                    text: '⚠ สูงสุด',
                    style: {
                        background: '#f87171',
                        color: '#fff',
                        fontSize: '11px',
                        fontWeight: 700,
                        padding: { left: 8, right: 8, top: 4, bottom: 4 },
                        borderRadius: 6,
                    },
                    offsetY: -12,
                }
            }] : []
        },
        legend: { show: false },
        dataLabels: { enabled: false },
    };
}

function createChart(period = '7d') {
    const labels = period === 'today' ? hourlyLabels : weeklyLabels;
    const fallData = period === 'today' ? hourlyFallData : weeklyFallData;
    const totalData = period === 'today' ? hourlyTotalData : weeklyTotalData;

    if (!labels || labels.length === 0) {
        document.getElementById('fallChart').innerHTML = `
            <div style="display:flex; align-items:center; justify-content:center; height:300px; color:#64748b; flex-direction:column; gap:12px;">
                <i class="fas fa-chart-area" style="font-size:3rem; opacity:0.3;"></i>
                <p>ยังไม่มีข้อมูลในช่วงเวลานี้</p>
            </div>`;
        return;
    }

    if (apexChart) {
        apexChart.destroy();
        apexChart = null;
    }

    const options = buildChartOptions(labels, fallData, totalData, period);
    apexChart = new ApexCharts(document.getElementById('fallChart'), options);
    apexChart.render();
}

function switchChartPeriod(period, btn) {
    currentPeriod = period;
    document.querySelectorAll('.chart-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    createChart(period);
}

// สร้างกราฟตอนโหลดหน้า
createChart('7d');

<?php endif; ?>

// ==================== CSS ANIMATIONS ====================
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInUp {
        from { transform: translateY(100px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    @keyframes slideOutDown {
        from { transform: translateY(0); opacity: 1; }
        to { transform: translateY(100px); opacity: 0; }
    }
`;
document.head.appendChild(style);

// ==================== INIT ====================
console.log('🎯 Fall Detection Dashboard Loaded');
console.log('💡 กดรูปโปรไฟล์เพื่อแก้ไขข้อมูล');
</script>

</body>
</html>