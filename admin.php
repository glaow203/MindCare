
<?php
session_start();

// ถ้าล็อกอินแล้ว ให้ไปหน้าจัดการเนื้อหา

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ ADMIN | MindCare</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, sans-serif;
            background: #f0f7ff;
            color: #17264b;
        }

        .login-box {
            width: 90%;
            max-width: 400px;
            padding: 32px;
            background: white;
            border-radius: 18px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }

        h1 {
            text-align: center;
            margin-top: 0;
        }

        p {
            text-align: center;
            color: #64748b;
        }

        label {
            display: block;
            margin: 18px 0 8px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 16px;
        }

        button {
            width: 100%;
            margin-top: 22px;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #173b65;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .note {
            margin-top: 16px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>MindCare</h1>
        <p>ระบบจัดการเว็บไซต์สำหรับผู้ดูแล</p>

        <form method="post" action="admin_login.php">
            <label for="email">อีเมลผู้ดูแลระบบ</label>
            <input type="email" id="email" name="email"
                   placeholder="กรอกอีเมล" required>

            <label for="password">รหัสผ่าน</label>
            <input type="password" id="password" name="password"
                   placeholder="กรอกรหัสผ่าน" required>

            <button type="submit">เข้าสู่ระบบ</button>
        </form>

        <p class="note">สำหรับผู้ดูแลระบบ MindCare เท่านั้น</p>
    </div>
</body>
</html>
