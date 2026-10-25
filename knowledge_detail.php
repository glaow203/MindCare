


<?php
require_once __DIR__ . '/supabase_config.php';

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$tip = null;
$images = [];
$blocks = [];
$loadError = false;

$secretKey = defined('SUPABASE_SECRET_KEY')
    ? trim(SUPABASE_SECRET_KEY)
    : '';

if (
    $id !== false &&
    $id !== null &&
    $id > 0 &&
    $secretKey !== '' &&
    defined('SUPABASE_URL')
) {
    $baseUrl = rtrim(SUPABASE_URL, '/');

    // ดึงข้อมูลบทความ รวมถึง URL รูปปก
    $url = $baseUrl
        . '/rest/v1/knowledge_tips'
        . '?select=id,title,content,image_url'
        . '&id=eq.' . $id
        . '&is_published=eq.true'
        . '&limit=1';

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
    curl_close($ch);

    if ($response !== false && $status >= 200 && $status < 300) {
        $result = json_decode($response, true);

        if (is_array($result) && isset($result[0])) {
            $tip = $result[0];
        } elseif (!is_array($result)) {
            $loadError = true;
        }
    } else {
        error_log('MindCare article request failed: HTTP ' . $status);
        $loadError = true;
    }

    // ดึงรูปประกอบของบทความเดียวกัน
    // ดึงหัวข้อ ข้อความ และรูปภาพจากตารางที่แอดมินบันทึกจริง
    if ($tip && isset($tip['id'])) {
        $blocksUrl = $baseUrl
            . '/rest/v1/knowledge_tip_sections'
            . '?select=id,block_type,content,image_url,alt_text,sort_order'
            . '&knowledge_tip_id=eq.' . (int)$tip['id']
            . '&order=sort_order.asc,id.asc';

        $ch = curl_init($blocksUrl);

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

        $blocksResponse = curl_exec($ch);
        $blocksStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (
            $blocksResponse !== false &&
            $blocksStatus >= 200 &&
            $blocksStatus < 300
        ) {
            $blocksResult = json_decode($blocksResponse, true);

            if (is_array($blocksResult)) {
                $blocks = $blocksResult;
            }
        } else {
            error_log(
                'MindCare article sections request failed: HTTP '
                . $blocksStatus
            );
        }
    }

} elseif ($secretKey === '' || !defined('SUPABASE_URL')) {
    $loadError = true;
}

$pageTitle = $tip['title'] ?? 'ไม่พบบทความ';
$content = trim((string)($tip['content'] ?? ''));

$coverUrl = trim((string)($tip['image_url'] ?? ''));

// แยกเนื้อหาตามบรรทัดว่าง โดยรักษาบทความเดิม
$paragraphs = $content !== ''
    ? preg_split('/\R\s*\R/u', $content)
    : [];
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= $tip ? htmlspecialchars($tip['title'], ENT_QUOTES, 'UTF-8') : 'ไม่พบข้อมูล'; ?> | MindCare</title>

<style>
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
    color: white;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 5px 18px rgba(0, 0, 0, 0.18);
}

