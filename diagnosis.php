
<?php
$title = 'การวินิจฉัยภาวะซึมเศร้า';

$content = [
    [
        'heading' => 'เกณฑ์การวินิจฉัย',
        'image' => 'https://images.unsplash.com/photo-1493836512294-502baa1986e2?auto=format&fit=crop&w=1200&q=80',
        'alt' => 'เกณฑ์การวินิจฉัยภาวะซึมเศร้า',
        'paragraphs' => [
            'อาการที่ใช้ประกอบการวินิจฉัยมี 9 ข้อ ได้แก่',
            '1. มีอารมณ์เศร้า หดหู่ หรือรู้สึกว่างเปล่า',
            '2. ความสนใจหรือความเพลิดเพลินในกิจกรรมต่าง ๆ ลดลง',
            '3. น้ำหนักลดลงหรือเพิ่มขึ้นอย่างชัดเจน หรือความอยากอาหารเปลี่ยนแปลง',
            '4. นอนไม่หลับหรือนอนมากกว่าปกติ',
            '5. กระวนกระวายหรือเชื่องช้าลง',
            '6. อ่อนเพลียหรือไม่มีแรง',
            '7. รู้สึกไร้ค่า หรือรู้สึกผิดมากเกินไป',
            '8. สมาธิลดลง หรือมีความยากลำบากในการคิดและตัดสินใจ',
            '9. คิดถึงความตาย คิดอยากตาย หรือมีความคิดทำร้ายตนเอง',
            'เกณฑ์การวินิจฉัย คือ มีอาการตั้งแต่ 5 ข้อขึ้นไป โดยต้องมีอาการข้อ 1 หรือข้อ 2 อย่างน้อยหนึ่งข้อ อาการเกิดขึ้นต่อเนื่องตั้งแต่ 2 สัปดาห์ขึ้นไป เป็นเกือบตลอดวันและเกือบทุกวัน และไม่ได้เกิดขึ้นเป็นช่วง ๆ'
        ]
    ],
    [
        'heading' => 'แพทย์วินิจฉัยอย่างไร',
        'image' => 'https://images.unsplash.com/photo-1573497620053-ea5300f94f21?auto=format&fit=crop&w=1200&q=80',
        'alt' => 'แพทย์ประเมินอาการ',
        'paragraphs' => [
            'แพทย์จะพิจารณาแยกโรคอื่นที่อาจทำให้เกิดอาการคล้ายภาวะซึมเศร้า รวมถึงโรคทางร่างกายและผลจากการใช้ยาบางชนิด เนื่องจากอาการดังกล่าวอาจมีสาเหตุจากโรคหรือยาที่ใช้อยู่ได้'
        ]
    ],
    [
        'heading' => 'โรคอื่นที่มีอาการคล้ายคลึงกับโรคซึมเศร้า',
        'paragraphs' => [
            'ภาวะอารมณ์เศร้าจากการปรับตัว อาจเกิดขึ้นหลังเผชิญเหตุการณ์หรือความเปลี่ยนแปลงในชีวิต โดยอารมณ์เศร้ามีความสัมพันธ์กับเหตุการณ์นั้น',
            'โรคอารมณ์สองขั้ว เป็นโรคที่ผู้ป่วยอาจมีช่วงอารมณ์ซึมเศร้าสลับกับช่วงอารมณ์ดีผิดปกติหรือมีพลังงานเพิ่มขึ้นมากกว่าปกติ',
            'โรควิตกกังวล อาจมีอาการกังวลมากเกินไป กระสับกระส่าย นอนไม่หลับ หรือมีปัญหาด้านสมาธิ ซึ่งบางอาการอาจคล้ายกับภาวะซึมเศร้า'
        ]
    ]
];

$diseases = [
    ['โรคสมองอักเสบ', 'โรคตับอักเสบ'],
    ['โรคระบบประสาท', 'เนื้องอกในสมอง'],
    ['โรคเอส แอล อี (SLE)', 'วัณโรค โรคเอดส์'],
    ['โรคฮอร์โมนไทรอยด์ต่ำ', 'โรคคุชชิ่ง'],
    ['โรคขาดไวตามิน (เช่น เพเลกรา เบอริเบอรี่)', '']
];

