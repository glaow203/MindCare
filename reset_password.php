
<?php
session_start();
require_once __DIR__ . '/supabase_config.php';

$message = '';
$messageType = '';
$accessToken = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accessToken = trim($_POST['access_token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($accessToken === '') {
        $message = 'ไม่พบโทเคนรีเซ็ตรหัสผ่าน กรุณาเปิดลิงก์จากอีเมลฉบับใหม่';
    } elseif (strlen($password) < 8) {
        $message = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    } elseif ($password !== $confirmPassword) {
        $message = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
    } else {
        $payload = json_encode([
            'password' => $password
        ]);

        $ch = curl_init(
            rtrim(SUPABASE_URL, '/') . '/auth/v1/user'
        );

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'apikey: ' . trim(SUPABASE_PUBLISHABLE_KEY),
                'Authorization: Bearer ' . $accessToken
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 20
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $message = 'เชื่อมต่อ Supabase ไม่สำเร็จ กรุณาลองใหม่';
            error_log('Password reset connection error: ' . $error);
        } elseif ($status >= 200 && $status < 300) {
            $message = 'เปลี่ยนรหัสผ่านสำเร็จแล้ว กลับไปเข้าสู่ระบบได้เลย';
            $messageType = 'success';
            $accessToken = '';
        } else {
            $data = json_decode($response, true);
            $message = $data['msg']
                ?? $data['message']
                ?? 'เปลี่ยนรหัสผ่านไม่สำเร็จ ลิงก์อาจหมดอายุแล้ว';
        }
    }
} else {
    // Supabase อาจส่ง recovery token มาใน URL fragment
    // JavaScript จะอ่าน fragment แล้วส่ง token เข้ามาในฟอร์ม
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ตั้งรหัสผ่านใหม่ | MindCare</title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: Arial, Tahoma, sans-serif;
    background: #f4f8fc;
    color: #1b2d5a;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.card {
    width: 100%;
    max-width: 430px;
    background: #fff;
    padding: 30px;
    border-radius: 18px;
    box-shadow: 0 8px 30px rgba(27,45,90,.10);
}
h1 { font-size: 25px; margin-top: 0; }
p { line-height: 1.6; }
label { display: block; margin: 16px 0 7px; }
input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccd6e5;
    border-radius: 8px;
    font-size: 16px;
}
button {
    width: 100%;
    margin-top: 20px;
    padding: 13px;
    border: 0;
    border-radius: 8px;
    background: #3157a4;
    color: white;
    font-size: 16px;
    cursor: pointer;
}
button:disabled { opacity: .5; cursor: not-allowed; }
.message { margin: 14px 0; color: #b42318; overflow-wrap: anywhere; }
.success { color: #167647; }
.hidden { display: none; }
a { color: #3157a4; }
</style>
</head>
<body>
<main class="card">
    <h1>ตั้งรหัสผ่านใหม่</h1>
    <p>กำหนดรหัสผ่านใหม่สำหรับบัญชี MindCare ของคุณ</p>

    <div id="message" class="message <?= $messageType === 'success' ? 'success' : '' ?>">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>

    <form id="resetForm" method="post">
        <input type="hidden" name="access_token" id="access_token"
               value="<?= htmlspecialchars($accessToken, ENT_QUOTES, 'UTF-8') ?>">

        <div id="passwordFields" class="<?= $messageType === 'success' ? 'hidden' : '' ?>">
            <label for="password">รหัสผ่านใหม่</label>
            <input type="password" id="password" name="password"
                   minlength="8" autocomplete="new-password" required>

            <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
            <input type="password" id="confirm_password"
                   name="confirm_password" minlength="8"
                   autocomplete="new-password" required>

            <button type="submit" id="submitButton">บันทึกรหัสผ่านใหม่</button>
        </div>
    </form>

    <p><a href="admin.php">กลับไปหน้าเข้าสู่ระบบ</a></p>
</main>

<script>
const messageBox = document.getElementById('message');
const tokenInput = document.getElementById('access_token');
const form = document.getElementById('resetForm');
const passwordFields = document.getElementById('passwordFields');

// Recovery email ของ Supabase ส่ง token มาใน URL fragment
// ซึ่งจะไม่ถูกส่งไปยัง PHP โดยอัตโนมัติ
const params = new URLSearchParams(window.location.hash.substring(1));
const token = params.get('access_token');
const type = params.get('type');
const error = params.get('error_description') || params.get('error');

if (error) {
    messageBox.textContent = 'ลิงก์รีเซ็ตไม่สำเร็จหรือหมดอายุแล้ว กรุณาขอลิงก์ใหม่';
} else if (token && type === 'recovery') {
    tokenInput.value = token;
    messageBox.textContent = 'พบลิงก์รีเซ็ตแล้ว กรุณาตั้งรหัสผ่านใหม่';
} else if (!tokenInput.value && <?= json_encode($messageType !== 'success') ?>) {
    messageBox.textContent =
        'ยังไม่พบลิงก์รีเซ็ตรหัสผ่าน กรุณาเปิดลิงก์จากอีเมล Supabase';
}

form.addEventListener('submit', function (event) {
    if (!tokenInput.value) {
        event.preventDefault();
        messageBox.textContent =
            'ไม่พบโทเคนรีเซ็ต กรุณาเปิดลิงก์จากอีเมลฉบับใหม่';
    }
});
</script>
</body>
</html>
