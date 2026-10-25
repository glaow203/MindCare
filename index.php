
<?php
// ========================================
// MindCare | หน้าแรก
// ========================================

require_once __DIR__ . '/supabase_config.php';

/**
 * ป้องกัน HTML ที่แสดงผลจากฐานข้อมูล
 */
if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/**
 * ดึงเกร็ดความรู้จาก Supabase
 */
function getMindCareTips()
{
    $tips = [];

    // ตรวจสอบการตั้งค่า Supabase
    if (
        !defined('SUPABASE_URL') ||
        !defined('SUPABASE_SECRET_KEY') ||
        !function_exists('curl_init')
    ) {
        error_log('MindCare: ตรวจสอบการตั้งค่า Supabase หรือ cURL');
        return $tips;
    }

    $supabaseUrl = trim(SUPABASE_URL);
    $secretKey = trim(SUPABASE_SECRET_KEY);

    if ($supabaseUrl === '' || $secretKey === '') {
        error_log('MindCare: ไม่พบค่า Supabase URL หรือ Secret Key');
        return $tips;
    }

    // ดึงเฉพาะเกร็ดความรู้ที่เผยแพร่แล้ว
    $url = rtrim($supabaseUrl, '/')
        . '/rest/v1/knowledge_tips'
        . '?select=id,title,content,content_group'
        . '&is_published=eq.true'
        . '&content_group=eq.tips'
        . '&order=id.asc';

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $secretKey,
            'Authorization: Bearer ' . $secretKey,
            'Accept: application/json'
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        error_log('MindCare Supabase error: ' . curl_error($ch));
        curl_close($ch);
        return $tips;
    }

    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        error_log('MindCare Supabase HTTP status: ' . $status);
        return $tips;
    }

    $result = json_decode($response, true);

    if (is_array($result)) {
        $tips = $result;
    }

    return $tips;
}

$tips = getMindCareTips();

function getDepressionIntro()
{
    $items = [];

    if (
        !defined('SUPABASE_URL') ||
        !defined('SUPABASE_SECRET_KEY') ||
        !function_exists('curl_init')
    ) {
        return $items;
    }

    $key = trim(SUPABASE_SECRET_KEY);

    if ($key === '') {
        return $items;
    }

    $url = rtrim(SUPABASE_URL, '/')
        . '/rest/v1/knowledge_tips'
        . '?select=id,title,content'
        . '&is_published=eq.true'
        . '&content_group=eq.depression_intro'
        . '&order=id.asc';

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Accept: application/json'
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        error_log('MindCare intro error: ' . curl_error($ch));
        curl_close($ch);
        return $items;
    }

    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        error_log('MindCare intro HTTP status: ' . $status);
        return $items;
    }

    $data = json_decode($response, true);

    return is_array($data) ? $data : [];
}