/* รองรับหน้าจอมือถือ */
@media (max-width: 768px) {
    .footer-container {
        grid-template-columns: 1fr;
        gap: 25px;
        padding: 35px 22px;
    }
}

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Tahoma, Arial, sans-serif;
            color: #17264b;
            background: #ffffff;
        }

        /* Top Bar */
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

        /* Header */
        .header {
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 12px 4%;
            background: #ffffff;
            border-bottom: 1px solid #dce3ed;
        }

        /* Logo and project name */
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

        /* Horizontal navigation */
        .nav {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-end;
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

        .nav a:hover {
            background: #e6f2ef;
            color: #087c69;
        }

        /* Main content placeholder */
        .main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 24px;
            text-align: center;
        }

        .main h1 {
            color: #173b65;
            font-size: 32px;
        }

        .main p {
            color: #657187;
            line-height: 1.8;
        }

        /* Responsive layout */
        @media (max-width: 1050px) {
            .header {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .nav {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 600px) {
            .top-bar {
                justify-content: center;
                text-align: center;
                font-size: 12px;
            }

            .brand-text span {
                font-size: 23px;
            }

            .brand-text small {
                white-space: normal;
            }

            .nav a {
                font-size: 13px;
                padding: 9px 7px;
            }
        }
        /* Main content placeholder */
        .main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 24px;
            text-align: center;
        }

        .main h1 {
            color: #173b65;
            font-size: 32px;
        }

        .main p {
            color: #657187;
            line-height: 1.8;
        }

        /* Responsive layout */
        @media (max-width: 1050px) {
            .header {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .nav {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 600px) {
            .top-bar {
                justify-content: center;
                text-align: center;
                font-size: 12px;
            }

            .brand-text span {
                font-size: 23px;
            }

            .brand-text small {
                white-space: normal;
            }

            .nav a {
                font-size: 13px;
                padding: 9px 7px;
            }
        }
/* พื้นที่เนื้อหาหลัก ไม่มีกรอบครอบ */
.page-container {
    max-width: 1000px;
    width: 100%;
    margin: 35px auto;
    padding: 0 24px;
}

.article-card {
    width: 100%;
    background: transparent;
    border: none;
    border-radius: 0;
    box-shadow: none;
    padding: 0;
    text-align: left;
}

.back-link {
    display: inline-block;
    margin-bottom: 24px;
    color: #27847d;
    text-decoration: none;
}

/* หัวข้อชิดซ้าย */
.article-title {
    color: #173b65;
    font-size: 30px;
    line-height: 1.5;
    text-align: left;
    margin: 0 0 24px;
}

/* รูปภาพอยู่ตรงกลาง */
 .article img {
            display: block;
            width: 100%;
            max-width: 760px;
            height: 350px;
            object-fit: cover;
            border-radius: 12px;
            margin: 0 auto 25px;
        }

/* เนื้อหาชิดซ้ายแบบเอกสาร */
.article-content {
    width: 100%;
    text-align: left;
}

.article-content p {
    font-size: 16px;
    color: #3d4c60;
    line-height: 2;
    text-align: left;
    text-indent: 2em;
    margin: 0 0 20px;
    overflow-wrap: anywhere;
}

/* แหล่งอ้างอิง */
.source-box {
    margin-top: 30px;
    padding: 16px 0;
    border-top: 1px solid #dce5ed;
    text-align: left;
}

.source-box p {
    margin: 0 0 6px;
}

.source-box a {
    color: #27847d;
    overflow-wrap: anywhere;
}


/* Footer */
.site-footer {
    background: #111d43;
    color: #fff;
    text-align: center;
    padding: 22px 16px;
    font-size: 13px;
}

.site-footer p {
    margin: 4px 0;
}
/* Footer */
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
    transition: color 0.2s;
}

.footer a:hover {
    color: #93c5fd;
}

.footer-brand h2 i {
    color: #93c5fd;
}

.footer-contact i {
    margin-right: 6px;
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

/* มือถือ */
@media (max-width: 768px) {
    .nav-container {
        padding: 15px 18px;
    }

    .nav-menu {
        justify-content: flex-start;
        gap: 12px;
    }

    .page-container {
        margin: 24px auto;
        padding: 0 18px;
    }

    .article-title {
        font-size: 24px;
    }

    .detail-image {
        height: 230px;
    }

    .article-content p {
        font-size: 15px;
    }
    
.detail-image {
    display: block;
    width: 100%;
    max-width: 760px;
    height: auto;
    max-height: 520px;
    object-fit: contain;
    border-radius: 12px;
    margin: 0 auto 25px;
}

.article-images {
    margin-top: 30px;
}

.article-images h2 {
    color: #173b65;
    font-size: 22px;
    margin-bottom: 20px;
}

.article-image-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

.article-image-grid figure {
    margin: 0;
}

.article-image-grid figcaption {
    color: #657187;
    font-size: 14px;
    text-align: center;
    margin-top: 8px;
}
/* หัวข้อย่อยจากระบบจัดการเนื้อหา */
.article-subheading {
    color: #173b65;
    font-size: 23px;
    line-height: 1.6;
    margin: 30px 0 14px;
    text-align: left;
}

/* รูปภาพที่แทรกระหว่างเนื้อหา */
.content-image {
    margin: 24px 0;
    text-align: center;
}

.content-image .detail-image {
    display: block;
    width: 100%;
    max-width: 760px;
    height: auto;
    max-height: 520px;
    object-fit: contain;
    border-radius: 12px;
    margin: 0 auto;
}

.content-image figcaption {
    color: #657187;
    font-size: 14px;
    line-height: 1.8;
    margin-top: 8px;
    text-align: center;
}

@media (max-width: 600px) {
    .article-subheading {
        font-size: 20px;
    }
}
    
}
</style>
</head>

<body>

 <!-- Top Bar: สายด่วนสุขภาพจิต -->
    <div class="top-bar">
        <a href="tel:1323">☎ สายด่วนสุขภาพจิต 1323</a>
        &nbsp; | &nbsp; โทรฟรีตลอด 24 ชั่วโมง
    </div>

    <!-- Header: โลโก้และเมนู -->
    <header class="header">

        <a href="index.php" class="brand">
            <img src="https://cdn.discordapp.com/attachments/1280536102024904777/1562841883238207519/Gemini_Generated_Image_lwbljvlwbljvlwbl-photoaidcom-cropped.jpg?ex=6adb756d&is=6ada23ed&hm=3c11eea0b45ac1f7c2c6ccacd5e9b57d753b0b52a8b74a45c0c124d488eca810&" alt="โลโก้ MindCare" class="logo-icon">

            <div class="brand-text">
                <span>MindCare</span>
                <small>เว็บไซต์ให้ความรู้เกี่ยวกับภาวะซึมเศร้า</small>
            </div>
        </a>

       
<nav class="nav">
    <a href="index.php">หน้าแรก</a>

    <a href="symptoms.php">อาการของภาวะซึมเศร้า</a>

    <a href="treatment.php">การรักษาและการดูแลตัวเองเบื้องต้น</a>

    <a href="news.php">ข่าวสารและบทความ</a>

    <a href="assessment.php">แบบประเมินสุขภาพใจ</a>

    
<a
    href="https://www.google.com/maps/search/?api=1&query=คลินิกจิตเวชใกล้ฉัน"
    target="_blank"
    rel="noopener noreferrer"
>
    <i class="fas fa-map-marker-alt"></i>
    ค้นหาคลินิกใกล้ฉัน
</a>

</nav>



    </header>

<main class="page-container">
    <article class="article-card">

        <a href="index.php#about" class="back-link">← กลับไปหน้าแรก</a>

       
<?php if ($loadError): ?>

    <h1 class="article-title">ไม่สามารถโหลดบทความได้</h1>
    <p>กรุณาลองใหม่ภายหลัง</p>

<?php elseif (!$tip): ?>

    <h1 class="article-title">ไม่พบบทความ</h1>
    <p>บทความนี้อาจไม่มีอยู่ หรือยังไม่ได้เผยแพร่</p>

<?php else: ?>

    <article class="article">
        <h1 class="article-title"><?= h($pageTitle) ?></h1>
        
<?php if ($coverUrl !== '' && filter_var($coverUrl, FILTER_VALIDATE_URL)): ?>
    <img
        class="detail-image"
        src="<?= h($coverUrl) ?>"
        alt="<?= h($pageTitle) ?>"
        loading="lazy"
    >
<?php endif; ?>


        <div class="article-content">

    <?php if (!empty($blocks)): ?>

        <?php foreach ($blocks as $block): ?>

            <?php
            $type = $block['block_type'] ?? 'text';
            $text = trim((string)($block['content'] ?? ''));
            $src = trim((string)($block['image_url'] ?? ''));
            $alt = trim((string)($block['alt_text'] ?? ''));
            ?>

            <?php if ($type === 'heading' && $text !== ''): ?>

                <h2 class="article-subheading">
                    <?= h($text) ?>
                </h2>

            <?php elseif ($type === 'text' && $text !== ''): ?>

                <p><?= nl2br(h($text)) ?></p>

            <?php elseif (
                $type === 'image' &&
                $src !== '' &&
                filter_var($src, FILTER_VALIDATE_URL) &&
                in_array(
                    strtolower((string)parse_url($src, PHP_URL_SCHEME)),
                    ['http', 'https'],
                    true
                )
            ): ?>

                <figure class="content-image">
                    <img
                        class="detail-image"
                        src="<?= h($src) ?>"
                        alt="<?= h($alt) ?>"
                        loading="lazy"
                    >

                    <?php if ($text !== '' || $alt !== ''): ?>
                        <figcaption>
                            <?= h($text !== '' ? $text : $alt) ?>
                        </figcaption>
                    <?php endif; ?>
                </figure>

            <?php endif; ?>

        <?php endforeach; ?>

    <?php else: ?>

        <!-- แสดงเนื้อหาเก่า หากบทความยังไม่มีบล็อกใหม่ -->
        <?php foreach ($paragraphs as $paragraph): ?>
            <?php if (trim($paragraph) !== ''): ?>
                <p><?= nl2br(h(trim($paragraph))) ?></p>
            <?php endif; ?>
        <?php endforeach; ?>

    <?php endif; ?>

</div>
        
<?php if (!empty($images)): ?>
    <section class="article-images">
        <h2>รูปภาพประกอบ</h2>

        <div class="article-image-grid">
            <?php foreach ($images as $image): ?>
                <?php
                $src = trim((string)($image['image_url'] ?? ''));
                $alt = trim((string)($image['alt_text'] ?? ''));
                ?>

                <?php if ($src !== '' && filter_var($src, FILTER_VALIDATE_URL)): ?>
                    <figure>
                        <img
                            class="detail-image"
                            src="<?= h($src) ?>"
                            alt="<?= h($alt !== '' ? $alt : $pageTitle) ?>"
                            loading="lazy"
                        >
                        <?php if ($alt !== ''): ?>
                            <figcaption><?= h($alt) ?></figcaption>
                        <?php endif; ?>
                    </figure>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php endif; ?>


    </article>
</main>
<!-- ปุ่มแชทบอท: ส่วนหน้าตาเบื้องต้น -->


<footer class="footer">
    <div class="footer-container">

        <div class="footer-brand">
            <h2><i class="fas fa-heart"></i> MindCare</h2>
            <p>ดูแลใจ เข้าใจตนเอง เริ่มต้นได้ที่นี่</p>
            <p>เว็บไซต์ให้ความรู้เกี่ยวกับภาวะซึมเศร้า
                และการดูแลสุขภาพจิต</p>
        </div>

        <div class="footer-menu">
            <h3>เมนูเว็บไซต์</h3>
            <a href="#home">หน้าแรก</a>
            <a href="#about">รู้จักภาวะซึมเศร้า</a>
            <a href="#symptoms">สังเกตอาการ</a>
            <a href="#treatment">การรักษา</a>
            <a href="#assessment">แบบประเมินสุขภาพใจ</a>
        </div>

        <div class="footer-contact">
            <h3>ช่องทางช่วยเหลือ</h3>
            <p>
                <i class="fas fa-phone"></i>
                สายด่วนสุขภาพจิต 1323
            </p>
            <a href="https://www.google.com/maps/search/?api=1&query=คลินิกจิตเวชใกล้ฉัน"
               target="_blank" rel="noopener noreferrer">
                <i class="fas fa-map-marker-alt"></i>
                ค้นหาคลินิกใกล้ฉัน
            </a>
            <p>ข้อมูลเพื่อการศึกษา ไม่ใช้แทนการวินิจฉัยจากแพทย์</p>
        </div>

    </div>

    <div class="footer-bottom">
        <p>จัดทำขึ้นเพื่อเป็นส่วนหนึ่งของโครงงานทางการศึกษา เพื่อเผยแพร่ความรู้เกี่ยวกับภาวะซึมเศร้าและการดูแลสุขภาพจิต</p>
    </div>
</footer>
<?php include __DIR__ . '/may_chat_widget.php'; ?>
</body>

</html>