$medications = [
    ['ยาลดความดันเลือด', 'เช่น alphamethyldopa, clonidine, propanolol'],
    ['ยารักษาโรคพาร์กินสัน', 'เช่น levodopa, amantadine'],
    ['ยากลุ่มสเตียรอยด์และฮอร์โมน', 'เช่น ยาคุม, เพรดนิโซโลน'],
    ['ยารักษามะเร็ง', 'vincristine, vinblastine'],
    ['ยาอื่นๆ', 'เช่น ไซเมทิดีน, cyproheptadine']
];

$doctorSteps = [
    'สอบถามอาการและความเปลี่ยนแปลงที่เกิดขึ้น',
    'สอบถามระยะเวลาที่เกิดอาการและผลกระทบต่อชีวิตประจำวัน',
    'สอบถามประวัติสุขภาพกายและสุขภาพจิต',
    'สอบถามประวัติการใช้ยาและสารต่าง ๆ',
    'ตรวจร่างกายและพิจารณาการตรวจเพิ่มเติมตามความเหมาะสม',
    'ประเมินข้อมูลทั้งหมดเพื่อประกอบการวินิจฉัย'
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
    background: #f7fafc;
    line-height: 1.8;
}

a {
    text-decoration: none;
}

/* แถบด้านบน */
.top-bar {
    background: #111d43;
    color: #fff;
    padding: 9px 5%;
    text-align: right;
    font-size: 14px;
}

.top-bar a {
    color: #fff;
    font-weight: bold;
}

/* Header */
.header {
    background: #fff;
    padding: 14px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    border-bottom: 1px solid #e0e7ef;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #173b65;
}

.logo-icon {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    object-fit: contain;
}

.brand-text span {
    display: block;
    font-size: 27px;
    font-weight: bold;
    line-height: 1.4;
}

.brand-text small {
    color: #657187;
    font-size: 11px;
}

