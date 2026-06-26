<?php
require_once "auth/check_login.php";
require_once "../config/db.php";

// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Query
$where = "";
if ($status_filter != 'all') {
    $where = "WHERE status = :status";
}

// Count total
$count_sql = "SELECT COUNT(*) FROM events $where";
$count_stmt = $conn->prepare($count_sql);
if ($status_filter != 'all') {
    $count_stmt->bindParam(':status', $status_filter);
}
$count_stmt->execute();
$total = $count_stmt->fetchColumn();
$total_pages = ceil($total / $per_page);

// Get events
$sql = "SELECT * FROM events $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);
if ($status_filter != 'all') {
    $stmt->bindParam(':status', $status_filter);
}
$stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_events = $conn->query("SELECT COUNT(*) FROM events")->fetchColumn();
$fall_events = $conn->query("SELECT COUNT(*) FROM events WHERE status='fall'")->fetchColumn();
$normal_events = $conn->query("SELECT COUNT(*) FROM events WHERE status='normal'")->fetchColumn();

/**
 * ✨ ฟังก์ชันสำหรับหา path รูปภาพที่ถูกต้อง
 * รองรับทั้งโฟลเดอร์ normal/ และ fall/
 */
function get_image_path($image_filename, $status = 'normal') {
    if (empty($image_filename)) {
        return null;
    }

    // web URL สำหรับ <img src="..."> (relative จาก admin/)
    $web_base = "../uploads/images/";

    // ถ้ามี subfolder อยู่แล้ว เช่น "normal/1234_normal.jpg"
    if (strpos($image_filename, '/') !== false) {
        return $web_base . $image_filename;
    }

    // ใช้ __DIR__ เพื่อให้ file_exists() ทำงานถูกต้องบน Windows
    $abs_base = __DIR__ . "/../uploads/images/";

    $candidates = ($status === 'fall')
        ? ["fall/", "normal/", ""]
        : ["normal/", "fall/", ""];

    foreach ($candidates as $sub) {
        if (file_exists($abs_base . $sub . $image_filename)) {
            return $web_base . $sub . $image_filename;
        }
    }

    // fallback
    return $web_base . $status . "/" . $image_filename;
}

/**
 * ✨ ฟังก์ชันสำหรับหา path วิดีโอที่ถูกต้อง
 */
function get_video_path($video_filename) {
    if (empty($video_filename)) {
        return null;
    }

    $web_base = "../uploads/videos/";
    $abs_base = __DIR__ . "/../uploads/videos/";

    // ถ้ามี subfolder อยู่แล้ว เช่น "fall/1234_vid.mp4"
    if (strpos($video_filename, '/') !== false) {
        return $web_base . $video_filename;
    }

    foreach (["fall/", "normal/", ""] as $sub) {
        if (file_exists($abs_base . $sub . $video_filename)) {
            return $web_base . $sub . $video_filename;
        }
    }

    return $web_base . $video_filename;
}

/**
 * ✨ ฟังก์ชันตรวจสอบว่าไฟล์มีอยู่จริงหรือไม่
 */
