<?php
// ========================================
// MindCare | ข่าวสารและบทความ
// ใช้ตารางแยกชื่อ news ใน Supabase
// คาดว่าตารางมีคอลัมน์: id, title, content, image_url,
// is_published, created_at
// ========================================

require_once __DIR__ . '/supabase_config.php';

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * โหลดข่าวที่เผยแพร่แล้วจากตาราง news
 */
function getNewsItems(): array
{
    if (
        !defined('SUPABASE_URL') ||
        !defined('SUPABASE_SECRET_KEY') ||
        !function_exists('curl_init')
    ) {
        error_log('MindCare news: ตรวจสอบ Supabase หรือ cURL');
        return [];
    }

    $supabaseUrl = trim((string) SUPABASE_URL);
    $secretKey = trim((string) SUPABASE_SECRET_KEY);

    if ($supabaseUrl === '' || $secretKey === '') {
        error_log('MindCare news: ไม่พบ Supabase URL หรือ Secret Key');
        return [];
    }

    $url = rtrim($supabaseUrl, '/')
        . '/rest/v1/news'
        . '?select=id,title,content,image_url,is_published,created_at'
        . '&is_published=eq.true'
        . '&order=created_at.desc';

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
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        error_log('MindCare news cURL error: ' . curl_error($ch));
        curl_close($ch);
        return [];
    }

    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        error_log('MindCare news HTTP ' . $status . ': ' . $response);
        return [];
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : [];
}

function newsExcerpt(string $content, int $limit = 150): string
{
    $excerpt = preg_replace('/\s+/u', ' ', trim(strip_tags($content))) ?? '';

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($excerpt, 'UTF-8') > $limit) {
            return mb_substr($excerpt, 0, $limit, 'UTF-8') . '...';
        }
        return $excerpt;
    }

    if (strlen($excerpt) > $limit * 3) {
        return substr($excerpt, 0, $limit * 3) . '...';
    }

    return $excerpt;
}

