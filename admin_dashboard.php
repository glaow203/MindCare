<?php
session_start();
require_once __DIR__ . '/supabase_config.php';

// ตรวจสอบว่าผ่านหน้าเข้าสู่ระบบหรือไม่
if (
    empty($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    !defined('ADMIN_EMAIL') ||
    strtolower((string)($_SESSION['admin_email'] ?? '')) !== strtolower((string)ADMIN_EMAIL)
) {
    header('Location: admin.php');
    exit;
}

$email = htmlspecialchars(
    (string)($_SESSION['admin_email'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ด ADMIN | MindCare</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Tahoma, sans-serif;
            background: #f0f5fa;
            color: #17264b;
        }

        header {
            background: #111d43;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        header h1 {
            margin: 0;
            font-size: 24px;
        }

        header p {
            margin: 6px 0 0;
            color: #dce6f8;
        }

        .logout-button {
            background: #ffffff;
            color: #111d43;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            cursor: pointer;
            font-size: 15px;
        }

        main {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .welcome {
            background: white;
            padding: 24px;
            border-radius: 14px;
            margin-bottom: 24px;
            box-shadow: 0 3px 12px #0000000a;
        }

        .welcome h2 {
            margin-top: 0;
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }

        .menu-card {
            background: white;
            padding: 24px;
            border-radius: 14px;
            border: 1px solid #e1e9f2;
        }

        .menu-card h3 {
            margin-top: 0;
        }

        .menu-card p {
            line-height: 1.6;
            color: #53627c;
        }

        .manage-button {
            display: inline-block;
            margin-top: 8px;
            padding: 10px 15px;
            background: #234e9b;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }

        .manage-button:hover {
            background: #173b7a;
        }

        .note {
            margin-top: 24px;
            color: #53627c;
            font-size: 14px;
            line-height: 1.7;
        }

        @media (max-width: 600px) {
            header {
                padding: 18px 20px;
            }

            main {
                margin-top: 20px;
                padding: 0 14px;
            }

            .welcome,
            .menu-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <header>
        <div>
            <h1>MindCare ADMIN</h1>
            <p>ระบบจัดการเนื้อหาเว็บไซต์</p>
        </div>

        <form method="post" action="admin_logout.php">
            <button class="logout-button" type="submit">ออกจากระบบ</button>
        </form>
    </header>

    <main>
        <section class="welcome">
            <h2>ยินดีต้อนรับผู้ดูแลระบบ</h2>
            <p>เข้าสู่ระบบด้วยอีเมล: <strong><?= $email ?></strong></p>
            <p>หน้านี้เป็นจุดเริ่มต้นสำหรับจัดการข้อมูลที่แสดงบนเว็บไซต์ MindCare</p>
        </section>

        <section class="menu">
            <div class="menu-card">
                <h3>รู้จักภาวะซึมเศร้า</h3>
                <p>จัดการข้อมูลความรู้เกี่ยวกับภาวะซึมเศร้า</p>
                <a class="manage-button" href="admin_tips.php?group=depression_intro">
                    จัดการข้อมูล →
                </a>
            </div>

            <div class="menu-card">
                <h3>สังเกตอาการและสาเหตุ</h3>
                <p>
                    จัดการข้อมูลอาการและสาเหตุของภาวะซึมเศร้า
                    สามารถเพิ่ม แก้ไข ลบ และเผยแพร่ข้อมูลได้
                    โดยแยกหมวดอาการและสาเหตุภายในหน้าเดียวกัน
                </p>
                <a class="manage-button" href="admin_symptoms.php">
                    จัดการอาการสาเหตุ →
                </a>
            </div>

            <div class="menu-card">
                <h3>การรักษาและการดูแลตัวเองเบื้องต้น</h3>
                <p>
                    จัดการข้อมูลแนวทางการรักษาภาวะซึมเศร้า
                    และการดูแลตัวเองเบื้องต้น สามารถเพิ่ม แก้ไข ลบ
                    และเผยแพร่ข้อมูลได้
                </p>
                <a class="manage-button" href="admin_treatment.php?group=treatment">
                    จัดการข้อมูลการรักษา →
                </a>
            </div>

            <div class="menu-card">
                <h3>ข่าวสารและบทความ</h3>
                <p>
                    จัดการข่าวสารและบทความของเว็บไซต์
                    สามารถเพิ่ม แก้ไข ลบ และเผยแพร่ข่าวสารได้
                </p>
                <a class="manage-button" href="admin_news.php">
                    จัดการข่าวสาร →
                </a>
            </div>

            <div class="menu-card">
                <h3>เกร็ดความรู้</h3>
                <p>เพิ่ม แก้ไข ลบ และเผยแพร่เกร็ดความรู้บนหน้าแรก</p>
                <a class="manage-button" href="admin_tips.php">
                    จัดการเกร็ดความรู้ →
                </a>
            </div>

            <div class="menu-card">
                <h3>แบบประเมินสุขภาพใจ</h3>
                <p>
                    จัดการรายการแบบประเมิน เพิ่มหรือแก้ไขข้อมูลแบบประเมิน
                    และเข้าไปจัดการคำถาม ตัวเลือก คะแนน และเกณฑ์แปลผล
                </p>
                <a class="manage-button" href="admin_assessments.php">
                    จัดการแบบประเมิน →
                </a>
            </div>
        </section>

        <p class="note">
            หมายเหตุ: เลือกเมนูที่ต้องการเพื่อเพิ่ม แก้ไข ลบ หรือเผยแพร่ข้อมูล
            การจัดการคำถามและเกณฑ์คะแนนของแบบประเมินทำได้จากหน้า “จัดการแบบประเมิน”
        </p>
    </main>
</body>
</html>