.nav {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.nav a {
    color: #17264b;
    padding: 9px 10px;
    border-radius: 8px;
    font-size: 14px;
}

.nav a:hover,
.nav a.active {
    background: #e6f2ef;
    color: #087c69;
}

/* ส่วนหัวของหน้า */
.page-hero {
    background: linear-gradient(135deg, #e5f3f4, #edf1fb);
    text-align: center;
    padding: 45px 20px;
}

.page-hero h1 {
    color: #173b65;
    font-size: 30px;
    line-height: 1.5;
    margin: 0;
}

.page-hero p {
    color: #53657a;
    margin: 8px 0 0;
}

/* เนื้อหาหลัก */
.page-container {
    max-width: 1000px;
    width: 100%;
    margin: 35px auto 60px;
    padding: 0 24px;
}

.back-link {
    display: inline-block;
    color: #27847d;
    margin-bottom: 20px;
    font-weight: bold;
}

.article-title {
    font-size: 27px;
    color: #173b65;
    margin: 0 0 30px;
}

/* กล่องเนื้อหาแต่ละหัวข้อ */
.article-section {
    background: #fff;
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 30px;
    box-shadow: 0 4px 18px rgba(30, 60, 90, 0.06);
    border: 1px solid #e7edf3;
}

.article-section h2 {
    color: #173b65;
    font-size: 23px;
    margin: 0 0 20px;
    line-height: 1.6;
}

/* รูปอยู่ด้านบนข้อความ */
.article-image {
    display: block;
    width: 100%;
    height: 320px;
    object-fit: cover;
    border-radius: 12px;
    margin: 0 0 25px;
}

.article-section p {
    color: #3d4c60;
    font-size: 16px;
    line-height: 2;
    margin: 0 0 14px;
    overflow-wrap: anywhere;
}

.article-section h3 {
    color: #245e58;
    font-size: 19px;
    margin: 28px 0 12px;
}

/* ตาราง */
.table-wrapper {
    width: 100%;
    overflow-x: auto;
    margin: 15px 0 25px;
    border-radius: 8px;
}

.data-table {
    width: 100%;
    min-width: 320px;
    border-collapse: collapse;
    background: #fff;
    font-size: 15px;
}

.data-table th,
.data-table td {
    border: 1px solid #dce5ed;
    padding: 12px;
    text-align: left;
    vertical-align: top;
}

.data-table th {
    background: #e6f4f0;
    color: #173b65;
}

.data-table tr:nth-child(even) {
    background: #f7fafc;
}

/* กล่องแจ้งเตือน */
.info-box {
    background: #edf7f5;
    border-left: 4px solid #27847d;
    padding: 16px 20px;
    border-radius: 8px;
    margin-top: 22px;
}

.info-box p {
    margin: 0;
}

/* Footer */
.footer {
    background: #102d50;
    color: #fff;
    margin-top: 50px;
}

.footer-container {
    max-width: 1200px;
    margin: auto;
    padding: 42px 30px;
    display: grid;
    grid-template-columns: 1.5fr 1fr 1.2fr;
    gap: 35px;
}

.footer h2 {
    font-size: 26px;
    margin: 0 0 12px;
}

.footer h3 {
    font-size: 18px;
    color: #bfdbfe;
    margin: 0 0 15px;
}

.footer p,
.footer a {
    color: #d5e2f0;
    font-size: 14px;
    line-height: 1.9;
}

.footer a {
    display: block;
    margin-bottom: 8px;
}

.footer a:hover {
    color: #93c5fd;
}

.footer-bottom {
    background: #0b223d;
    padding: 14px 20px;
    text-align: center;
}

.footer-bottom p {
    margin: 0;
    font-size: 13px;
}

/* ปุ่มแชต */
.chat-button {
    position: fixed;
    right: 22px;
    bottom: 22px;
    z-index: 1000;
    border: none;
    border-radius: 50px;
    padding: 14px 20px;
    background: #173b65;
    color: #fff;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 5px 18px rgba(0,0,0,0.18);
}

/* รองรับมือถือ */
@media (max-width: 768px) {
    .header {
        flex-direction: column;
        align-items: flex-start;
    }

    .nav {
        width: 100%;
    }

    .nav a {
        font-size: 13px;
        padding: 8px;
    }

    .page-hero h1 {
        font-size: 25px;
    }

    .page-container {
        padding: 0 15px;
    }

    .article-section {
        padding: 20px 17px;
    }

    .article-section h2 {
        font-size: 21px;
    }

    .article-image {
        height: 230px;
    }

    .footer-container {
        grid-template-columns: 1fr;
        padding: 35px 22px;
        gap: 25px;
    }
}

@media (max-width: 480px) {
    .top-bar {
        text-align: center;
        font-size: 12px;
    }

    .brand-text span {
        font-size: 23px;
    }

    .brand-text small {
        font-size: 10px;
    }

    .article-title {
        font-size: 23px;
    }

    .article-section p {
        font-size: 15px;
    }

    .article-image {
        height: 200px;
    }

    .chat-button {
        right: 12px;
        bottom: 12px;
        padding: 12px 15px;
        font-size: 13px;
    }
}
</style>
</head>

<body>

<!-- แถบสายด่วน -->
<div class="top-bar">
    <a href="tel:1323">☎ สายด่วนสุขภาพจิต 1323</a>
    &nbsp; | &nbsp; โทรฟรีตลอด 24 ชั่วโมง
</div>

<!-- Header และเมนู -->
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
            <i class="fas fa-map-marker-alt"></i> ค้นหาคลินิกใกล้ฉัน
        </a>
    </nav>
</header>

<!-- หัวข้อหน้า -->
<section class="page-hero">
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p>ทำความเข้าใจหลักเกณฑ์และขั้นตอนการประเมินโดยแพทย์</p>
</section>

<!-- เนื้อหา -->
<main class="page-container">

    <a href="index.php#about" class="back-link">
        ← กลับไปหน้าแรก
    </a>

    <h1 class="article-title">
        <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
    </h1>

    <?php foreach ($content as $index => $section): ?>
        <section class="article-section">

            <h2>
                <?= htmlspecialchars($section['heading'], ENT_QUOTES, 'UTF-8'); ?>
            </h2>

            <?php if (isset($section['image'])): ?>
                <img
                    class="article-image"
                    src="<?= htmlspecialchars($section['image'], ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?= htmlspecialchars($section['alt'], ENT_QUOTES, 'UTF-8'); ?>"
                    loading="lazy"
                >
            <?php endif; ?>

            <?php foreach ($section['paragraphs'] as $paragraph): ?>
                <p><?= htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endforeach; ?>

            <?php if ($index === 1): ?>

                <h3>โรคที่อาจทำให้เกิดอาการคล้ายภาวะซึมเศร้า</h3>

                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>โรค</th>
                                <th>ตัวอย่าง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diseases as $disease): ?>
                                <tr>
                                    <td><?= htmlspecialchars($disease[0], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($disease[1], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3>ยาที่อาจเกี่ยวข้อง</h3>

                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ประเภทของยา</th>
                                <th>ตัวอย่าง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medications as $medication): ?>
                                <tr>
                                    <td><?= htmlspecialchars($medication[0], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($medication[1], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3>ขั้นตอนการประเมินของแพทย์</h3>

                <?php foreach ($doctorSteps as $step): ?>
                    <p><?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endforeach; ?>

                <div class="info-box">
                    <p>
                        เกณฑ์การวินิจฉัยเป็นเพียงแนวทางเบื้องต้น
                        แพทย์ต้องพิจารณาข้อมูลและองค์ประกอบหลายด้านร่วมกัน
                        การวินิจฉัยที่แน่นอนจึงควรได้รับการประเมินจากแพทย์
                    </p>
                </div>

            <?php endif; ?>

        </section>
    <?php endforeach; ?>

</main>



<!-- Footer -->
<footer class="footer">
    <div class="footer-container">

        <div>
            <h2><i class="fas fa-heart"></i> MindCare</h2>
            <p>ดูแลใจ เข้าใจตนเอง เริ่มต้นได้ที่นี่</p>
            <p>เว็บไซต์ให้ความรู้เกี่ยวกับภาวะซึมเศร้าและการดูแลสุขภาพจิต</p>
        </div>

        <div>
            <h3>เมนูเว็บไซต์</h3>
            <a href="index.php">หน้าแรก</a>
            <a href="symptoms.php">อาการของภาวะซึมเศร้า</a>
            <a href="treatment.php">การรักษาและการดูแลตัวเองเบื้องต้น</a>
            <a href="assessment.php">แบบประเมินสุขภาพใจ</a>
        </div>

        <div>
            <h3>ช่องทางช่วยเหลือ</h3>
            <p><i class="fas fa-phone"></i> สายด่วนสุขภาพจิต 1323</p>
            <a href="https://www.google.com/maps/search/?api=1&query=คลินิกจิตเวชใกล้ฉัน"
               target="_blank" rel="noopener noreferrer">
                <i class="fas fa-map-marker-alt"></i> ค้นหาคลินิกใกล้ฉัน
            </a>
            <p>ข้อมูลเพื่อการศึกษา ไม่ใช้แทนการวินิจฉัยจากแพทย์</p>
        </div>

    </div>

    <div class="footer-bottom">
        <p>
            จัดทำขึ้นเพื่อเป็นส่วนหนึ่งของโครงงานทางการศึกษา
            เพื่อเผยแพร่ความรู้เกี่ยวกับภาวะซึมเศร้าและการดูแลสุขภาพจิต
        </p>
    </div>
    <?php include __DIR__ . '/may_chat_widget.php'; ?>
</footer>

</body>
</html>
