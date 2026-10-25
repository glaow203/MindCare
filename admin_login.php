
<?php
session_start();
require_once __DIR__ . '/supabase_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $password === ''
) {
    exit('กรุณากรอกอีเมลและรหัสผ่านให้ถูกต้อง');
}

$adminEmail = strtolower(trim(ADMIN_EMAIL));

if (
    $email !== $adminEmail ||
    !defined('ADMIN_PASSWORD_HASH') ||
    !password_verify($password, ADMIN_PASSWORD_HASH)
) {
    exit('อีเมลหรือรหัสผ่านไม่ถูกต้อง');
}

session_regenerate_id(true);

$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_email'] = $adminEmail;

// ไม่ใช้ Supabase Auth access token แล้ว
unset($_SESSION['admin_access_token']);

header('Location: admin_dashboard.php');
exit;
