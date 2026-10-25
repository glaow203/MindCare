<?php
/**
 * MindCare — Gemini API connectivity test (local development only)
 * Path: mindcare/api/test_gemini.php
 * Config: mindcare/config/gemini.php returns ['api_key' => '...', 'model' => '...']
 * DELETE this file after testing. Do not deploy it to a public server.
 */
header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// Prevent access from other computers; this is a localhost development test.
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit("FORBIDDEN: ทดสอบได้จากเครื่อง localhost เท่านั้น\n");
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    exit("ERROR: PHP cURL ยังไม่เปิดใช้งาน\n");
}

$configFile = __DIR__ . '/../config/gemini.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit("ERROR: ไม่พบไฟล์ config/gemini.php\n");
}

$config = require $configFile;
if (!is_array($config)) {
    http_response_code(500);
    exit("ERROR: config/gemini.php ต้อง return ค่าเป็น array\n");
}

$key = trim((string)($config['api_key'] ?? ''));
$model = trim((string)($config['model'] ?? 'gemini-2.5-flash'));
if ($key === '' || $key === 'ใส่_GEMINI_API_KEY_ตรงนี้') {
    http_response_code(500);
    exit("ERROR: ยังไม่ได้ใส่ Gemini API key ใน config/gemini.php\n");
}
if (!preg_match('/^[a-zA-Z0-9._-]+$/', $model)) {
    http_response_code(500);
    exit("ERROR: ชื่อโมเดลไม่ถูกต้อง\n");
}

$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
$payload = [
    'contents' => [[
        'role' => 'user',
        'parts' => [['text' => 'ตอบเป็นภาษาไทยสั้น ๆ ว่า สวัสดีค่ะ ฉันชื่อเมย์ ผู้ช่วย MindCare']]
    ]],
    'generationConfig' => ['maxOutputTokens' => 256]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 45,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $key
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo "ERROR: เชื่อมต่อ Gemini ไม่สำเร็จ\n";
    echo "รายละเอียดเครือข่าย: " . $curlError . "\n";
    exit;
}

$data = json_decode($response, true);
echo "Model: {$model}\n";
echo "HTTP Status: {$status}\n";

if ($status < 200 || $status >= 300) {
    http_response_code(502);
    $errorCode = $data['error']['status'] ?? 'UNKNOWN';
    echo "Gemini API Error: {$errorCode}\n";
    // Do not print raw error responses: they can contain sensitive values.
    if ($status === 400) echo "คำแนะนำ: ตรวจชื่อโมเดลและรูปแบบคำขอ\n";
    elseif ($status === 401 || $status === 403) echo "คำแนะนำ: ตรวจสิทธิ์และความถูกต้องของ API key\n";
    elseif ($status === 404) echo "คำแนะนำ: โมเดลนี้อาจไม่มีให้ใช้ใน API/บัญชีของคุณ\n";
    elseif ($status === 429) echo "คำแนะนำ: โควตาการใช้งานเต็มหรือเกินขีดจำกัด\n";
    else echo "คำแนะนำ: ตรวจสถานะบริการ Gemini และการตั้งค่าโปรเจกต์\n";
    exit;
}

$reply = '';
foreach (($data['candidates'][0]['content']['parts'] ?? []) as $part) {
    if (isset($part['text']) && is_string($part['text'])) {
        $reply .= $part['text'];
    }
}

if (trim($reply) === '') {
    echo "เชื่อมต่อ API สำเร็จ แต่ไม่พบข้อความตอบกลับ กรุณาตรวจสอบ generationConfig และสถานะ candidate\n";
} else {
    echo "ผลการเชื่อมต่อ: สำเร็จ\n";
    echo "เมย์ตอบ: " . trim($reply) . "\n";
}
