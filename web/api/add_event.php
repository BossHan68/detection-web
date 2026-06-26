<?php
require '../config/db.php';
require 'notification.php';
header('Content-Type: application/json; charset=utf-8');

$status    = $_POST['status'] ?? $_GET['status'] ?? null;
$device_id = $_POST['device_id'] ?? $_GET['device_id'] ?? 'cam01';

if (!$status || !in_array($status, ['fall','normal'], true)) {
    http_response_code(400);
    echo json_encode(["status"=>"error", "message"=>"Invalid status"]);
    exit;
}

$image_name = null;
$video_name = null;

// รับรูปภาพ
if (!empty($_FILES['image']['name'])) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png'])) {
        $dir = __DIR__ . "/../uploads/images/{$status}/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $name = time() . "_{$status}.{$ext}";
        if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $name)) {
            $image_name = "{$status}/{$name}";
        }
    }
} elseif (!empty($_POST['image_path'])) {
    $image_name = trim($_POST['image_path']);
}

// รับวิดีโอ
if (!empty($_FILES['video']['name'])) {
    $ext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4','avi'])) {
        $dir = __DIR__ . "/../uploads/videos/{$status}/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $name = time() . "_vid.{$ext}";
        if (move_uploaded_file($_FILES['video']['tmp_name'], $dir . $name)) {
            $video_name = "{$status}/{$name}";
        }
    }
} elseif (!empty($_POST['video_path'])) {
    $video_name = trim($_POST['video_path']);
}

// GPS realtime
$lat = $lng = $acc = $ts = null;

if (isset($_POST['latitude'])) {
    $lat = floatval($_POST['latitude']);
    $lng = floatval($_POST['longitude']);
}

if (!$lat) {
    $gps_file = __DIR__ . "/../gps_config.json";
    if (file_exists($gps_file)) {
        $gps = json_decode(file_get_contents($gps_file), true);
        if ($gps && (time() - ($gps['timestamp'] ?? 0)) <= 86400) {
            $lat = $gps['latitude'] ?? null;
            $lng = $gps['longitude'] ?? null;
            $acc = $gps['accuracy'] ?? null;
            $ts  = $gps['updated_at'] ?? null;
        }
    }
}

// บันทึกลงฐานข้อมูล
$stmt = $conn->prepare(
    "INSERT INTO events (status, image_path, video_path, device_id, latitude, longitude, location_accuracy, location_timestamp, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
);
$stmt->execute([$status, $image_name, $video_name, $device_id, $lat, $lng, $acc, $ts]);
$event_id = $conn->lastInsertId();

// ✅ ส่งแจ้งเตือน LINE ถ้าเป็นการล้ม
$notification_result = null;
if ($status === 'fall') {
    $notification_result = notifyFallDetected(
        $event_id,
        $device_id,
        $lat,
        $lng,
        $image_name ? "uploads/images/{$image_name}" : null
    );
}

echo json_encode([
    "status"       => "success",
    "id"           => $event_id,
    "image_path"   => $image_name,
    "video_path"   => $video_name,
    "latitude"     => $lat,
    "longitude"    => $lng,
    "notification" => $notification_result
]);
?>