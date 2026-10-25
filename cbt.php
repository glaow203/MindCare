<?php
$title = 'การบำบัดพฤติกรรมทางปัญญา (CBT)';

$content = [
    [
        'heading' => 'CBT คืออะไร?',
        'paragraphs' => [
            'การบำบัดพฤติกรรมทางปัญญา หรือ Cognitive Behavioral Therapy (CBT) เป็นการบำบัดทางจิตใจที่ช่วยให้บุคคลเข้าใจความเชื่อมโยงระหว่างความคิด อารมณ์ และพฤติกรรมของตนเอง',
            'แนวทางนี้ช่วยให้ผู้เข้ารับการบำบัดเรียนรู้วิธีสังเกตความคิดที่ไม่เป็นประโยชน์ พิจารณาสถานการณ์อย่างรอบด้าน และปรับพฤติกรรมเพื่อรับมือกับปัญหาในชีวิตประจำวันได้ดีขึ้น'
        ]
    ],
    [
        'heading' => 'CBT เกี่ยวข้องกับภาวะซึมเศร้าอย่างไร?',
        'paragraphs' => [
            'ผู้ที่มีภาวะซึมเศร้าอาจมีความคิดด้านลบเกี่ยวกับตนเอง อนาคต หรือสิ่งที่กำลังเผชิญ เช่น รู้สึกว่าตนเองไม่มีความสามารถ หรือเชื่อว่าสถานการณ์จะไม่ดีขึ้น',
            'CBT ช่วยให้ผู้เข้ารับการบำบัดฝึกตรวจสอบความคิดเหล่านั้น โดยพิจารณาข้อเท็จจริงและมุมมองอื่นที่เป็นไปได้ รวมถึงฝึกทำกิจกรรมที่เป็นประโยชน์ต่อชีวิตประจำวัน'
        ]
    ],
    [
        'heading' => 'ขั้นตอนการบำบัด CBT',
        'paragraphs' => [
            '1. สังเกตความคิดและอารมณ์ เรียนรู้ว่าเหตุการณ์ต่าง ๆ ส่งผลต่อความรู้สึกและพฤติกรรมอย่างไร',
            '2. ตรวจสอบความคิด พิจารณาว่าความคิดที่เกิดขึ้นตรงกับข้อเท็จจริงทั้งหมดหรือไม่ และมีมุมมองอื่นที่เป็นไปได้หรือเปล่า',
            '3. ฝึกปรับพฤติกรรม ทดลองทำกิจกรรมที่เหมาะสมทีละขั้น เช่น แบ่งงานเป็นส่วนเล็ก ๆ หรือกลับไปทำกิจกรรมที่เคยสนใจ',
            '4. ทบทวนผลและฝึกต่อเนื่อง นำทักษะที่เรียนรู้ไปใช้ในชีวิตจริง โดยมีผู้บำบัดช่วยแนะนำตามความเหมาะสม'
        ]
    ],
    [
        'heading' => 'ใครบ้างที่อาจได้รับประโยชน์จาก CBT?',
        'paragraphs' => [
            'CBT เป็นแนวทางการบำบัดที่ใช้กับปัญหาสุขภาพจิตหลายประเภท รวมถึงภาวะซึมเศร้าและโรควิตกกังวล อย่างไรก็ตาม วิธีการรักษาที่เหมาะสมขึ้นอยู่กับอาการ ความต้องการ และการประเมินของผู้เชี่ยวชาญ',
            'การเข้ารับการบำบัดควรดำเนินการร่วมกับผู้เชี่ยวชาญด้านสุขภาพจิต และอาจใช้ร่วมกับการรักษารูปแบบอื่นตามคำแนะนำของแพทย์'
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> | MindCare</title>

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Tahoma, Arial, sans-serif;
    color: #17264b;
    background: #ffffff;
    line-height: 1.8;
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
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    flex-wrap: wrap;
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

/* Intro */
.page-hero {
    background: linear-gradient(135deg, #e7f3f5, #edf1fb);
    text-align: center;
    padding: 38px 20px;
}

.page-hero h1 {
    color: #173b65;
    font-size: 30px;
    line-height: 1.5;
    margin: 0 0 8px;
}

.page-hero p {
    color: #657187;
    margin: 0;
}

/* Article */
.page-container {
    max-width: 1000px;
    width: 100%;
    margin: 35px auto;
    padding: 0 24px;
}

.back-link {
    display: inline-block;
    margin-bottom: 25px;
    color: #27847d;
    text-decoration: none;
}

.article-title {
    color: #173b65;
    font-size: 28px;
    line-height: 1.5;
    margin: 0 0 20px;
}

.article-image {
    display: block;
    width: 100%;
    max-width: 500px;
    height: 240px;
    object-fit: cover;
    border-radius: 12px;
    margin: 0 auto 30px;
}

.article-section {
    margin-bottom: 30px;
}

.article-section h2 {
    color: #173b65;
    font-size: 22px;
    margin: 0 0 12px;
}

.article-section p {
    color: #3d4c60;
    font-size: 16px;
    line-height: 2;
    margin: 0 0 16px;
    overflow-wrap: anywhere;
}

.info-box {
    background: #e6f4f0;
    border-left: 4px solid #318b88;
    border-radius: 8px;
    padding: 20px 22px;
    margin: 30px 0;
}

.info-box h3 {
    color: #245e58;
    margin: 0 0 8px;
}

.info-box p {
    margin: 0;
    color: #345b57;
}

.source-box {
    margin-top: 30px;
    padding-top: 18px;
    border-top: 1px solid #dce5ed;
    color: #657187;
    font-size: 14px;
}

.source-box a {
    color: #27847d;
    overflow-wrap: anywhere;
}

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
    color: white;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 5px 18px rgba(0,0,0,0.18);
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

.footer p,
.footer a {
    color: #d5e2f0;
    font-size: 14px;
    line-height: 1.8;
}

.footer a {
    display: block;
    text-decoration: none;
    margin-bottom: 10px;
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
@media (max-width: 1050px) {
    .header {
        align-items: flex-start;
        flex-direction: column;
        gap: 12px;
    }

    .nav {
        width: 100%;
        justify-content: flex-start;
    }
}

@media (max-width: 768px) {
    .footer-container {
        grid-template-columns: 1fr;
        gap: 25px;
        padding: 35px 22px;
    }

    .page-container {
        padding: 0 18px;
    }

    .page-hero h1 {
        font-size: 25px;
    }

    .article-title {
        font-size: 24px;
    }

    .article-section h2 {
        font-size: 20px;
    }

    .article-section p {
        font-size: 15px;
    }

    .article-image {
        height: 220px;
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

    .nav a {
        font-size: 13px;
        padding: 9px 7px;
    }

    .chat-button {
        right: 15px;
        bottom: 15px;
        padding: 12px 16px;
    }
}
</style>
</head>

<body>

<div class="top-bar">
    <a href="tel:1323">☎ สายด่วนสุขภาพจิต 1323</a>
    &nbsp; | &nbsp; โทรฟรีตลอด 24 ชั่วโมง
</div>

<header class="header">
    <a href="index.php" class="brand">
        <img
            src="https://cdn.discordapp.com/attachments/1280536102024904777/1562841883238207519/Gemini_Generated_Image_lwbljvlwbljvlwbl-photoaidcom-cropped.jpg?ex=6adb756d&is=6ada23ed&hm=3c11eea0b45ac1f7c2c6ccacd5e9b57d753b0b52a8b74a45c0c124d488eca810&"
            alt="โลโก้ MindCare"
            class="logo-icon"
        >

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
        <a href="https://www.google.com/maps/search/?api=1&query=คลินิกจิตเวชใกล้ฉัน"
           target="_blank" rel="noopener noreferrer">
            <i class="fas fa-map-marker-alt"></i>
            ค้นหาคลินิกใกล้ฉัน
        </a>
    </nav>
</header>

<section class="page-hero">
    <h1>การบำบัดพฤติกรรมทางปัญญา (CBT)</h1>
    <p>เรียนรู้ความคิด เข้าใจอารมณ์ และปรับพฤติกรรมอย่างเหมาะสม</p>
</section>

<main class="page-container">

    <a href="index.php#about" class="back-link">
        ← กลับไปหน้าแรก
    </a>

    <h1 class="article-title">
        <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
    </h1>

    <img
        class="article-image"
        src="https://images.unsplash.com/photo-1573497620053-ea5300f94f21?auto=format&fit=crop&w=1000&q=80"
        alt="การพูดคุยเพื่อดูแลสุขภาพจิต"
    >

    <?php foreach ($content as $section): ?>
        <section class="article-section">

            <h2>
                <?= htmlspecialchars($section['heading'], ENT_QUOTES, 'UTF-8'); ?>
            </h2>

            <?php foreach ($section['paragraphs'] as $paragraph): ?>
                <p>
                    <?= htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endforeach; ?>

        </section>
    <?php endforeach; ?>

    <div class="info-box">
        <h3><i class="fas fa-heart"></i> สิ่งสำคัญที่ควรรู้</h3>
        <p>
            CBT ไม่ใช่การบังคับให้คิดบวกตลอดเวลา แต่เป็นการฝึกมองความคิด
            และสถานการณ์อย่างสมดุล เนื้อหานี้เป็นข้อมูลเบื้องต้น
            ไม่สามารถใช้แทนการประเมินหรือการรักษาจากผู้เชี่ยวชาญได้
        </p>
    </div>

    <div class="source-box">
        <strong>แหล่งข้อมูลเพิ่มเติม</strong>
        <p>
            National Institute of Mental Health (NIMH):
            <a href="https://www.nimh.nih.gov/health/topics/psychotherapies"
               target="_blank" rel="noopener noreferrer">
                ข้อมูลเกี่ยวกับจิตบำบัด
            </a>
        </p>
    </div>

</main>



<footer class="footer">
    <div class="footer-container">

        <div class="footer-brand">
            <h2><i class="fas fa-heart"></i> MindCare</h2>
            <p>ดูแลใจ เข้าใจตนเอง เริ่มต้นได้ที่นี่</p>
            <p>เว็บไซต์ให้ความรู้เกี่ยวกับภาวะซึมเศร้าและการดูแลสุขภาพจิต</p>
        </div>

        <div class="footer-menu">
            <h3>เมนูเว็บไซต์</h3>
            <a href="index.php">หน้าแรก</a>
            <a href="symptoms.php">อาการของภาวะซึมเศร้า</a>
            <a href="treatment.php">การรักษา</a>
            <a href="assessment.php">แบบประเมินสุขภาพใจ</a>
        </div>

        <div class="footer-contact">
            <h3>ช่องทางช่วยเหลือ</h3>
            <p><i class="fas fa-phone"></i> สายด่วนสุขภาพจิต 1323</p>
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