function file_exists_check($filepath) {
    return file_exists($filepath);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เหตุการณ์ล้ม | Fall Detection System</title>
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
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .top-bar h4 {
        margin: 0 0 5px 0;
        color: var(--dark-bg);
        font-weight: 700;
    }
    
    /* ✨ ปุ่มบันทึกภาพวงกลม */
    .capture-btn-container {
        position: relative;
    }
    
    .capture-btn {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #10b981, #059669);
        border: 4px solid white;
        box-shadow: 0 6px 25px rgba(16, 185, 129, 0.4);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .capture-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 8px 30px rgba(16, 185, 129, 0.6);
    }
    
    .capture-btn:active {
        transform: scale(0.95);
    }
    
    .capture-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .capture-btn i {
        font-size: 2rem;
        color: white;
        margin-bottom: 4px;
    }
    
    .capture-btn span {
        font-size: 0.7rem;
        color: white;
        font-weight: 600;
    }
    
    .capture-btn.capturing {
        animation: pulse 1s ease-in-out;
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
            box-shadow: 0 6px 25px rgba(16, 185, 129, 0.4);
        }
        50% {
            transform: scale(1.15);
            box-shadow: 0 8px 35px rgba(16, 185, 129, 0.8);
        }
    }
    
    .capture-status {
        position: absolute;
        top: -10px;
        right: -10px;
        background: white;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        display: none;
    }
    
    .capture-status.success {
        display: block;
        color: #10b981;
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        gap: 15px;
        transition: all 0.3s ease;
    }
    
    .stat-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
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
    
    .filter-card {
        background: white;
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .filter-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 10px 20px;
        border: 2px solid #e2e8f0;
        background: white;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        color: #64748b;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .filter-btn:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
        transform: translateY(-2px);
    }
    
    .filter-btn.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .filter-btn.active.fall {
        background: var(--danger-color);
        border-color: var(--danger-color);
    }
    
    .filter-btn.active.normal {
        background: var(--success-color);
        border-color: var(--success-color);
    }
    
    .events-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .events-header {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--dark-bg);
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .event-item {
        padding: 20px;
        border: 2px solid #f1f5f9;
        border-radius: 12px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .event-item:hover {
        border-color: #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transform: translateY(-2px);
    }
    
    .event-item.fall {
        border-left: 4px solid var(--danger-color);
    }
    
    .event-item.normal {
        border-left: 4px solid var(--success-color);
    }
    
    .event-status {
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .event-status.fall {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: var(--danger-color);
    }
    
    .event-status.normal {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: var(--success-color);
    }
    
    .event-media-wrap {
        display: flex;
        gap: 15px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .media-item {
        position: relative;
    }
    
    .media-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        backdrop-filter: blur(5px);
    }
    
    .media-badge.fall {
        background: rgba(239, 68, 68, 0.9);
    }
    
    .media-badge.normal {
        background: rgba(16, 185, 129, 0.9);
    }
    
    .event-image {
        width: 160px;
        height: 120px;
        object-fit: cover;
        border-radius: 10px;
        border: 3px solid #f1f5f9;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .event-image:hover {
        border-color: var(--primary-color);
        transform: scale(1.05);
    }
    
    .event-image.fall {
        border-color: rgba(239, 68, 68, 0.3);
    }
    
    .event-image.normal {
        border-color: rgba(16, 185, 129, 0.3);
    }
    
    .video-container {
        position: relative;
        width: 200px;
        height: 120px;
    }
    
    .event-video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 10px;
        border: 3px solid rgba(239, 68, 68, 0.3);
    }
    
    .video-error {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(239, 68, 68, 0.1);
        border-radius: 10px;
        display: none;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: var(--danger-color);
        font-size: 0.85rem;
        padding: 10px;
    }
    
    .pagination {
        display: flex;
        gap: 8px;
        justify-content: center;
        margin-top: 25px;
        flex-wrap: wrap;
    }
    
    .page-link {
        padding: 10px 16px;
        border: 2px solid #e2e8f0;
        background: white;
        border-radius: 8px;
        color: #64748b;
        text-decoration: none;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .page-link:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }
    
    .page-link.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .no-data {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }
    
    .no-data i {
        font-size: 4rem;
        margin-bottom: 15px;
        opacity: 0.3;
    }
    
    /* Image Zoom Modal */
    .img-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
    
    .img-modal.open {
        display: block;
    }
    
    .img-modal-backdrop {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
    }
    
    .img-modal-content {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .img-modal-content img {
        max-width: 90%;
        max-height: 90%;
        object-fit: contain;
        border-radius: 10px;
        box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
    }
    
    .img-modal-close {
        position: absolute;
        top: 20px;
        right: 30px;
        font-size: 3rem;
        color: white;
        background: none;
        border: none;
        cursor: pointer;
        z-index: 10000;
        transition: all 0.3s ease;
    }
    
    .img-modal-close:hover {
        transform: scale(1.2);
        color: #ef4444;
    }
    
    /* Toast Notification */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
    }
    
    .toast {
        background: white;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        margin-bottom: 10px;
        min-width: 300px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideInRight 0.3s ease-out;
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
    
    .toast.success {
        border-left: 4px solid #10b981;
    }
    
    .toast.error {
        border-left: 4px solid #ef4444;
    }
    
    .toast i {
        font-size: 1.5rem;
    }
    
    .toast.success i {
        color: #10b981;
    }
    
    .toast.error i {
        color: #ef4444;
    }
    
    /* Loading Spinner */
    .loading-spinner {
        display: none;
        text-align: center;
        padding: 40px;
    }
    
    .loading-spinner.show {
        display: block;
    }
    
    .spinner {
        border: 4px solid #f3f4f6;
        border-top: 4px solid var(--primary-color);
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 1s linear infinite;
        margin: 0 auto 15px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .sidebar {
            width: 100%;
            height: auto;
            position: relative;
        }
        
        .content {
            margin-left: 0;
            padding: 20px;
        }
        
        .top-bar {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .capture-btn {
            width: 70px;
            height: 70px;
        }
        
        .stats-row {
            grid-template-columns: 1fr;
        }
        
        .event-media-wrap {
            flex-direction: column;
        }
        
        .event-image,
        .video-container {
            width: 100%;
            max-width: 300px;
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
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="events.php" class="active">
            <i class="fas fa-list-alt"></i> เหตุการณ์ล้ม
        </a>
        <a href="gps_system.php">
             <i class="fas fa-map-marked-alt"></i> แผนที่การล้ม
        </a>
        <a href="profile.php">
            <i class="fas fa-user"></i> โปรไฟล์
        </a>
        <a href="settings.php">
            <i class="fas fa-cog"></i> ตั้งค่า
        </a>
        <a href="logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
        </a>
    </div>
</div>

<div class="content">
    <!-- Top Bar with Capture Button -->
    <div class="top-bar">
        <div>
            <h4><i class="fas fa-clipboard-list"></i> รายการเหตุการณ์</h4>
            <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                ดูและจัดการเหตุการณ์ทั้งหมด (Normal & Fall)
            </p>
        </div>
        
        <!-- ปุ่มบันทึกภาพวงกลม -->
        <div class="capture-btn-container">
            <button class="capture-btn" id="captureBtn" onclick="captureImage()">
                <i class="fas fa-camera"></i>
                <span>บันทึกภาพ</span>
            </button>
            <div class="capture-status" id="captureStatus">สำเร็จ!</div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-icon primary">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div>
                <div style="font-size: 0.85rem; color: #64748b;">เหตุการณ์ทั้งหมด</div>
                <div style="font-size: 1.8rem; font-weight: 700; color: var(--dark-bg);">
                    <?= number_format($total_events) ?>
                </div>
                <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 4px;">
                    Total Events
                </div>
            </div>
        </div>
        
        <div class="stat-box">
            <div class="stat-icon danger">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <div style="font-size: 0.85rem; color: #64748b;">การล้ม (Fall)</div>
                <div style="font-size: 1.8rem; font-weight: 700; color: var(--danger-color);">
                    <?= number_format($fall_events) ?>
                </div>
                <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 4px;">
                    <?= $total_events > 0 ? round(($fall_events / $total_events) * 100, 1) : 0 ?>% ของทั้งหมด
                </div>
            </div>
        </div>
        
        <div class="stat-box">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div style="font-size: 0.85rem; color: #64748b;">ปกติ (Normal)</div>
                <div style="font-size: 1.8rem; font-weight: 700; color: var(--success-color);">
                    <?= number_format($normal_events) ?>
                </div>
                <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 4px;">
                    <?= $total_events > 0 ? round(($normal_events / $total_events) * 100, 1) : 0 ?>% ของทั้งหมด
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="filter-card">
        <div style="margin-bottom: 15px; font-weight: 600; color: var(--dark-bg);">
            <i class="fas fa-filter"></i> กรองข้อมูล:
        </div>
        <div class="filter-buttons">
            <a href="events.php?status=all" 
               class="filter-btn <?= $status_filter == 'all' ? 'active' : '' ?>">
                <i class="fas fa-th"></i> ทั้งหมด (<?= number_format($total_events) ?>)
            </a>
            <a href="events.php?status=fall" 
               class="filter-btn fall <?= $status_filter == 'fall' ? 'active fall' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i> การล้ม (<?= number_format($fall_events) ?>)
            </a>
            <a href="events.php?status=normal" 
               class="filter-btn normal <?= $status_filter == 'normal' ? 'active normal' : '' ?>">
                <i class="fas fa-check-circle"></i> ปกติ (<?= number_format($normal_events) ?>)
            </a>
        </div>
    </div>

    <!-- Events List -->
    <div class="events-card">
        <div class="events-header">
            <span>
                <i class="fas fa-clipboard-list"></i> 
                รายการเหตุการณ์
                <?php if($status_filter == 'fall'): ?>
                    <span style="color: var(--danger-color);">(Fall)</span>
                <?php elseif($status_filter == 'normal'): ?>
                    <span style="color: var(--success-color);">(Normal)</span>
                <?php endif; ?>
                (<?= number_format($total) ?> รายการ)
            </span>
            <span style="font-size: 0.9rem; font-weight: 400; color: #64748b;">
                หน้า <?= $page ?> จาก <?= $total_pages ?>
            </span>
        </div>
        
        <div id="eventsContainer">
            <?php if (count($events) > 0): ?>
                <?php foreach ($events as $event): ?>
                <div class="event-item <?= $event['status'] ?>" data-event-id="<?= $event['id'] ?>">
                    <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                        
                        <div class="event-media-wrap">
                          <!-- ✨ Image with Badge -->
                          <?php if (!empty($event['image_path'])): ?>
                            <?php
                                // สร้าง URL ตรงๆ จาก image_path ใน DB
                                // image_path เก็บเป็น "normal/xxx.jpg" หรือ "fall/xxx.jpg"
                                $image_src = "../uploads/images/" . $event['image_path'];
                            ?>
                            <div class="media-item">
                                <img src="<?= htmlspecialchars($image_src) ?>"
                                     alt="Event Image"
                                     class="event-image <?= $event['status'] ?> zoomable"
                                     data-status="<?= $event['status'] ?>"
                                     onclick="openImageModal(this.src)"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="event-image <?= $event['status'] ?>" 
                                     style="background: #f1f5f9; display:none; align-items:center; justify-content:center; flex-direction: column; gap: 8px;">
                                    <i class="fas fa-exclamation-circle" style="font-size: 2rem; color: #cbd5e1;"></i>
                                    <span style="font-size: 0.75rem; color: #94a3b8;">ไม่พบรูปภาพ</span>
                                </div>
                                <div class="media-badge <?= $event['status'] ?>">
                                    <?= strtoupper($event['status']) ?>
                                </div>
                            </div>
                          <?php else: ?>
                            <div class="media-item">
                                <div class="event-image" style="background: #f1f5f9; display:flex; align-items:center; justify-content:center;">
                                  <i class="fas fa-image" style="font-size: 2rem; color: #cbd5e1;"></i>
                                </div>
                                <div class="media-badge <?= $event['status'] ?>">
                                    <?= strtoupper($event['status']) ?>
                                </div>
                            </div>
                          <?php endif; ?>

                          <!-- ✨ Video (เฉพาะ fall events) -->
                          <?php if ($event['status'] === 'fall' && !empty($event['video_path'])): ?>
                            <?php
                                $video_src = "../uploads/videos/" . $event['video_path'];
                            ?>
                            <div class="video-container">
                                <video class="event-video" controls preload="metadata">
                                    <source src="<?= htmlspecialchars($video_src) ?>" type="video/mp4">
                                    เบราว์เซอร์ของคุณไม่รองรับการเล่นวิดีโอ
                                </video>
                              
                              <div class="video-error">
                                <div>
                                  <i class="fas fa-exclamation-circle"></i><br>
                                  ไม่สามารถเล่นวิดีโอได้<br>
                                  <small>กรุณาดาวน์โหลดเพื่อดู</small><br>
                                  <a href="<?= htmlspecialchars($video_path) ?>" 
                                     download 
                                     class="btn btn-sm btn-danger mt-2"
                                     style="background: var(--danger-color); color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; display: inline-block; margin-top: 8px;">
                                    <i class="fas fa-download"></i> ดาวน์โหลด
                                  </a>
                                </div>
                              </div>
                            </div>
                          <?php endif; ?>
                        </div>
                        
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px; flex-wrap: wrap;">
                                <span class="event-status <?= $event['status'] ?>">
                                    <?php if($event['status'] == 'fall'): ?>
                                        <i class="fas fa-exclamation-circle"></i> พบการล้ม (Fall Detected)
                                    <?php else: ?>
                                        <i class="fas fa-check-circle"></i> สถานะปกติ (Normal Status)
                                    <?php endif; ?>
                                </span>
                                
                                <?php if(!empty($event['device_id'])): ?>
                                <span style="background: #f1f5f9; padding: 4px 12px; border-radius: 6px; font-size: 0.8rem; color: #64748b;">
                                    <i class="fas fa-video"></i> <?= htmlspecialchars($event['device_id']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <div style="color: #64748b; font-size: 0.9rem; margin-bottom: 6px;">
                                <i class="fas fa-clock"></i>
                                <?= date('d/m/Y H:i:s', strtotime($event['created_at'])) ?>
                                <span style="color: #94a3b8; margin-left: 8px;">
                                    (<?= date('D', strtotime($event['created_at'])) ?>)
                                </span>
                            </div>
                            
                            <div style="color: #94a3b8; font-size: 0.85rem;">
                                <i class="fas fa-hashtag"></i> Event ID: <?= $event['id'] ?>
                                <?php if(!empty($event['image_path'])): ?>
                                <span style="margin-left: 12px;">
                                    <i class="fas fa-image"></i> <?= htmlspecialchars($event['image_path']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <h5 style="color: #64748b;">ไม่พบข้อมูล</h5>
                    <p>
                        <?php if($status_filter == 'fall'): ?>
                            ยังไม่มีบันทึกเหตุการณ์การล้ม (Fall Events)
                        <?php elseif($status_filter == 'normal'): ?>
                            ยังไม่มีบันทึกเหตุการณ์ปกติ (Normal Events)
                        <?php else: ?>
                            ยังไม่มีบันทึกเหตุการณ์ในระบบ
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Loading Spinner -->
        <div class="loading-spinner" id="loadingSpinner">
            <div class="spinner"></div>
            <p style="color: #64748b;">กำลังโหลดข้อมูล...</p>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?status=<?= $status_filter ?>&page=<?= $page - 1 ?>" class="page-link">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php 
        // Pagination logic
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        if ($start_page > 1): ?>
            <a href="?status=<?= $status_filter ?>&page=1" class="page-link">1</a>
            <?php if ($start_page > 2): ?>
                <span class="page-link" style="cursor: default; border-color: transparent;">...</span>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="?status=<?= $status_filter ?>&page=<?= $i ?>" 
               class="page-link <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($end_page < $total_pages): ?>
            <?php if ($end_page < $total_pages - 1): ?>
                <span class="page-link" style="cursor: default; border-color: transparent;">...</span>
            <?php endif; ?>
            <a href="?status=<?= $status_filter ?>&page=<?= $total_pages ?>" class="page-link">
                <?= $total_pages ?>
            </a>
        <?php endif; ?>
        
        <?php if ($page < $total_pages): ?>
        <a href="?status=<?= $status_filter ?>&page=<?= $page + 1 ?>" class="page-link">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Image Zoom Modal -->
<div id="imgModal" class="img-modal" aria-hidden="true">
  <div class="img-modal-backdrop"></div>
  <div class="img-modal-content" role="dialog" aria-modal="true">
    <button type="button" class="img-modal-close" aria-label="Close">×</button>
    <img id="imgModalTarget" src="" alt="Zoomed image">
  </div>
</div>

<script>
// ==================== GLOBAL VARIABLES ====================
let isCapturing = false;

// ==================== IMAGE ZOOM MODAL ====================
function openImageModal(src) {
    const modal = document.getElementById('imgModal');
    const modalImg = document.getElementById('imgModalTarget');
    
    modalImg.src = src;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    const modal = document.getElementById('imgModal');
    const modalImg = document.getElementById('imgModalTarget');
    
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    modalImg.src = '';
    document.body.style.overflow = '';
}

// Event Listeners for Modal
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('imgModal');
    const closeBtn = modal.querySelector('.img-modal-close');
    const backdrop = modal.querySelector('.img-modal-backdrop');
    
    // Close button
    closeBtn.addEventListener('click', closeImageModal);
    
    // Backdrop click
    backdrop.addEventListener('click', closeImageModal);
    
    // ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) {
            closeImageModal();
        }
    });
});

// ==================== VIDEO ERROR HANDLING ====================
document.addEventListener('DOMContentLoaded', function() {
    const videos = document.querySelectorAll('.event-video');
    
    videos.forEach(video => {
        const container = video.closest('.video-container');
        const errorDiv = container ? container.querySelector('.video-error') : null;
        
        // Error event
        video.addEventListener('error', function(e) {
            console.error('Video error:', e);
            if (errorDiv) {
                errorDiv.style.display = 'flex';
            }
        });
        
        // Loaded event
        video.addEventListener('loadeddata', function() {
            if (errorDiv) {
                errorDiv.style.display = 'none';
            }
        });
        
        // Try to load
        video.load();
    });
});

// ==================== CAPTURE IMAGE FUNCTION ====================
function captureImage() {
    if (isCapturing) {
        showToast('error', 'กำลังดำเนินการอยู่ กรุณารอสักครู่');
        return;
    }
    
    const btn = document.getElementById('captureBtn');
    const status = document.getElementById('captureStatus');
    
    // Set capturing state
    isCapturing = true;
    btn.classList.add('capturing');
    btn.disabled = true;
    
    // ส่งคำสั่งไปยัง API
    fetch('../api/capture_image.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'device_id=cam01'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // แสดงสถานะสำเร็จ
            status.classList.add('success');
            
            // แสดง toast notification
            showToast('success', 'คำสั่งบันทึกภาพถูกส่งไปยังกล้องแล้ว! กำลังรีเฟรชข้อมูล...');
            
            // รีเฟรชหน้าหลัง 2 วินาที
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showToast('error', 'เกิดข้อผิดพลาด: ' + (data.message || 'ไม่สามารถส่งคำสั่งได้'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ กรุณาลองใหม่อีกครั้ง');
    })
    .finally(() => {
        setTimeout(() => {
            btn.classList.remove('capturing');
            btn.disabled = false;
            status.classList.remove('success');
            isCapturing = false;
        }, 2000);
    });
}

// ==================== TOAST NOTIFICATION ====================
function showToast(type, message) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    toast.innerHTML = `
        <i class="fas ${icon}"></i>
        <div>
            <strong>${type === 'success' ? 'สำเร็จ!' : 'ข้อผิดพลาด'}</strong>
            <div style="font-size: 0.9rem; color: #64748b;">${message}</div>
        </div>
    `;
    
    container.appendChild(toast);
    
    // ลบ toast หลัง 5 วินาที
    setTimeout(() => {
        toast.style.animation = 'slideInRight 0.3s ease-out reverse';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 5000);
}

// ==================== AUTO REFRESH (Optional) ====================
// Uncomment to enable auto-refresh every 30 seconds
/*
setInterval(() => {
    if (!isCapturing) {
        console.log('Auto-refreshing events...');
        location.reload();
    }
}, 30000);
*/

// ==================== INITIALIZE ====================
console.log('📹 Fall Detection Events Page Loaded');
console.log('📊 Total Events: <?= $total_events ?>');
console.log('⚠️ Fall Events: <?= $fall_events ?>');
console.log('✅ Normal Events: <?= $normal_events ?>');
console.log('🔍 Current Filter: <?= $status_filter ?>');
</script>

<script>
// ===========================
// AUTO UPDATE GPS (Real-time)
// ===========================
let currentGPS = {
    latitude: null,
    longitude: null,
    accuracy: null
};

// ฟังก์ชันอัปเดต GPS
function updateGPS() {
    if (!navigator.geolocation) {
        console.warn('⚠️ Browser ไม่รองรับ Geolocation');
        return;
    }
    
    navigator.geolocation.getCurrentPosition(
        position => {
            currentGPS.latitude = position.coords.latitude;
            currentGPS.longitude = position.coords.longitude;
            currentGPS.accuracy = position.coords.accuracy;
            
            console.log('📍 GPS Updated:', currentGPS);
            
            // ส่งไปอัปเดต server (ผ่าน gps_system.php ในโฟลเดอร์เดียวกัน)
            sendGPSToServer(currentGPS);
        },
        error => {
            console.error('❌ GPS Error:', error.message);
        },
        {
            enableHighAccuracy: true,  // ความแม่นยำสูง
            timeout: 10000,            // รอ 10 วินาที
            maximumAge: 0              // ไม่ใช้ cache เก่า
        }
    );
}

// ส่ง GPS ไปยัง server
async function sendGPSToServer(gps) {
    try {
        const response = await fetch('gps_system.php?api=1', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=update&latitude=${gps.latitude}&longitude=${gps.longitude}&accuracy=${gps.accuracy}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('✅ GPS sent to server');
        }
    } catch (error) {
        console.error('❌ Failed to send GPS:', error);
    }
}

// ===========================
// AUTO START
// ===========================
// อัปเดต GPS ทันทีที่โหลดหน้า
updateGPS();

// อัปเดต GPS ทุก 30 วินาที
setInterval(updateGPS, 30000);

console.log('🎯 Real-time GPS tracking started!');
</script>


</body>
</html>