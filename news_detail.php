<?php
// ========================================
// MindCare | รายละเอียดข่าวสารและบทความ
// อ่านข้อมูลจากตาราง news ใน Supabase
// คอลัมน์ที่ใช้: id, title, content, image_url, is_published, created_at
// ========================================

require_once __DIR__ . '/supabase_config.php';

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * ส่งคำขออ่านข้อมูลจาก Supabase REST API
 */
function newsDetailRequest(string $query): array
{
    if (
        !defined('SUPABASE_URL') ||
        !defined('SUPABASE_SECRET_KEY') ||
        !function_exists('curl_init')
    ) {
        error_log('MindCare news detail: ตรวจสอบ Supabase หรือ cURL');
        return ['ok' => false, 'status' => 500, 'data' => []];
    }

    $baseUrl = trim((string) SUPABASE_URL);
    $key = trim((string) SUPABASE_SECRET_KEY);

    if ($baseUrl === '' || $key === '') {
        error_log('MindCare news detail: ไม่พบ Supabase URL หรือ Secret Key');
        return ['ok' => false, 'status' => 500, 'data' => []];
    }

    $url = rtrim($baseUrl, '/') . '/rest/v1/news?' . $query;
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
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        error_log('MindCare news detail cURL error: ' . curl_error($ch));
        curl_close($ch);
        return ['ok' => false, 'status' => 500, 'data' => []];
    }

    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        error_log('MindCare news detail HTTP ' . $status . ': ' . $response);
        return ['ok' => false, 'status' => $status, 'data' => []];
    }

    $data = json_decode($response, true);
    return [
        'ok' => is_array($data),
        'status' => $status,
        'data' => is_array($data) ? $data : []
    ];
}

/* รับ ID ข่าวจาก URL */
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$article = null;
$pageError = '';

if (!$id || $id < 1) {
    http_response_code(404);
    $pageError = 'ไม่พบรหัสข่าวสารที่ต้องการ';
} else {
    $result = newsDetailRequest(
        'select=*'
        . '&id=eq.' . (int) $id
        . '&is_published=eq.true'
        . '&limit=1'
    );

    if (!$result['ok']) {
        $pageError = 'ไม่สามารถโหลดข้อมูลได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง';
    } elseif (empty($result['data'])) {
        http_response_code(404);
        $pageError = 'ไม่พบบทความ หรือบทความนี้ยังไม่ได้เผยแพร่';
    } else {
        $article = $result['data'][0];
    }
}

