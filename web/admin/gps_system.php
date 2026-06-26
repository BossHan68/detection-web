<?php
/**
 * 🗺️ GPS System - All-in-One
 * รวมทุกอย่างเกี่ยวกับ GPS + Map ไว้ในไฟล์เดียว
 * 
 * Features:
 * - แสดงแผนที่การล้ม (Map View)
 * - อัปเดต GPS จาก Browser
 * - ดึงข้อมูล GPS ปัจจุบัน
 * - API endpoints สำหรับ GPS
 */

require_once "auth/check_login.php";
require_once "../config/db.php";

// ===========================
// CONFIG
// ===========================
// gps_system.php อยู่ใน admin/ → gps_config.json อยู่ใน web/ (ขึ้นไป 1 ระดับ)
$GPS_CONFIG_FILE = __DIR__ . "/../gps_config.json";

// ===========================
// HANDLE API REQUESTS
// ===========================
if (isset($_GET['api']) || isset($_POST['api'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? $_GET['action'] ?? 'get';
    
    switch ($action) {
        case 'update':
            // อัปเดตตำแหน่ง GPS
            $latitude = $_POST['latitude'] ?? null;
            $longitude = $_POST['longitude'] ?? null;
            $accuracy = $_POST['accuracy'] ?? null;
            
            if ($latitude === null || $longitude === null) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing latitude or longitude'
                ]);
                exit;
            }
            
            // สร้างโฟลเดอร์ถ้ายังไม่มี
            $dir = dirname($GPS_CONFIG_FILE);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            
            $gps_data = [
                'latitude' => floatval($latitude),
                'longitude' => floatval($longitude),
                'accuracy' => $accuracy ? floatval($accuracy) : null,
                'updated_at' => date('Y-m-d H:i:s'),
                'timestamp' => time()
            ];
            
            if (file_put_contents($GPS_CONFIG_FILE, json_encode($gps_data, JSON_PRETTY_PRINT)) !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'GPS location updated',
                    'data' => $gps_data
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to write GPS config file'
                ]);
            }
            exit;
        
        case 'get':
            // อ่านตำแหน่ง GPS
            if (file_exists($GPS_CONFIG_FILE)) {
                $gps_data = json_decode(file_get_contents($GPS_CONFIG_FILE), true);
                
                echo json_encode([
                    'success' => true,
                    'data' => $gps_data
                ]);
            } else {
                // ถ้ายังไม่มี ใช้ค่า default (Hat Yai)
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'latitude' => 7.0087,
                        'longitude' => 100.4744,
                        'accuracy' => null,
                        'updated_at' => null,
                        'timestamp' => null
                    ],
                    'note' => 'Using default location (Hat Yai)'
                ]);
            }
            exit;
        
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action. Use: update or get'
            ]);
            exit;
    }
}

// ===========================
// MAP VIEW (HTML)
// ===========================

