

<?php

// ========================================

// MindCare | รายละเอียดอาการและสาเหตุ

// ========================================



require_once __DIR__ . '/supabase_config.php';



if (!function_exists('h')) {

    function h($value): string

    {

        return htmlspecialchars(

            (string)($value ?? ''),

            ENT_QUOTES,

            'UTF-8'

        );

    }

}



/**

 * ส่งคำขอไปยัง Supabase REST API

 */

function detailRequest(string $table, string $query): array

{

    if (

        !defined('SUPABASE_URL') ||

        !defined('SUPABASE_SECRET_KEY') ||

        !function_exists('curl_init')

    ) {

        return [

            'ok' => false,

            'status' => 500,

            'data' => []

        ];

    }



    $url = rtrim(SUPABASE_URL, '/')

        . '/rest/v1/' . $table . '?' . $query;



    $key = trim(SUPABASE_SECRET_KEY);



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

    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);



    if ($response === false) {

        error_log('MindCare detail API error: ' . curl_error($ch));

        curl_close($ch);



        return [

            'ok' => false,

            'status' => 500,

            'data' => []

        ];

    }



    curl_close($ch);



    if ($status < 200 || $status >= 300) {

        error_log(

            'MindCare detail HTTP ' . $status . ': ' . $response

        );



        return [

            'ok' => false,

            'status' => $status,

            'data' => []

        ];

    }



    $data = json_decode($response, true);



    return [

        'ok' => is_array($data),

        'status' => $status,

        'data' => is_array($data) ? $data : []

    ];

}



/* =========================

   รับ ID บทความจาก URL

\========================= */



$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);



if (!$id || $id < 1) {

    http_response_code(404);

    $article = null;

    $blocks = [];

    $pageError = 'ไม่พบรหัสบทความที่ต้องการ';

} else {

    $articleResult = detailRequest(

        'knowledge_tips',

        'select=id,title,content,image_url,is_published,content_group'

        . '&id=eq.' . (int)$id

        . '&is_published=eq.true'

        . '&content_group=in.(symptoms,causes)'

        . '&limit=1'

    );



    $article = null;

    $blocks = [];

    $pageError = '';



    if (!$articleResult['ok']) {

        $pageError = 'ไม่สามารถโหลดข้อมูลได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง';

    } elseif (empty($articleResult['data'])) {

        http_response_code(404);

        $pageError = 'ไม่พบบทความ หรือบทความนี้ยังไม่ได้เผยแพร่';

    } else {

        $article = $articleResult['data'][0];



        /* โหลดเนื้อหาฉบับเต็ม */

        $sectionResult = detailRequest(

            'knowledge_tip_sections',

            'select=id,knowledge_tip_id,block_type,content,image_url,alt_text,sort_order'

            . '&knowledge_tip_id=eq.' . (int)$article['id']

            . '&order=sort_order.asc,id.asc'

        );



        if (!$sectionResult['ok']) {

            $pageError = 'โหลดเนื้อหาฉบับเต็มไม่สำเร็จ กรุณาลองใหม่อีกครั้ง';

            $article = null;

        } else {

            $blocks = $sectionResult['data'];

        }

    }

}



/* =========================

   ชื่อหมวดหมู่

\========================= */



$group = $article['content_group'] ?? 'symptoms';



$category = $group === 'causes'

    ? 'สาเหตุของโรคซึมเศร้า'

    : 'อาการของโรคซึมเศร้า';



$backUrl = 'symptoms.php#' . (

    $group === 'causes' ? 'causes' : 'symptoms'

);

?>

<!DOCTYPE html>