$dateLabel = '';
if ($article && (!empty($article['published_at']) || !empty($article['created_at']))) {
    $timestamp = strtotime((string) ($article['published_at'] ?? $article['created_at']));
    if ($timestamp !== false) {
        $dateLabel = date('d/m/Y', $timestamp);
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $article ? h($article['title']) . ' | MindCare' : 'รายละเอียดข่าวสาร | MindCare' ?></title>
    <meta name="description" content="<?= h($article['title'] ?? 'รายละเอียดข่าวสารและบทความเกี่ยวกับสุขภาพจิต') ?>">

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

        /* Header และโลโก้ */
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

        /* Navigation */
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

        /* รายละเอียดบทความ */
        .detail-container {
            max-width: 870px;
            margin: 30px auto 70px;
            padding: 0 24px;
        }
        .article {
            background: transparent;
            border: none;
            border-radius: 0;
            overflow: visible;
            box-shadow: none;
        }
        .back-link {
            display: inline-block;
            margin: 0 0 12px;
            padding: 0;
            background: transparent;
            color: #27847d;
            border: none;
            border-radius: 0;
            text-decoration: none;
            font-weight: normal;
            font-size: 14px;
            box-shadow: none;
        }
        .back-link:hover {
            background: transparent;
            color: #173b65;
            text-decoration: underline;
        }
        .article-header {
            padding: 18px 0 12px;
            background: transparent;
        }
        .article-category {
            display: inline-block;
            background: #dcefe9;
            color: #267565;
            padding: 6px 13px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 12px;
        }
        .article-header h1 {
            margin: 0;
            color: #173b65;
            font-size: 32px;
            font-weight: 600;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }
        .article-date {
            color: #74839a;
            font-size: 13px;
            margin-top: 8px;
        }
        .article-cover {
            display: block;
            width: 100%;
            max-width: 730px;
            max-height: 460px;
            height: auto;
            object-fit: contain;
            background: transparent;
            border-radius: 10px;
            margin: 8px auto 22px;
        }

        .article-summary {
            color: #586981;
            font-size: 17px;
            line-height: 1.9;
            margin: 0 0 20px;
        }
        .article-links {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #e1e9f2;
        }
        .article-links h2 {
            color: #17264b;
            font-size: 21px;
            font-weight: 600;
            margin: 0 0 14px;
        }
        .video-link-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 18px;
        }
        .video-link {
            display: inline-block;
            padding: 9px 14px;
            background: #f0f7ff;
            color: #173b65;
            border: 1px solid #dce8f3;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            overflow-wrap: anywhere;
        }
        .video-link:hover {
            background: #e6f4f1;
            color: #087c69;
        }
        .video-embed {
            width: 100%;
            max-width: 730px;
            aspect-ratio: 16 / 9;
            border: 0;
            border-radius: 10px;
            display: block;
            margin: 12px auto 20px;
        }
        .article-content { padding: 0; }
        .article-content p {
            color: #43546a;
            font-size: 16px;
            line-height: 2;
            margin: 0 0 18px;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }
        .no-content {
            padding: 22px;
            border-radius: 12px;
            background: #f8fbff;
            border: 1px dashed #b9cbd9;
            color: #586981;
        }

        /* Error */
        .error-state {
            max-width: 760px;
            margin: 50px auto 80px;
            padding: 32px 24px;
            text-align: center;
            background: #f8fbff;
            border: 1px solid #dce8f3;
            border-radius: 16px;
        }
        .error-state h1 { color: #173b65; font-size: 25px; }
        .error-state p { color: #586981; }

        /* ปุ่มแชทบอท */
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
            .footer-container { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .footer-brand { grid-column: 1 / -1; }
        }
        @media (max-width: 768px) {
            .article-header h1 { font-size: 27px; }
            .footer-container {
                grid-template-columns: 1fr;
                gap: 25px;
                padding: 35px 22px;
            }
            .footer-brand { grid-column: auto; }
        }
        @media (max-width: 600px) {
            .top-bar { justify-content: center; flex-wrap: wrap; text-align: center; font-size: 12px; }
            .header { padding: 12px 5%; }
            .logo-icon { width: 62px; height: 62px; }
            .brand-text span { font-size: 23px; }
            .nav { gap: 3px; }
            .nav a { font-size: 13px; padding: 10px 8px; white-space: normal; }
            .detail-container { padding: 0 18px; margin-top: 18px; }
            .article-header h1 { font-size: 24px; }
            .article-content p { font-size: 15px; }
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
        <?php if ($article === null): ?>
            <section class="error-state">
                <h1>ไม่สามารถเปิดรายละเอียดได้</h1>
                <p><?= h($pageError) ?></p>
                <a class="back-link" href="news.php">← กลับไปข่าวสารและบทความ</a>
            </section>
        <?php else: ?>
            <section class="detail-container">
                <article class="article">
                    <a class="back-link" href="news.php">← กลับไปข่าวสารและบทความ</a>

                    <header class="article-header">
                        <span class="article-category"><?= h($article['category'] ?? 'ข่าวสารและบทความ') ?></span>
                        <h1><?= h($article['title'] ?? '') ?></h1>
                        <?php if ($dateLabel !== ''): ?>
                            <div class="article-date">เผยแพร่เมื่อ <?= h($dateLabel) ?></div>
                        <?php endif; ?>
                    </header>

                    <?php if (trim((string)($article['summary'] ?? '')) !== ''): ?>
                        <p class="article-summary"><?= h($article['summary']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($article['image_url'])): ?>
                        <img
                            class="article-cover"
                            src="<?= h($article['image_url']) ?>"
                            alt="<?= h($article['title'] ?? 'รูปภาพประกอบข่าว') ?>"
                            onerror="this.style.display='none'"
                        >
                    <?php endif; ?>

                    <div class="article-content">
                        <?php if (trim((string)($article['content'] ?? '')) !== ''): ?>
                            <p><?= h($article['content']) ?></p>
                        <?php else: ?>
                            <div class="no-content">บทความนี้ยังไม่มีเนื้อหาเพิ่มเติม</div>
                        <?php endif; ?>
                    </div>

                    <?php
                        $videoLinks = [
                            'YouTube' => trim((string)($article['youtube_url'] ?? '')),
                            'TikTok' => trim((string)($article['tiktok_url'] ?? '')),
                            'Facebook / Facebook Watch' => trim((string)($article['facebook_url'] ?? '')),
                            'Instagram / Reels' => trim((string)($article['instagram_url'] ?? '')),
                            'แพลตฟอร์มอื่น' => trim((string)($article['other_video_url'] ?? '')),
                            'แหล่งข่าวต้นฉบับ' => trim((string)($article['source_url'] ?? ''))
                        ];
                        $videoLinks = array_filter($videoLinks, static function ($url) {
                            return $url !== '' && filter_var($url, FILTER_VALIDATE_URL);
                        });
                        $youtubeEmbed = '';
                        $youtubeUrl = trim((string)($article['youtube_url'] ?? ''));
                        if ($youtubeUrl !== '') {
                            $host = strtolower((string)parse_url($youtubeUrl, PHP_URL_HOST));
                            $path = (string)parse_url($youtubeUrl, PHP_URL_PATH);
                            $videoId = '';
                            if (strpos($host, 'youtu.be') !== false) {
                                $videoId = trim($path, '/');
                            } elseif (strpos($host, 'youtube.com') !== false) {
                                parse_str((string)parse_url($youtubeUrl, PHP_URL_QUERY), $ytQuery);
                                $videoId = (string)($ytQuery['v'] ?? '');
                                if ($videoId === '' && strpos($path, '/embed/') === 0) {
                                    $videoId = substr($path, 7);
                                }
                            }
                            if (preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId)) {
                                $youtubeEmbed = 'https://www.youtube-nocookie.com/embed/' . $videoId;
                            }
                        }
                    ?>

                    <?php if (!empty($videoLinks)): ?>
                        <section class="article-links">
                            <h2>แหล่งข้อมูลและคลิปวิดีโอเพิ่มเติม</h2>
                            <div class="video-link-list">
                                <?php foreach ($videoLinks as $label => $url): ?>
                                    <a class="video-link"
                                       href="<?= h($url) ?>"
                                       target="_blank"
                                       rel="noopener noreferrer">
                                        <?= h($label) ?> ↗
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($youtubeEmbed !== ''): ?>
                                <iframe
                                    class="video-embed"
                                    src="<?= h($youtubeEmbed) ?>"
                                    title="วิดีโอ YouTube ประกอบข่าว"
                                    loading="lazy"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    referrerpolicy="strict-origin-when-cross-origin"
                                    allowfullscreen></iframe>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                </article>
            </section>
        <?php endif; ?>
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
