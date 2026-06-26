<?php
/**
 * Notification System
 * ส่ง LINE Messaging API เมื่อตรวจพบการล้ม
 */

// ===========================
// CONFIG
// ===========================
define('LINE_CHANNEL_TOKEN', 'jpAOU5kpu0Em9wAdxxY93OxRVq0fEUHse8DsQx7w2ScYY24aoUx3czTgVe9MOWCiSozSa2z+cOH8UcZ9xVfTT2UlrB5r2yG4ELwXKykWOtocEphXAJBfFyqBMnL1AWzsA0903ga7ZUJHRW1IbCz1rAdB04t89/1O/w1cDnyilFU=');
define('LINE_USER_ID',       'Ud43f63d828a47fef8bb42275a9e702b8');
define('SYSTEM_URL',         'http://localhost/web');

// ===========================
// ส่งแจ้งเตือนการล้ม (main function)
// ===========================
function notifyFallDetected($eventId, $deviceId, $latitude, $longitude, $imagePath = null) {
    $time     = date('d/m/Y H:i:s');
    $messages = [];

    // Message 1: Flex Card (ข้อมูลการล้ม) — ส่งเสมอ
    $messages[] = _buildFlexMessage($eventId, $deviceId, $time, $latitude, $longitude);

    // Message 2: รูปภาพ — ส่งได้เฉพาะถ้าเป็น HTTPS (ไม่ใช่ localhost)
    // LINE API ต้องการ URL แบบ HTTPS ที่เข้าถึงได้จากอินเตอร์เน็ต
    if ($imagePath) {
        $imageUrl = SYSTEM_URL . '/' . ltrim($imagePath, '/');
        $isPublicHttps = strpos($imageUrl, 'https://') === 0
                      && strpos($imageUrl, 'localhost') === false
                      && strpos($imageUrl, '127.0.0.1') === false;

        if ($isPublicHttps) {
            $messages[] = [
                'type'               => 'image',
                'originalContentUrl' => $imageUrl,
                'previewImageUrl'    => $imageUrl,
            ];
        }
        // ถ้าเป็น localhost → ไม่ส่งรูป แต่ Flex Card ยังส่งได้ปกติ
    }

    $sent = _lineApiPush($messages);
    return ['line_sent' => $sent];
}

// ===========================
// สร้าง Flex Message
// ===========================
function _buildFlexMessage($eventId, $deviceId, $time, $latitude, $longitude) {
    $mapUrl    = SYSTEM_URL . "/admin/gps_system.php";
    $googleUrl = ($latitude && $longitude)
        ? "https://www.google.com/maps?q={$latitude},{$longitude}"
        : null;

    return [
        'type'     => 'flex',
        'altText'  => '🚨 ตรวจพบการล้ม! Event #' . $eventId,
        'contents' => [
            'type'   => 'bubble',
            'header' => [
                'type'            => 'box',
                'layout'          => 'vertical',
                'backgroundColor' => '#FF3B30',
                'paddingAll'      => '15px',
                'contents'        => [[
                    'type'   => 'text',
                    'text'   => '🚨 ตรวจพบการล้ม!',
                    'color'  => '#FFFFFF',
                    'size'   => 'xl',
                    'weight' => 'bold'
                ]]
            ],
            'body' => [
                'type'       => 'box',
                'layout'     => 'vertical',
                'spacing'    => 'md',
                'paddingAll' => '15px',
                'contents'   => [
                    _flexRow('⏰ เวลา',     $time),
                    _flexRow('📱 อุปกรณ์',  $deviceId),
                    _flexRow('🔖 Event ID', '#' . $eventId),
                    ($latitude && $longitude)
                        ? _flexRow('📍 ตำแหน่ง', $latitude . ', ' . $longitude)
                        : _flexRow('📍 ตำแหน่ง', 'ไม่พบข้อมูล GPS'),
                ]
            ],
            'footer' => [
                'type'       => 'box',
                'layout'     => 'vertical',
                'spacing'    => 'sm',
                'paddingAll' => '12px',
                'contents'   => array_values(array_filter([
                    $googleUrl ? [
                        'type'   => 'button',
                        'style'  => 'primary',
                        'color'  => '#4285F4',
                        'height' => 'sm',
                        'action' => [
                            'type'  => 'uri',
                            'label' => '🗺️ ดูใน Google Maps',
                            'uri'   => $googleUrl
                        ]
                    ] : null,
                    [
                        'type'   => 'button',
                        'style'  => 'secondary',
                        'height' => 'sm',
                        'action' => [
                            'type'  => 'uri',
                            'label' => '📊 ดูในระบบ',
                            'uri'   => $mapUrl
                        ]
                    ]
                ]))
            ]
        ]
    ];
}

// ===========================
// Helper: แถวข้อมูลใน Flex
// ===========================
function _flexRow($label, $value) {
    return [
        'type'     => 'box',
        'layout'   => 'horizontal',
        'contents' => [
            [
                'type'  => 'text',
                'text'  => $label,
                'color' => '#888888',
                'size'  => 'sm',
                'flex'  => 3
            ],
            [
                'type'   => 'text',
                'text'   => (string)$value,
                'color'  => '#333333',
                'size'   => 'sm',
                'weight' => 'bold',
                'flex'   => 5,
                'wrap'   => true
            ]
        ]
    ];
}

// ===========================
// Helper: เรียก LINE Push API
// ===========================
function _lineApiPush(array $messages) {
    $payload = json_encode([
        'to'       => LINE_USER_ID,
        'messages' => $messages
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.line.me/v2/bot/message/push',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . LINE_CHANNEL_TOKEN,
        ],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr)          { error_log('[LINE] cURL Error: ' . $curlErr); return false; }
    if ($httpCode !== 200) { error_log('[LINE] API Error ' . $httpCode . ': ' . $result); return false; }

    return true;
}

// ===========================
// ทดสอบ  ?test=1
// ===========================
if (isset($_GET['test'])) {
    header('Content-Type: application/json');
    $result = notifyFallDetected(999, 'cam01', 6.5392, 101.2804, null);
    echo json_encode([
        'success'   => true,
        'line_sent' => $result['line_sent'],
        'message'   => 'Test notification sent!'
    ]);
    exit;
}
?>