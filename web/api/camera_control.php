<?php
header('Content-Type: application/json');

// ===========================
// CONFIG - ต้องตรงกับ Python!
// ===========================
$control_file = "D:/detection-web/web/camera_control.txt";
$status_file = "D:/detection-web/web/camera_status.txt";
$pid_file = "D:/detection-web/web/camera_pid.txt";

// สร้างโฟลเดอร์ถ้ายังไม่มี
$dir = dirname($control_file);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

// ===========================
// HANDLE REQUEST
// ===========================
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'start':
        // เปิดกล้อง
        $command = json_encode(['action' => 'start', 'timestamp' => time()]);
        
        if (file_put_contents($control_file, $command) !== false) {
            // รอให้ Python อ่านและอัพเดทสถานะ (1 วินาที)
            sleep(1);
            
            echo json_encode([
                'success' => true,
                'message' => 'Camera start command sent',
                'status' => 'running'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to write control file',
                'status' => 'error'
            ]);
        }
        break;
    
    case 'stop':
        // ปิดกล้อง
        $command = json_encode(['action' => 'stop', 'timestamp' => time()]);
        
        if (file_put_contents($control_file, $command) !== false) {
            // รอให้ Python อ่านและอัพเดทสถานะ (1 วินาที)
            sleep(1);
            
            echo json_encode([
                'success' => true,
                'message' => 'Camera stop command sent',
                'status' => 'stopped'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to write control file',
                'status' => 'error'
            ]);
        }
        break;
    
    case 'status':
        // ตรวจสอบสถานะ
        if (file_exists($status_file)) {
            $status = trim(file_get_contents($status_file));
            
            // อ่าน PID ถ้ามี
            $pid = null;
            if (file_exists($pid_file)) {
                $pid = trim(file_get_contents($pid_file));
            }
            
            echo json_encode([
                'success' => true,
                'status' => $status,
                'pid' => $pid
            ]);
        } else {
            // ถ้ายังไม่มีไฟล์ status แสดงว่าปิดอยู่
            echo json_encode([
                'success' => true,
                'status' => 'stopped',
                'pid' => null
            ]);
        }
        break;
    
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action. Use: start, stop, or status',
            'status' => 'error'
        ]);
        break;
}
?>