$introItems = getDepressionIntro();

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MindCare | ดูแลสุขภาพใจ</title>

    <meta
        name="description"
        content="MindCare เว็บไซต์ให้ความรู้เกี่ยวกับภาวะซึมเศร้า การสังเกตอาการ แนวทางการรักษาและการดูแลตัวเองเบื้องต้น"
    >

    <style>
        /* ========================================
           1. ตั้งค่าพื้นฐาน
        ======================================== */

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: Tahoma, Arial, sans-serif;
            color: #17264b;
            background: #ffffff;
        }

        img {
            max-width: 100%;
        }

        a {
            transition: color 0.2s, background 0.2s;
        }

        /* ========================================
           2. แถบสายด่วนด้านบน
        ======================================== */

        .top-bar {
            min-height: 46px;
            background: #111d43;
            color: #ffffff;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 10px 4%;
            font-size: 14px;
        }

        .top-bar a {
            color: #ffffff;
            text-decoration: none;
            font-weight: bold;
        }

        .top-bar a:hover {
            color: #bfdbfe;
        }

        /* ========================================
           3. Header และโลโก้
        ======================================== */

        .header {
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 12px 4%;
            background: #ffffff;
            border-bottom: 1px solid #dce3ed;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 50%;
            background: transparent;
            flex-shrink: 0;
        }

        .brand-text span {
            display: block;
            color: #111d43;
            font-size: 27px;
            font-weight: bold;
        }

        .brand-text small {
            display: block;
            margin-top: 4px;
            color: #657187;
            font-size: 11px;
            white-space: nowrap;
        }

        /* ========================================
           4. เมนูนำทางแนวนอน
        ======================================== */

        .nav {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 4px;
        }

        .nav a {
            color: #17264b;
            text-decoration: none;
            font-size: 14px;
            padding: 12px 9px;
            border-radius: 8px;
            white-space: nowrap;
        }

        .nav a:hover,
        .nav a:focus-visible {
            background: #e6f2ef;
            color: #087c69;
        }

        /* ========================================
           5. ส่วน Hero หน้าแรก
        ======================================== */

        .hero {
            min-height: 480px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
            padding: 65px 7%;
            background: linear-gradient(
                135deg,
                #f0f7ff,
                #e6f4f1
            );
        }

        .hero-content {
            flex: 1;
            max-width: 600px;
        }

        .hero-tag {
            display: inline-block;
            background: #dcefe9;
            color: #267565;
            padding: 9px 16px;
            border-radius: 30px;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .hero h1 {
            font-size: 42px;
            line-height: 1.5;
            color: #173b65;
            margin: 0 0 18px;
        }

        .hero h1 span {
            color: #318b88;
        }

        .hero-content > p {
            color: #586981;
            font-size: 17px;
            line-height: 1.9;
            margin: 0;
        }

        .hero-visual {
            flex: 1;
            width: 100%;
            max-width: 430px;
            min-width: 0;
            padding: 15px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 8px 25px rgba(30, 70, 120, 0.10);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .hero-image {
            width: 100%;
            height: 280px;
            object-fit: contain;
            display: block;
            border-radius: 16px;
        }

        .hero-caption {
            width: 100%;
            text-align: center;
            padding: 5px 0 10px;
        }

        .hero-caption h2 {
            font-size: 22px;
            color: #173f78;
            margin: 0 0 8px;
        }

        .hero-caption p {
            font-size: 15px;
            color: #64748b;
            margin: 0;
            line-height: 1.7;
        }

        /* ========================================
           6. ส่วนแนะนำภาวะซึมเศร้า
        ======================================== */

        .intro-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 45px 30px;
        }

        .section-title {
            color: #173b65;
            font-size: 30px;
            line-height: 1.5;
            text-align: left;
            margin: 0 0 24px;
            padding-left: 14px;
            border-left: 5px solid #318b88;
        }

        .section-description {
            color: #43546a;
            font-size: 16px;
            line-height: 2;
            max-width: 1100px;
            margin: 0 0 20px;
        }

        /* ========================================
           7. การ์ดเกร็ดความรู้
        ======================================== */

        .knowledge-heading {
            text-align: center;
            color: #173b65;
            font-size: 24px;
            line-height: 1.6;
            margin: 45px 0 22px;
        }

        .knowledge-grid {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: stretch;
            gap: 20px;
        }

        
.knowledge-card {
    min-width: 0;
    height: 100%;
    background: #f0f7ff;
    border: 1px solid #dce8f3;
    border-top: 4px solid #318b88;
    border-radius: 14px;
    padding: 20px;
    text-align: left;
    box-shadow: 0 5px 16px rgba(23, 59, 101, 0.06);
    display: flex;
    flex-direction: column;
}

.knowledge-card h3 {
    color: #216b63;
    font-size: 17px;
    line-height: 1.5;
    margin: 0 0 10px;
    overflow-wrap: anywhere;

    /* จำกัดความสูงหัวข้อ */
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.knowledge-card p {
    color: #43546a;
    font-size: 14px;
    line-height: 1.7;
    margin: 0 0 16px;
    overflow-wrap: anywhere;

    /* แสดงเนื้อหาไม่เกิน 4 บรรทัด */
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.knowledge-link {
    display: block;
    text-align: right;
    margin-top: auto;
    color: #27847d;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
    padding-top: 8px;
}

.knowledge-link:hover {
    color: #173b65;
    text-decoration: underline;
}


        .knowledge-link {
            display: block;
            text-align: right;
            margin-top: auto;
            color: #27847d;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .knowledge-link:hover {
            color: #173b65;
            text-decoration: underline;
        }

        .empty-tips {
            grid-column: 1 / -1;
            padding: 20px;
            border: 1px dashed #b9cbd9;
            border-radius: 12px;
            color: #586981;
            background: #f8fbff;
            text-align: center;
            line-height: 1.8;
        }

        /* ========================================
           8. ปุ่มแชทบอท
        ======================================== */

        .chat-button {
            position: fixed;
            right: 25px;
            bottom: 25px;
            z-index: 1000;
            border: none;
            border-radius: 50px;
            padding: 15px 22px;
            background: #173b65;
            color: #ffffff;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.18);
        }

        .chat-button:hover {
            background: #27847d;
        }

        .chat-button:focus-visible {
            outline: 3px solid #8bd3c7;
            outline-offset: 3px;
        }

        /* ========================================
           9. Footer
        ======================================== */

        .footer {
            background: #102d50;
            color: #ffffff;
            margin-top: 60px;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 45px 30px;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1.2fr;
            gap: 40px;
        }

        .footer h2 {
            font-size: 26px;
            margin: 0 0 12px;
        }

        .footer h3 {
            font-size: 18px;
            margin: 0 0 18px;
            color: #bfdbfe;
        }

        .footer p {
            color: #d5e2f0;
            font-size: 14px;
            line-height: 1.8;
        }

        .footer a {
            display: block;
            color: #d5e2f0;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .footer a:hover {
            color: #93c5fd;
        }

        .footer-bottom {
            background: #0b223d;
            text-align: center;
            padding: 12px 20px;
        }

        .footer-bottom p {
            margin: 0;
            font-size: 13px;
        }

        /* ========================================
           10. Responsive สำหรับแท็บเล็ต
        ======================================== */

        @media (max-width: 1100px) {
            .header {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .nav {
                justify-content: flex-start;
                width: 100%;
            }

            .hero {
                padding-left: 5%;
                padding-right: 5%;
                gap: 25px;
            }

            .hero h1 {
                font-size: 36px;
            }
        }

        @media (max-width: 900px) {
            .knowledge-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .footer-container {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .footer-brand {
                grid-column: 1 / -1;
            }
        }

        /* ========================================
           11. Responsive สำหรับมือถือ
        ======================================== */

        @media (max-width: 768px) {
            .hero {
                flex-direction: column;
                align-items: stretch;
                padding: 45px 6%;
                gap: 30px;
            }

            .hero-content {
                max-width: none;
            }

            .hero-visual {
                max-width: 100%;
            }

            .hero-image {
                height: 240px;
            }

            .footer-container {
                grid-template-columns: 1fr;
                gap: 25px;
                padding: 35px 22px;
            }

            .footer-brand {
                grid-column: auto;
            }
        }

        @media (max-width: 600px) {
            .top-bar {
                justify-content: center;
                flex-wrap: wrap;
                text-align: center;
                font-size: 12px;
                gap: 3px;
            }

            .header {
                padding: 12px 5%;
            }

            .logo-icon {
                width: 62px;
                height: 62px;
            }

            .brand-text span {
                font-size: 23px;
            }

            .brand-text small {
                font-size: 10px;
                white-space: normal;
            }

            .nav {
                gap: 3px;
            }

            .nav a {
                font-size: 13px;
                padding: 10px 8px;
                white-space: normal;
            }

            .hero {
                padding: 35px 20px;
            }

            .hero h1 {
                font-size: 30px;
                line-height: 1.5;
            }

            .hero-content > p {
                font-size: 15px;
            }

            .hero-image {
                height: 210px;
            }

            .intro-section {
                padding: 35px 20px;
            }

            .section-title {
                font-size: 25px;
            }

            .section-description {
                font-size: 15px;
                line-height: 1.9;
            }

            .knowledge-heading {
                font-size: 21px;
            }

            .knowledge-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .chat-button {
                right: 15px;
                bottom: 15px;
                padding: 13px 17px;
                font-size: 14px;
            }
        }
    </style>
</head>

<body>

    <!-- ========================================
         แถบสายด่วนสุขภาพจิต
    ======================================== -->

    <div class="top-bar">
        <a href="tel:1323">☎ สายด่วนสุขภาพจิต 1323</a>
        <span>&nbsp; | &nbsp; โทรฟรีตลอด 24 ชั่วโมง</span>
    </div>

    <!-- ========================================
         Header และเมนูนำทาง
    ======================================== -->

    <header class="header">
        <a href="index.php" class="brand" aria-label="MindCare หน้าแรก">

            <img
                src="https://cdn.discordapp.com/attachments/1280536102024904777/1562841883238207519/Gemini_Generated_Image_lwbljvlwbljvlwbl-photoaidcom-cropped.jpg?ex=6adcc6ed&is=6adb756d&hm=becd715ca32e74f6d811b9be0753a6d1c75e4dbff459949343e5d8c6f84c9272&"
                alt="โลโก้ MindCare"
                class="logo-icon"
            >

            <div class="brand-text">
                <span>MindCare</span>
                <small>เว็บไซต์ให้ความรู้เกี่ยวกับภาวะซึมเศร้า</small>
            </div>
        </a>

        <nav class="nav" aria-label="เมนูหลัก">
            <a href="index.php">หน้าแรก</a>
            <a href="symptoms.php">อาการและสาเหตุของโรคซึมเศร้า</a>
            <a href="treatment.php">การรักษาและการดูแลตัวเองเบื้องต้น</a>
            <a href="news.php">ข่าวสารและบทความ</a>
            <a href="assessment.php">แบบประเมินสุขภาพใจ</a>

            <a
                href="https://www.google.com/maps/search/?api=1&amp;query=คลินิกจิตเวชใกล้ฉัน"
                target="_blank"
                rel="noopener noreferrer"
            >
                ค้นหาคลินิกใกล้ฉัน
            </a>
        </nav>
    </header>

    <!-- ========================================
         เนื้อหาหลัก
    ======================================== -->

    <main id="home">

        <!-- หน้าแรก Hero -->

        <section class="hero">
            <div class="hero-content">

                <span class="hero-tag">
                    พื้นที่แห่งความเข้าใจและใส่ใจสุขภาพใจ
                </span>

                <h1>
                    เพราะสุขภาพใจ<br>
                    <span>สำคัญไม่แพ้สุขภาพกาย</span>
                </h1>

                <p>
                    ทำความเข้าใจภาวะซึมเศร้า เรียนรู้การสังเกตอาการ
                    และค้นหาข้อมูลเกี่ยวกับแนวทางการดูแลสุขภาพใจ
                    เพื่อให้คุณเข้าใจตัวเองและคนรอบข้างมากขึ้น
                </p>

            </div>

            <div class="hero-visual">

                <img
                    src="https://cdn.discordapp.com/attachments/1280536102024904777/1562848378382057542/9ce8fd35-7b00-4f26-88f7-4a4c29d44e47.jpg?ex=6adb7b79&amp;is=6ada29f9&amp;hm=e25871dc6db8d13914033d0db51780ac3e992600a93542713a4c5b851ed3f87d&amp;"
                    alt="ภาพประกอบการดูแลสุขภาพใจ"
                    class="hero-image"
                >

                <div class="hero-caption">
                    <h2>ดูแลใจ เริ่มได้ที่ตัวเรา</h2>
                    <p>รับฟัง เข้าใจ และไม่ตัดสิน</p>
                </div>

            </div>
        </section>

        <!-- รู้จักภาวะซึมเศร้า -->

        <section class="intro-section" id="about">

            


<?php if (!empty($introItems)): ?>

    <?php foreach ($introItems as $item): ?>

        <?php if (!empty($item['title'])): ?>
            <h3><?= h($item['title']) ?></h3>
        <?php endif; ?>

        <?php if (!empty($item['content'])): ?>
            <p class="section-description">
                <?= nl2br(h($item['content'])) ?>
            </p>
        <?php endif; ?>

    <?php endforeach; ?>

<?php else: ?>

    <p class="section-description">
        ขณะนี้ยังไม่มีเนื้อหาเกี่ยวกับภาวะซึมเศร้าในฐานข้อมูล
    </p>

<?php endif; ?>

            <!-- หัวข้อเกร็ดความรู้ -->

            
<h3 class="knowledge-heading">
    💡 เกร็ดความรู้เกี่ยวกับภาวะซึมเศร้า
</h3>

<div class="knowledge-grid">

    <?php if (!empty($tips)): ?>

        <?php foreach ($tips as $index => $tip): ?>

            <article class="knowledge-card">
                <h3>
                    <?= h(sprintf('%02d', $index + 1)) ?>
                    | <?= h($tip['title'] ?? 'เกร็ดความรู้') ?>
                </h3>

                <p>
                    <?= nl2br(h($tip['content'] ?? '')) ?>
                </p>

                <a
                    href="knowledge_detail.php?id=<?= urlencode((string)($tip['id'] ?? '')) ?>"
                    class="knowledge-link"
                >
                    อ่านรายละเอียด →
                </a>
            </article>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="empty-tips">
            ขณะนี้ยังไม่มีเกร็ดความรู้ในฐานข้อมูล
        </div>

    <?php endif; ?>

</div>
</section>



    </main>

    <!-- ========================================
         ปุ่มแชทบอท
    ======================================== -->

   

    <!-- ========================================
         Footer
    ======================================== -->

    <footer class="footer">

        <div class="footer-container">

            <div class="footer-brand">
                <h2>MindCare</h2>
                <p>ดูแลใจ เข้าใจตนเอง เริ่มต้นได้ที่นี่</p>
                <p>
                    เว็บไซต์ให้ความรู้เกี่ยวกับภาวะซึมเศร้า
                    และการดูแลสุขภาพจิต
                </p>
            </div>

            <div class="footer-menu">
                <h3>เมนูเว็บไซต์</h3>

                <a href="index.php">หน้าแรก</a>
                <a href="symptoms.php">อาการและสาเหตุของโรคซึมเศร้า</a>
                <a href="treatment.php">การรักษาและการดูแลตัวเองเบื้องต้น</a>
                <a href="assessment.php">แบบประเมินสุขภาพใจ</a>
                <a href="news.php">ข่าวสารและบทความ</a>
            </div>

            <div class="footer-contact">
                <h3>ช่องทางช่วยเหลือ</h3>

                <p>
                    สายด่วนสุขภาพจิต
                    <a href="tel:1323">1323</a>
                    โทรฟรีตลอด 24 ชั่วโมง
                </p>

                <a
                    href="https://www.google.com/maps/search/?api=1&amp;query=คลินิกจิตเวชใกล้ฉัน"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    ค้นหาคลินิกใกล้ฉัน
                </a>

                <p>
                    ข้อมูลบนเว็บไซต์จัดทำเพื่อการศึกษา
                    ไม่ใช้แทนการวินิจฉัยหรือคำแนะนำจากแพทย์
                </p>
            </div>

        </div>

        <div class="footer-bottom">
            <p>
                จัดทำขึ้นเพื่อเป็นส่วนหนึ่งของโครงงานทางการศึกษา
                เพื่อเผยแพร่ความรู้เกี่ยวกับภาวะซึมเศร้าและการดูแลสุขภาพจิต
            </p>
        </div>

    </footer>
<?php include __DIR__ . '/may_chat_widget.php'; ?>
</body>
</html>
