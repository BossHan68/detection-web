<?php
/**
 * 📸 Capture Image API (Fixed)
 * เขียน capture_flag.txt แบบ atomic เพื่อให้ Python อ่านได้ชัวร์
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ===========================
// ✅ PATH ให้ตรงกับ Python 100%
// ===========================
$flag_file = 'D:/detection-web/web/capture_flag.txt';
$tmp_file  = 'D:/detection-web/web/capture_flag.tmp';

$timestamp = time();
$status    = isset($_POST['status']) ? $_POST['status'] : 'normal';
$device_id = isset($_POST['device_id']) ? $_POST['device_id'] : 'cam01';

$command = [
  'action'    => 'capture',
  'timestamp' => $timestamp,
  'device_id' => $device_id,
  'status'    => $status
];

try {
  $dir = dirname($flag_file);
  if (!is_dir($dir)) {
    if (!mkdir($dir, 0777, true)) {
      throw new Exception("ไม่สามารถสร้างโฟลเดอร์: $dir");
    }
  }

  // ✅ เขียนแบบ atomic: เขียน tmp ก่อน แล้ว rename เป็น txt
  $json_data = json_encode($command, JSON_UNESCAPED_SLASHES);
  if ($json_data === false) {
    throw new Exception("json_encode failed");
  }

  $bytes = file_put_contents($tmp_file, $json_data, LOCK_EX);
  if ($bytes === false) {
    throw new Exception("ไม่สามารถเขียนไฟล์ tmp ได้");
  }

  // rename จะทำให้ Python เห็นไฟล์ที่ “เขียนเสร็จแล้ว” เท่านั้น
  if (!rename($tmp_file, $flag_file)) {
    throw new Exception("rename tmp -> flag failed");
  }

  @chmod($flag_file, 0666);

  echo json_encode([
    'success' => true,
    'message' => 'ส่งคำสั่ง capture แล้ว',
    'flag_file' => $flag_file,
    'timestamp' => $timestamp,
    'status' => $status,
    'device_id' => $device_id,
    'bytes_written' => $bytes
  ]);

} catch (Exception $e) {
  error_log("❌ capture api error: " . $e->getMessage());

  echo json_encode([
    'success' => false,
    'message' => $e->getMessage(),
    'flag_file' => $flag_file
  ]);
}