<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>

        <?= $article ? h($article['title']) . ' | MindCare' : 'รายละเอียดบทความ | MindCare' ?>

    </title>



    <meta

        name="description"

        content="<?= h($article['title'] ?? 'รายละเอียดข้อมูลอาการและสาเหตุของโรคซึมเศร้า') ?>"

    >



    <style>

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

            line-height: 1.8;

        }



        img {

            max-width: 100%;

        }



        a {

            transition: color 0.2s, *background* 0.2s;

        }



        /* Topbar */

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



        /* Header และโลโก้ */

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



        .nav a:hover,

        .nav a.active {

            background: #e6f2ef;

            color: #087c69;

        }



        /* Breadcrumb */

        .breadcrumb {

            max-width: 1100px;

            margin: 24px auto 0;

            padding: 0 24px;

            font-size: 14px;

            color: #657187;

        }



        .breadcrumb a {

            color: #27847d;

            text-decoration: none;

        }



        .breadcrumb a:hover {

            text-decoration: underline;

        }



        /* เนื้อหาบทความ */

                .detail-container {
            max-width: 870px;
            margin: 22px auto 70px;
            padding: 0 24px;
        }



                .article {
            background: transparent;
            border: none;
            border-radius: 0;
            overflow: visible;
            box-shadow: none;
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

            line-height: 1.6;

            overflow-wrap: anywhere;

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



                .article-content {
            padding: 0;
        }



        .article-summary {

            color: #43546a;

            font-size: 16px;

            line-height: 2;

            white-space: pre-line;

            overflow-wrap: anywhere;

            margin: 0 0 28px;

        }



        .article-block {

            margin: 0 0 24px;

        }



        
.article-block h2 {
    color: #172020;
    font-size: 17px;
    font-weight: 500;
    line-height: 1.7;
    margin: 24px 0 12px;
    padding-left: 0;
    border-left: none;
    overflow-wrap: anywhere;
}




        .article-block p {

            color: #43546a;

            font-size: 16px;

            line-height: 2;

            margin: 0 0 18px;

            white-space: pre-line;

            overflow-wrap: anywhere;

        }



        .article-block img {

            display: block;

            max-width: 100%;

            height: auto;

            max-height: 600px;

            object-fit: contain;

            margin: 18px auto;

            border-radius: 12px;

        }



        .no-content {

            padding: 22px;

            border-radius: 12px;

            background: #f8fbff;

            border: 1px dashed #b9cbd9;

            color: #586981;

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



        .error-state {

            max-width: 760px;

            margin: 50px auto 80px;

            padding: 32px 24px;

            text-align: center;

            background: #f8fbff;

            border: 1px solid #dce8f3;

            border-radius: 16px;

        }



        .error-state h1 {

            color: #173b65;

            font-size: 25px;

        }



        .error-state p {

            color: #586981;

        }



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

            color: #ffffff;

            font-size: 15px;

            font-weight: bold;

            cursor: pointer;

            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.18);

        }



        .chat-button:hover {

            background: #27847d;

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



        /* Responsive */

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

        }



        @media (max-width: 900px) {

            .footer-container {

                grid-template-columns: repeat(2, minmax(0, 1fr));

            }



            .footer-brand {

                grid-column: 1 / -1;

            }

        }



        @media (max-width: 768px) {

            .article-header {

                padding: 26px 22px 18px;

            }



            .article-header h1 {

                font-size: 27px;

            }



            .article-content {

                padding: 24px 22px 30px;

            }



            .article-block h2 {

                font-size: 18x;

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



            .nav {

                gap: 3px;

            }



            .nav a {

                font-size: 13px;

                padding: 10px 8px;

                white-space: normal;

            }



            .breadcrumb {

                padding: 0 18px;

                font-size: 13px;

            }



            .detail-container {

                padding: 0 14px;

                margin-top: 18px;

            }



            .article-header h1 {

                font-size: 24px;

            }



            .article-content {

                padding: 20px 18px 26px;

            }



            .article-summary,

            .article-block p {

                font-size: 15px;

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



    <!-- Topbar -->

    <div class="top-bar">

        <a href="tel:1323">☎ สายด่วนสุขภาพจิต 1323</a>

        <span>&nbsp; | &nbsp; โทรฟรีตลอด 24 ชั่วโมง</span>

    </div>



    <!-- Header -->

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

            <a href="symptoms.php" class="active">อาการและสาเหตุของโรคซึมเศร้า</a>

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



    <main>



        <?php if ($article === null): ?>



            <section class="error-state">

                <h1>ไม่สามารถเปิดรายละเอียดได้</h1>

                <p><?= h($pageError) ?></p>

                <a class="back-link" href="symptoms.php">

                    ← กลับไปหน้าอาการและสาเหตุ

                </a>

            </section>



        <?php else: ?>







            <section class="detail-container">

                <article class="article">



<a class="back-link" href="<?= h($backUrl) ?>">

    ← กลับไป<?= h($category) ?>

</a>



<header class="article-header">

                        <span class="article-category">

                            <?= h($category) ?>

                        </span>



                        <h1><?= h($article['title']) ?></h1>

                    </header>



                    <?php if (!empty($article['image_url'])): ?>

                        <img

                            class="article-cover"

                            src="<?= h($article['image_url']) ?>"

                            alt="<?= h($article['title']) ?>"

                            onerror="this.style.display='none'"

                        >

                    <?php endif; ?>



<div class="article-content">

    <?php if (!empty($blocks)): ?>

        <?php foreach ($blocks as $block): ?>
            <?php
                $type = $block['block_type'] ?? 'text';
                $content = trim((string)($block['content'] ?? ''));
                $imageUrl = trim((string)($block['image_url'] ?? ''));
                $altText = trim((string)($block['alt_text'] ?? ''));
            ?>

            <?php if ($type === 'heading' && $content !== ''): ?>
                <section class="article-block">
                    <h2><?= h($content) ?></h2>
                </section>

            <?php elseif ($type === 'text' && $content !== ''): ?>
                <section class="article-block">
                    <p><?= h($content) ?></p>
                </section>

            <?php elseif ($type === 'image' && $imageUrl !== ''): ?>
                <section class="article-block">
                    <img
                        src="<?= h($imageUrl) ?>"
                        alt="<?= h($altText !== '' ? $altText : $article['title']) ?>"
                        loading="lazy"
                        onerror="this.style.display='none'"
                    >
                </section>
            <?php endif; ?>

        <?php endforeach; ?>

    <?php elseif (trim((string)($article['content'] ?? '')) !== ''): ?>

        <div class="article-summary">
            <?= h($article['content']) ?>
        </div>

    <?php else: ?>

        <div class="no-content">
            บทความนี้ยังไม่มีเนื้อหาฉบับเต็ม
        </div>

    <?php endif; ?>

</div>


                </article>

            </section>



        <?php endif; ?>



    </main>



    <!-- ปุ่มแชทบอท -->

   



    <!-- Footer -->

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