// ดึงเหตุการณ์ล้มที่มี GPS
$falls_query = $conn->query("
    SELECT id, device_id, latitude, longitude, created_at, image_path
    FROM events 
    WHERE status = 'fall' 
    AND latitude IS NOT NULL 
    AND longitude IS NOT NULL
    ORDER BY created_at DESC
    LIMIT 100
");
$falls = $falls_query->fetchAll(PDO::FETCH_ASSOC);

// นับการล้มทั้งหมด (รวมที่ไม่มี GPS)
$total_falls     = $conn->query("SELECT COUNT(*) FROM events WHERE status='fall'")->fetchColumn();
$falls_with_gps  = count($falls);
$falls_no_gps    = $total_falls - $falls_with_gps;

// ดึงข้อมูลผู้ใช้
$admin_id = $_SESSION['admin_id'];
$admin_query = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$admin_query->execute([$admin_id]);
$admin_data = $admin_query->fetch(PDO::FETCH_ASSOC);

$profile_image = '';
if (!empty($admin_data['profile_image']) && file_exists("../uploads/profiles/" . $admin_data['profile_image'])) {
    $profile_image = "../uploads/profiles/" . $admin_data['profile_image'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>แผนที่การล้ม | Fall Detection System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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
    
    #map {
        height: 600px;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .map-container {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .legend {
        background: white;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-top: 20px;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .legend-color {
        width: 20px;
        height: 20px;
        border-radius: 50%;
    }
    
    .gps-info {
        background: #f8fafc;
        padding: 15px;
        border-radius: 10px;
        margin-top: 15px;
    }
    
    .gps-info-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .gps-info-item:last-child {
        border-bottom: none;
    }
    
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .content {
            margin-left: 0;
            padding: 15px;
        }
        
        #map {
            height: 400px;
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
        <a href="gps_system.php" class="active">
            <i class="fas fa-map-marked-alt"></i>
            <span>แผนที่การล้ม</span>
        </a>
        <a href="profile.php">
            <i class="fas fa-user-circle"></i>
            <span>โปรไฟล์</span>
        </a>
        <a href="settings.php">
            <i class="fas fa-cog"></i>
            <span>ตั้งค่า</span>
        </a>
        <a href="logout.php" style="color: #fca5a5; margin-top: 20px;">
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
            <h4><i class="fas fa-map-marked-alt"></i> แผนที่การล้ม</h4>
            <small class="text-muted">
                การล้มทั้งหมด <strong><?= $total_falls ?> ครั้ง</strong> &nbsp;|&nbsp;
                มีตำแหน่ง GPS <strong class="text-success"><?= $falls_with_gps ?> จุด</strong>
                <?php if ($falls_no_gps > 0): ?>
                &nbsp;|&nbsp; ไม่มี GPS <strong class="text-warning"><?= $falls_no_gps ?> ครั้ง</strong>
                <?php endif; ?>
            </small>
        </div>
        <?php if ($falls_no_gps > 0): ?>
        <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:0.85rem; border-radius:10px;">
            <i class="fas fa-exclamation-triangle"></i>
            มี <strong><?= $falls_no_gps ?> เหตุการณ์</strong> ที่ไม่มีข้อมูล GPS — ไม่แสดงบนแผนที่<br>
            <small class="text-muted">เปิดหน้าเว็บค้างไว้เพื่อให้ Browser ส่ง GPS ก่อนเกิดเหตุ</small>
        </div>
        <?php endif; ?>
    </div>

    <!-- Map -->
    <div class="map-container">
        <div id="map"></div>
        
        <!-- GPS Info -->
        <div class="gps-info">
            <h6><i class="fas fa-satellite-dish"></i> ข้อมูล GPS</h6>
            <div class="gps-info-item">
                <span><i class="fas fa-map-marker-alt"></i> ตำแหน่งปัจจุบัน:</span>
                <span id="currentPosition">กำลังระบุตำแหน่ง...</span>
            </div>
            <div class="gps-info-item">
                <span><i class="fas fa-crosshairs"></i> ความแม่นยำ:</span>
                <span id="currentAccuracy">-</span>
            </div>
            <div class="gps-info-item">
                <span><i class="fas fa-clock"></i> อัปเดตล่าสุด:</span>
                <span id="lastUpdate">-</span>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="legend">
            <h6><i class="fas fa-info-circle"></i> คำอธิบาย</h6>
            <div class="legend-item">
                <div class="legend-color" style="background: var(--danger-color);"></div>
                <span>ตำแหน่งที่ตรวจพบการล้ม — แสดงบนแผนที่ <strong><?= $falls_with_gps ?></strong> / <?= $total_falls ?> ครั้ง (เฉพาะที่มี GPS)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: var(--success-color);"></div>
                <span>ตำแหน่งปัจจุบัน (GPS Browser)</span>
            </div>
        </div>
    </div>
</div>

<script>
// ===========================
// DATA
// ===========================
const fallsData = <?= json_encode($falls) ?>;

// ===========================
// CREATE MAP
// ===========================
// ✅ สร้างแผนที่ (ใช้พิกัดยะลาเป็น default)
const map = L.map('map').setView([6.5392, 101.2804], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
}).addTo(map);

// ===========================
// ADD FALL MARKERS
// ===========================
const fallIcon = L.divIcon({
    className: 'custom-marker',
    html: '<div style="background: #ef4444; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><i class="fas fa-exclamation"></i></div>',
    iconSize: [30, 30]
});

fallsData.forEach(fall => {
    const marker = L.marker([fall.latitude, fall.longitude], {icon: fallIcon}).addTo(map);
    
    const popupContent = `
        <div style="padding: 10px; min-width: 220px;">
            <h6 style="margin: 0 0 10px 0; color: #ef4444;"><i class="fas fa-exclamation-triangle"></i> ตรวจพบการล้ม</h6>
            <p style="margin: 5px 0; font-size: 0.9rem;"><strong><i class="fas fa-clock"></i> เวลา:</strong> ${new Date(fall.created_at).toLocaleString('th-TH')}</p>
            <p style="margin: 5px 0; font-size: 0.9rem;"><strong><i class="fas fa-video"></i> อุปกรณ์:</strong> ${fall.device_id}</p>
            <p style="margin: 5px 0; font-size: 0.9rem;"><strong><i class="fas fa-map-pin"></i> ตำแหน่ง:</strong><br>${fall.latitude.toFixed(6)}, ${fall.longitude.toFixed(6)}</p>
            ${fall.image_path ? `<img src="../${fall.image_path}" style="width: 100%; max-width: 250px; margin-top: 10px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">` : ''}
            
            <div style="margin-top: 15px; display: flex; gap: 8px;">
                <button onclick="navigateToLocation(${fall.latitude}, ${fall.longitude})" 
                        style="flex: 1; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 10px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.3s ease;">
                    <i class="fas fa-directions"></i> นำทาง
                </button>
                <button onclick="copyLocation(${fall.latitude}, ${fall.longitude})" 
                        style="background: #f59e0b; color: white; border: none; padding: 10px; border-radius: 8px; cursor: pointer; font-size: 0.9rem; transition: all 0.3s ease;">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
        </div>
    `;
    
    marker.bindPopup(popupContent, { maxWidth: 300 });
});

// ===========================
// GET BROWSER GPS (2-Stage: เร็วก่อน → แม่นทีหลัง)
// ===========================
let currentMarker = null;

const greenIcon = L.icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
});

async function applyPosition(lat, lng, accuracy, label = '') {
    // อัปเดต UI
    document.getElementById('currentPosition').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    document.getElementById('currentAccuracy').textContent = `${accuracy.toFixed(0)} เมตร ${label}`;
    document.getElementById('lastUpdate').textContent = new Date().toLocaleString('th-TH');

    // ย้าย/วาง marker
    if (currentMarker) {
        currentMarker.setLatLng([lat, lng]);
        currentMarker.getPopup().setContent(`
            <div style="padding:10px;">
                <h6 style="margin:0 0 10px 0;color:#10b981;"><i class="fas fa-location-arrow"></i> ตำแหน่งปัจจุบัน ${label}</h6>
                <p style="margin:5px 0;font-size:0.9rem;"><strong>ความแม่นยำ:</strong> ${accuracy.toFixed(0)} เมตร</p>
                <p style="margin:5px 0;font-size:0.9rem;"><strong>ตำแหน่ง:</strong><br>${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
            </div>
        `);
    } else {
        currentMarker = L.marker([lat, lng], { icon: greenIcon })
            .addTo(map)
            .bindPopup(`
                <div style="padding:10px;">
                    <h6 style="margin:0 0 10px 0;color:#10b981;"><i class="fas fa-location-arrow"></i> ตำแหน่งปัจจุบัน ${label}</h6>
                    <p style="margin:5px 0;font-size:0.9rem;"><strong>ความแม่นยำ:</strong> ${accuracy.toFixed(0)} เมตร</p>
                    <p style="margin:5px 0;font-size:0.9rem;"><strong>ตำแหน่ง:</strong><br>${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
                </div>
            `)
            .openPopup();

        if (fallsData.length === 0) map.setView([lat, lng], 15);
    }

    await updateGPSConfig(lat, lng, accuracy);
}

// ===========================
// โหลดตำแหน่งล่าสุดจาก gps_config.json ทันที (ไม่ต้องรอ GPS)
// ===========================
(async () => {
    try {
        const res  = await fetch('gps_system.php?api=1&action=get');
        const data = await res.json();

        if (data.success && data.data?.latitude) {
            const { latitude: lat, longitude: lng, accuracy, updated_at, timestamp } = data.data;

            // ถ้าเป็น default (ยังไม่มี config จริง) → แสดงแต่บอกว่าเป็นค่าเริ่มต้น
            const isDefault = !!data.note;
            const ageSec    = timestamp ? Math.round(Date.now()/1000 - timestamp) : null;
            const ageStr    = !ageSec    ? '' :
                              ageSec < 60    ? `(${ageSec} วิที่แล้ว)` :
                              ageSec < 3600  ? `(${Math.round(ageSec/60)} นาทีที่แล้ว)` :
                                              `(${Math.round(ageSec/3600)} ชม.ที่แล้ว)`;

            document.getElementById('currentPosition').textContent  = isDefault
                ? 'ยังไม่มี GPS — รอ events.php ส่งตำแหน่งครั้งแรก'
                : `${parseFloat(lat).toFixed(6)}, ${parseFloat(lng).toFixed(6)}`;
            document.getElementById('currentAccuracy').textContent  = isDefault
                ? '-'
                : `${parseFloat(accuracy||0).toFixed(0)} เมตร ${ageStr}`;
            document.getElementById('lastUpdate').textContent       = updated_at ?? '-';

            if (!isDefault) {
                currentMarker = L.marker([lat, lng], { icon: greenIcon })
                    .addTo(map)
                    .bindPopup(`
                        <div style="padding:10px;">
                            <h6 style="margin:0 0 8px 0;color:#10b981;"><i class="fas fa-location-arrow"></i> ตำแหน่งล่าสุด</h6>
                            <p style="margin:5px 0;font-size:0.9rem;"><strong>ความแม่นยำ:</strong> ${parseFloat(accuracy||0).toFixed(0)} เมตร</p>
                            <p style="margin:5px 0;font-size:0.9rem;"><strong>อัปเดต:</strong> ${ageStr}</p>
                            <p style="margin:5px 0;font-size:0.9rem;"><strong>ตำแหน่ง:</strong><br>${parseFloat(lat).toFixed(6)}, ${parseFloat(lng).toFixed(6)}</p>
                        </div>
                    `)
                    .openPopup();
                console.log('📡 Loaded last GPS from config:', lat, lng);
            } else {
                console.log('ℹ️ No GPS config yet — waiting for events.php to send location');
            }
        } // end if data.success
    } catch(e) {
        document.getElementById('currentPosition').textContent = 'โหลด GPS ไม่ได้';
    }
})();

// ===========================
// Browser GPS — รันเงียบๆ เบื้องหลัง อัปเดต marker เมื่อได้ค่าแม่นขึ้น
// ===========================
if (navigator.geolocation) {
    const watchId = navigator.geolocation.watchPosition(
        async position => {
            const lat      = position.coords.latitude;
            const lng      = position.coords.longitude;
            const accuracy = position.coords.accuracy;

            // อัปเดต UI
            document.getElementById('currentPosition').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            document.getElementById('currentAccuracy').textContent = `${accuracy.toFixed(0)} เมตร${accuracy <= 50 ? ' ✓' : ''}`;
            document.getElementById('lastUpdate').textContent      = new Date().toLocaleString('th-TH');

            // หยุดเมื่อแม่นพอ
            if (accuracy <= 50) {
                navigator.geolocation.clearWatch(watchId);
                console.log('✅ GPS locked:', accuracy.toFixed(0), 'm');
            }

            // อัปเดต marker
            if (currentMarker) {
                currentMarker.setLatLng([lat, lng]);
                currentMarker.setPopupContent(`
                    <div style="padding:10px;">
                        <h6 style="margin:0 0 8px 0;color:#10b981;"><i class="fas fa-location-arrow"></i> ตำแหน่งปัจจุบัน ${accuracy <= 50 ? '✓' : ''}</h6>
                        <p style="margin:5px 0;font-size:0.9rem;"><strong>ความแม่นยำ:</strong> ${accuracy.toFixed(0)} เมตร</p>
                        <p style="margin:5px 0;font-size:0.9rem;"><strong>ตำแหน่ง:</strong><br>${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
                    </div>
                `);
            } else {
                currentMarker = L.marker([lat, lng], { icon: greenIcon })
                    .addTo(map)
                    .bindPopup(`
                        <div style="padding:10px;">
                            <h6 style="margin:0 0 8px 0;color:#10b981;"><i class="fas fa-location-arrow"></i> ตำแหน่งปัจจุบัน</h6>
                            <p style="margin:5px 0;font-size:0.9rem;"><strong>ความแม่นยำ:</strong> ${accuracy.toFixed(0)} เมตร</p>
                            <p style="margin:5px 0;font-size:0.9rem;"><strong>ตำแหน่ง:</strong><br>${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
                        </div>
                    `)
                    .openPopup();
                if (fallsData.length === 0) map.setView([lat, lng], 15);
            }

            await updateGPSConfig(lat, lng, accuracy);
        },
        err => console.warn('Browser GPS:', err.message), // ล้มเหลว → ไม่ต้อง alert เพราะมี config แล้ว
        { enableHighAccuracy: true, timeout: 30000, maximumAge: 0 }
    );
}

// ===========================
// UPDATE GPS CONFIG
// ===========================
async function updateGPSConfig(lat, lng, accuracy) {
    try {
        const response = await fetch('gps_system.php?api=1', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=update&latitude=${lat}&longitude=${lng}&accuracy=${accuracy}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('✅ GPS updated:', data.data);
        } else {
            console.error('❌ GPS update failed:', data.message);
        }
    } catch (error) {
        console.error('❌ Failed to update GPS:', error);
    }
}

// ===========================
// NAVIGATION FUNCTIONS
// ===========================
function navigateToLocation(destLat, destLng) {
    // ตรวจสอบว่ามีตำแหน่งปัจจุบันหรือไม่
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                const currentLat = position.coords.latitude;
                const currentLng = position.coords.longitude;
                
                // เปิด Google Maps พร้อมเส้นทาง
                const url = `https://www.google.com/maps/dir/?api=1&origin=${currentLat},${currentLng}&destination=${destLat},${destLng}&travelmode=driving`;
                
                window.open(url, '_blank');
            },
            error => {
                // ถ้าไม่ได้ตำแหน่งปัจจุบัน ให้เปิดแค่จุดหมาย
                const url = `https://www.google.com/maps/search/?api=1&query=${destLat},${destLng}`;
                window.open(url, '_blank');
            }
        );
    } else {
        // Browser ไม่รองรับ Geolocation
        const url = `https://www.google.com/maps/search/?api=1&query=${destLat},${destLng}`;
        window.open(url, '_blank');
    }
}