$newsItems = getNewsItems();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข่าวสารและบทความ | MindCare</title>
    <meta name="description" content="ติดตามข่าวสาร บทความ และความรู้เกี่ยวกับสุขภาพจิตและภาวะซึมเศร้าผ่านเว็บไซต์ MindCare">

    <style>
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: Tahoma, Arial, sans-serif;
            color: #17264b;
            background: #fff;
            line-height: 1.8;
        }
        img { max-width: 100%; }
        a { transition: color .2s, background .2s; }

        /* Top bar */
        .top-bar {
            min-height: 46px;
            background: #111d43;
            color: #fff;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 10px 4%;
            font-size: 14px;
        }
        .top-bar a { color: #fff; text-decoration: none; font-weight: bold; }
        .top-bar a:hover { color: #bfdbfe; }

        /* Header / brand / navigation */
        .header {
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 12px 4%;
            background: #fff;
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
        }
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
        .nav a:hover, .nav a.active {
            background: #e6f2ef;
            color: #087c69;
        }

        /* Intro */
        .page-intro {
            padding: 65px 7% 45px;
            background: linear-gradient(135deg, #f0f7ff, #e6f4f1);
        }
        .intro-inner { max-width: 1100px; margin: 0 auto; }
        .hero-tag {
            display: inline-block;
            background: #dcefe9;
            color: #267565;
            padding: 9px 16px;
            border-radius: 30px;
            font-size: 14px;
            margin-bottom: 18px;
        }
        .page-intro h1 {
            font-size: 38px;
            line-height: 1.5;
            color: #173b65;
            margin: 0 0 18px;
        }
        .page-intro h1 span { color: #318b88; }
        .page-intro p {
            max-width: 950px;
            color: #586981;
            font-size: 16px;
            line-height: 2;
            margin: 0 0 14px;
        }
        .health-note {
            margin-top: 22px;
            padding: 16px 20px;
            background: rgba(255,255,255,.8);
            border-left: 5px solid #318b88;
            border-radius: 10px;
            color: #43546a;
            font-size: 14px;
        }

        /* News cards */
        .content-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 45px 30px 60px;
        }
        .section-title {
            color: #173b65;
            font-size: 30px;
            line-height: 1.5;
            margin: 0 0 10px;
            padding-left: 14px;
            border-left: 5px solid #318b88;
        }
        .section-description {
            color: #586981;
            font-size: 15px;
            margin: 0 0 28px;
        }
        .news-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: stretch;
            gap: 20px;
        }
        .news-card {
            min-width: 0;
            height: 100%;
            background: #f0f7ff;
            border: 1px solid #dce8f3;
            border-top: 4px solid #318b88;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 5px 16px rgba(23,59,101,.06);
            display: flex;
            flex-direction: column;
        }
        .news-image, .image-placeholder {
            display: block;
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #e6f2f5;
        }
        .image-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #318b88;
            background: linear-gradient(135deg, #e6f4f1, #f0f7ff);
        }
        .news-card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .card-label {
            color: #27847d;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .news-card h3 {
            color: #216b63;
            font-size: 18px;
            line-height: 1.6;
            margin: 0 0 10px;
            overflow-wrap: anywhere;
        }
        .news-card p {
            color: #43546a;
            font-size: 14px;
            line-height: 1.8;
            margin: 0 0 18px;
            overflow-wrap: anywhere;
            white-space: pre-line;
        }
        .news-date {
            color: #74839a;
            font-size: 12px;
            margin-bottom: 10px;
        }
        .detail-button {
            display: inline-block;
            align-self: flex-end;
            margin-top: auto;
            padding: 10px 18px;
            background: #173b65;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }
        .detail-button:hover { background: #27847d; }
        .empty-state {
            background: #f8fbff;
            border: 1px dashed #b9cbd9;
            border-radius: 12px;
            padding: 28px;
            text-align: center;
            color: #586981;
        }
        .empty-state h3 { color: #173b65; margin-top: 0; }

        /* Chat button */
        .chat-button {
            position: fixed;
            right: 25px;
            bottom: 25px;
            z-index: 1000;
            border: none;
            border-radius: 50px;
            padding: 15px 22px;
            background: #173b65;
            color: #fff;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 5px 18px rgba(0,0,0,.18);
        }
        .chat-button:hover { background: #27847d; }

        /* Footer */
        .footer { background: #102d50; color: #fff; margin-top: 60px; }
        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 45px 30px;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1.2fr;
            gap: 40px;
        }
        .footer h2 { font-size: 26px; margin: 0 0 12px; }
        .footer h3 { font-size: 18px; margin: 0 0 18px; color: #bfdbfe; }
        .footer p { color: #d5e2f0; font-size: 14px; line-height: 1.8; }
        .footer a {
            display: block;
            color: #d5e2f0;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 12px;
        }
        .footer a:hover { color: #93c5fd; }
        .footer-bottom { background: #0b223d; text-align: center; padding: 12px 20px; }
        .footer-bottom p { margin: 0; font-size: 13px; }

        /* Responsive */
        @media (max-width: 1100px) {
            .header { align-items: flex-start; flex-direction: column; gap: 12px; }
            .nav { justify-content: flex-start; width: 100%; }
        }
        @media (max-width: 900px) {
            .news-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .footer-container { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .footer-brand { grid-column: 1 / -1; }
        }
        @media (max-width: 768px) {
            .page-intro { padding: 42px 6%; }
            .page-intro h1 { font-size: 31px; }
            .footer-container { grid-template-columns: 1fr; gap: 25px; padding: 35px 22px; }
            .footer-brand { grid-column: auto; }
        }
        @media (max-width: 600px) {
            .top-bar { justify-content: center; flex-wrap: wrap; text-align: center; font-size: 12px; }
            .header { padding: 12px 5%; }
            .logo-icon { width: 62px; height: 62px; }
            .brand-text span { font-size: 23px; }
            .nav { gap: 3px; }
            .nav a { font-size: 13px; padding: 10px 8px; white-space: normal; }
            .news-grid { grid-template-columns: 1fr; }
            .content-section { padding: 35px 20px; }
            .section-title { font-size: 25px; }
            .chat-button { right: 15px; bottom: 15px; padding: 13px 17px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="tel:1323">☎ สายด่วนสุขภาพจิต 1323</a>
        <span>&nbsp; | &nbsp; โทรฟรีตลอด 24 ชั่วโมง</span>
    </div>

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
            <a href="news.php" class="active">ข่าวสารและบทความ</a>
            <a href="assessment.php">แบบประเมินสุขภาพใจ</a>
            <a href="https://www.google.com/maps/search/?api=1&amp;query=คลินิกจิตเวชใกล้ฉัน" target="_blank" rel="noopener noreferrer">ค้นหาคลินิกใกล้ฉัน</a>
        </nav>
    </header>

    <main>
        <section class="page-intro">
            <div class="intro-inner">
                <span class="hero-tag">ติดตามข่าวสารสุขภาพใจ</span>
                <h1>ข่าวสารและบทความ<br><span>เกี่ยวกับสุขภาพจิต</span></h1>
                <p>
                    อ่านข่าวสาร บทความ และข้อมูลที่ช่วยให้เข้าใจสุขภาพจิต
                    รวมถึงภาวะซึมเศร้าและการดูแลใจในชีวิตประจำวัน
                </p>
                <p>
                    เนื้อหาบนเว็บไซต์จัดทำเพื่อให้ความรู้เบื้องต้น
                    ควรพิจารณาแหล่งที่มาและปรึกษาผู้เชี่ยวชาญเมื่อมีข้อสงสัยด้านสุขภาพ
                </p>
                <div class="health-note">
                    <strong>ข้อควรรู้:</strong>
                    บทความเป็นข้อมูลเพื่อการศึกษา ไม่ใช้แทนการวินิจฉัยหรือคำแนะนำจากแพทย์
                </div>
            </div>
        </section>

        <section class="content-section" id="news">
            <h2 class="section-title">ข่าวสารและบทความล่าสุด</h2>
            <p class="section-description">ติดตามเนื้อหาที่เผยแพร่จากทีม MindCare</p>

            <?php if (!empty($newsItems)): ?>
                <div class="news-grid">
                    <?php foreach ($newsItems as $item): ?>
                        <?php
                            $title = (string)($item['title'] ?? 'ข่าวสาร MindCare');
                            $content = trim((string)($item['content'] ?? ''));
                            $imageUrl = trim((string)($item['image_url'] ?? ''));
                            $excerpt = newsExcerpt($content);
                            $createdAt = trim((string)($item['created_at'] ?? ''));
                            $dateLabel = '';
                            if ($createdAt !== '') {
                                $timestamp = strtotime($createdAt);
                                if ($timestamp !== false) {
                                    $dateLabel = date('d/m/Y', $timestamp);
                                }
                            }
                        ?>
                        <article class="news-card">
                            <?php if ($imageUrl !== ''): ?>
                                <img src="<?= h($imageUrl) ?>" alt="<?= h($title) ?>" class="news-image" loading="lazy" onerror="this.style.display='none'">
                            <?php else: ?>
                                <div class="image-placeholder">ยังไม่มีรูปภาพ</div>
                            <?php endif; ?>
                            <div class="news-card-body">
                                <div class="card-label">ข่าวสารและบทความ</div>
                                <?php if ($dateLabel !== ''): ?>
                                    <div class="news-date">เผยแพร่เมื่อ <?= h($dateLabel) ?></div>
                                <?php endif; ?>
                                <h3><?= h($title) ?></h3>
                                <p><?= h($excerpt !== '' ? $excerpt : 'เลือกอ่านรายละเอียดเพิ่มเติมเกี่ยวกับบทความนี้') ?></p>
                                <a href="news_detail.php?id=<?= urlencode((string)($item['id'] ?? '')) ?>" class="detail-button">รายละเอียด →</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>ยังไม่มีข่าวสารและบทความ</h3>
                    <p>เมื่อเพิ่มข้อมูลในตาราง news และตั้งสถานะ is_published เป็น true แล้ว ข่าวจะปรากฏในส่วนนี้โดยอัตโนมัติ</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-brand">
                <h2>MindCare</h2>
                <p>ดูแลใจ เข้าใจตนเอง เริ่มต้นได้ที่นี่</p>
                <p>เว็บไซต์ให้ความรู้เกี่ยวกับภาวะซึมเศร้าและการดูแลสุขภาพจิต</p>
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
                <p>สายด่วนสุขภาพจิต <a href="tel:1323">1323</a> โทรฟรีตลอด 24 ชั่วโมง</p>
                <a href="https://www.google.com/maps/search/?api=1&amp;query=คลินิกจิตเวชใกล้ฉัน" target="_blank" rel="noopener noreferrer">ค้นหาคลินิกใกล้ฉัน</a>
                <p>ข้อมูลบนเว็บไซต์จัดทำเพื่อการศึกษา ไม่ใช้แทนการวินิจฉัยหรือคำแนะนำจากแพทย์</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>จัดทำขึ้นเพื่อเป็นส่วนหนึ่งของโครงงานทางการศึกษา เพื่อเผยแพร่ความรู้เกี่ยวกับภาวะซึมเศร้าและการดูแลสุขภาพจิต</p>
        </div>
    </footer>
    <?php include __DIR__ . '/may_chat_widget.php'; ?>
</body>

</html>