function copyLocation(lat, lng) {
    const locationText = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    
    // คัดลอกไปยัง clipboard
    navigator.clipboard.writeText(locationText).then(() => {
        // แสดงการแจ้งเตือน
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 9999;
            font-weight: 600;
            animation: slideIn 0.3s ease;
        `;
        notification.innerHTML = '<i class="fas fa-check-circle"></i> คัดลอกพิกัดแล้ว!';
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy:', err);
        alert('ไม่สามารถคัดลอกได้: ' + locationText);
    });
}

// เพิ่ม CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// ===========================
// AUTO FIT BOUNDS — ซูมแผนที่อัตโนมัติ
// ===========================
let mapCentered = false;

if (fallsData.length === 1) {
    // มี 1 จุด → ซูมไปที่จุดนั้น
    map.setView([fallsData[0].latitude, fallsData[0].longitude], 16);
    mapCentered = true;
} else if (fallsData.length > 1) {
    // มีหลายจุด → ซูมให้เห็นทุกจุด
    const bounds = L.latLngBounds(fallsData.map(f => [f.latitude, f.longitude]));
    map.fitBounds(bounds, { padding: [80, 80] });
    mapCentered = true;
}

// ✅ ถ้ายังไม่ได้ซูม และได้ GPS จาก Browser → ซูมไปที่นั่น
setTimeout(() => {
    if (!mapCentered && currentMarker) {
        const pos = currentMarker.getLatLng();
        map.setView([pos.lat, pos.lng], 15);
        console.log('🎯 Auto-zoomed to current location');
    }
}, 2000);

console.log('✅ GPS System loaded');
console.log('📍 Falls:', fallsData.length);
</script>


</body>
</html>