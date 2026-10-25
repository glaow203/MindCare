<?php
require_once __DIR__ . '/supabase_config.php';

if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

function getPublishedAssessments(): array {
    if (
        !defined('SUPABASE_URL') ||
        !defined('SUPABASE_SECRET_KEY') ||
        trim((string)SUPABASE_URL) === '' ||
        trim((string)SUPABASE_SECRET_KEY) === '' ||
        !function_exists('curl_init')
    ) {
        error_log('MindCare assessment page: Supabase config or cURL is unavailable.');
        return [];
    }

    $url = rtrim((string)SUPABASE_URL, '/') .
        '/rest/v1/assessments?select=id,code,slug,title,short_title,description,audience,assessment_type,source_url,source_name,status,instructions,estimated_minutes,sort_order,safety_notice' .
        '&status=eq.published&order=sort_order.asc,id.asc';

    $key = trim((string)SUPABASE_SECRET_KEY);
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
        error_log('MindCare assessment page cURL error: ' . curl_error($ch));
        curl_close($ch);
        return [];
    }
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        error_log('MindCare assessment page Supabase HTTP status: ' . $status . ' body: ' . $response);
        return [];
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : [];
}

$assessments = getPublishedAssessments();
$generalCount = 0;
$studentCount = 0;
foreach ($assessments as $assessment) {
    $audience = is_array($assessment['audience'] ?? null) ? $assessment['audience'] : [];
    if (in_array('general', $audience, true)) $generalCount++;
    if (in_array('student', $audience, true)) $studentCount++;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>แบบประเมินสุขภาพใจ | MindCare</title>
<meta name="description" content="แบบประเมินสุขภาพใจเบื้องต้นสำหรับบุคคลทั่วไป นักเรียน และนักศึกษา โดย MindCare">
<style>
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;font-family:Tahoma,Arial,sans-serif;color:#17264b;background:#fff;line-height:1.8}
a{transition:color .2s,background .2s}
.top-bar{min-height:46px;background:#111d43;color:#fff;display:flex;justify-content:flex-end;align-items:center;padding:10px 4%;font-size:14px}
.top-bar a{color:#fff;text-decoration:none;font-weight:bold}.top-bar a:hover{color:#bfdbfe}
.header{min-height:100px;display:flex;align-items:center;justify-content:space-between;gap:24px;padding:12px 4%;background:#fff;border-bottom:1px solid #dce3ed}
.brand{display:flex;align-items:center;gap:12px;text-decoration:none;flex-shrink:0}
.logo-icon{width:80px;height:80px;object-fit:contain;border-radius:50%;flex-shrink:0}
.brand-text span{display:block;color:#111d43;font-size:27px;font-weight:bold}
.brand-text small{display:block;margin-top:4px;color:#657187;font-size:11px}
.nav{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:4px}
.nav a{color:#17264b;text-decoration:none;font-size:14px;padding:12px 9px;border-radius:8px;white-space:nowrap}
.nav a:hover,.nav a.active{background:#e6f2ef;color:#087c69}
.page-intro{padding:60px 7% 48px;background:linear-gradient(135deg,#f0f7ff,#e6f4f1)}
.intro-inner{max-width:1100px;margin:0 auto}
.hero-tag{display:inline-block;background:#dcefe9;color:#267565;padding:8px 15px;border-radius:30px;font-size:14px;margin-bottom:16px}
.page-intro h1{font-size:38px;line-height:1.45;color:#173b65;margin:0 0 16px}
.page-intro h1 span{color:#318b88}
.page-intro p{max-width:920px;color:#586981;font-size:16px;line-height:2;margin:0 0 12px}
.health-note{margin-top:20px;padding:15px 19px;background:rgba(255,255,255,.82);border-left:5px solid #318b88;border-radius:9px;color:#43546a;font-size:14px}
.content-section{max-width:1200px;margin:0 auto;padding:42px 30px 60px}
.section-title{color:#173b65;font-size:29px;line-height:1.5;margin:0 0 8px;padding-left:14px;border-left:5px solid #318b88}
.section-description{color:#586981;font-size:15px;margin:0 0 25px}
.assessment-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));align-items:stretch;gap:20px}
.assessment-card{min-width:0;background:#f0f7ff;border:1px solid #dce8f3;border-top:4px solid #318b88;border-radius:14px;overflow:hidden;box-shadow:0 5px 16px rgba(23,59,101,.06);display:flex;flex-direction:column}
.card-banner{height:112px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#e1f3ef,#eaf2ff);color:#173b65;font-size:34px;font-weight:bold}
.card-body{padding:19px;display:flex;flex-direction:column;flex:1}
.card-label{color:#27847d;font-size:12px;font-weight:bold;margin-bottom:5px}
.assessment-card h2{color:#216b63;font-size:19px;line-height:1.6;margin:0 0 9px;overflow-wrap:anywhere}
.assessment-card p{color:#43546a;font-size:14px;line-height:1.8;margin:0 0 14px;overflow-wrap:anywhere}
.card-meta{font-size:12px;color:#6b7890;margin-top:auto;margin-bottom:15px}
.audience-tags{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px}
.audience-tags span{font-size:11px;background:#e0eee9;color:#286c62;border-radius:20px;padding:4px 8px}
.card-button{display:inline-flex;align-items:center;justify-content:center;align-self:flex-start;padding:10px 16px;background:#173b65;color:#fff;border-radius:8px;text-decoration:none;font-size:14px;font-weight:bold;border:0;cursor:pointer}
.card-button:hover{background:#27847d}
.card-button.disabled{background:#dbe3ec;color:#59677c;cursor:not-allowed}
.empty-state{background:#f8fbff;border:1px dashed #b9cbd9;border-radius:12px;padding:28px;text-align:center;color:#586981}
.empty-state h2{color:#173b65;margin-top:0}
.footer{background:#102d50;color:#fff;margin-top:35px}
.footer-container{max-width:1200px;margin:0 auto;padding:42px 30px;display:grid;grid-template-columns:1.5fr 1fr 1.2fr;gap:40px}
.footer h2{font-size:26px;margin:0 0 12px}.footer h3{font-size:18px;margin:0 0 18px;color:#bfdbfe}
.footer p{color:#d5e2f0;font-size:14px;line-height:1.8}
.footer a{display:block;color:#d5e2f0;text-decoration:none;font-size:14px;margin-bottom:10px}.footer a:hover{color:#93c5fd}
.footer-bottom{background:#0b223d;text-align:center;padding:12px 20px}.footer-bottom p{margin:0;font-size:13px}
.chat-button{position:fixed;right:25px;bottom:25px;z-index:1000;border:0;border-radius:50px;padding:14px 20px;background:#173b65;color:#fff;font-size:15px;font-weight:bold;cursor:pointer;box-shadow:0 5px 18px #0003}
.chat-button:hover{background:#27847d}
@media(max-width:1100px){.header{align-items:flex-start;flex-direction:column;gap:12px}.nav{justify-content:flex-start;width:100%}}
@media(max-width:900px){.assessment-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.footer-container{grid-template-columns:repeat(2,minmax(0,1fr))}.footer-brand{grid-column:1/-1}}
@media(max-width:768px){.page-intro{padding:42px 6%}.page-intro h1{font-size:31px}.footer-container{grid-template-columns:1fr;gap:24px;padding:34px 22px}.footer-brand{grid-column:auto}}
@media(max-width:600px){.top-bar{justify-content:center;flex-wrap:wrap;text-align:center;font-size:12px}.header{padding:12px 5%}.logo-icon{width:62px;height:62px}.brand-text span{font-size:23px}.nav{gap:3px}.nav a{font-size:13px;padding:10px 8px;white-space:normal}.assessment-grid{grid-template-columns:1fr}.content-section{padding:34px 20px}.section-title{font-size:25px}.chat-button{right:15px;bottom:15px;padding:12px 16px;font-size:14px}}
</style>
</head>
<body>
<div class="top-bar">
  <a href="tel:1323">☎ สายด่วนสุขภาพจิต 1323</a>
  <span>&nbsp; | &nbsp; โทรฟรีตลอด 24 ชั่วโมง</span>
</div>

<header class="header">
  <a href="index.php" class="brand" aria-label="MindCare หน้าแรก">
    <img src="https://cdn.discordapp.com/attachments/1280536102024904777/1562841883238207519/Gemini_Generated_Image_lwbljvlwbljvlwbl-photoaidcom-cropped.jpg?ex=6adcc6ed&is=6adb756d&hm=becd715ca32e74f6d811b9be0753a6d1c75e4dbff459949343e5d8c6f84c9272&" alt="โลโก้ MindCare" class="logo-icon">
    <div class="brand-text"><span>MindCare</span><small>เว็บไซต์ให้ความรู้เกี่ยวกับภาวะซึมเศร้า</small></div>
  </a>
  <nav class="nav" aria-label="เมนูหลัก">
    <a href="index.php">หน้าแรก</a>
    <a href="symptoms.php">อาการและสาเหตุของโรคซึมเศร้า</a>
    <a href="treatment.php">การรักษาและการดูแลตัวเองเบื้องต้น</a>
    <a href="news.php">ข่าวสารและบทความ</a>
    <a href="assessment.php" class="active">แบบประเมินสุขภาพใจ</a>
    <a href="https://www.google.com/maps/search/?api=1&amp;query=คลินิกจิตเวชใกล้ฉัน" target="_blank" rel="noopener noreferrer">ค้นหาคลินิกใกล้ฉัน</a>
  </nav>
</header>

<main>
  <section class="page-intro">
    <div class="intro-inner">
      <span class="hero-tag">สำรวจและทำความเข้าใจตนเอง</span>
      <h1>แบบประเมิน<br><span>สุขภาพใจ</span></h1>
      <p>เลือกทำแบบประเมินที่เหมาะกับสิ่งที่คุณอยากสำรวจ เช่น อารมณ์ ความเครียด ความวิตกกังวล การนอนหลับ หรือความเข้มแข็งทางใจ</p>
      <p>MindCare ออกแบบพื้นที่นี้สำหรับบุคคลทั่วไป นักเรียน และนักศึกษา เพื่อช่วยให้เข้าใจสุขภาพใจเบื้องต้นและพิจารณาว่าควรขอคำปรึกษาเพิ่มเติมหรือไม่</p>
      <div class="health-note"><strong>ข้อควรรู้:</strong> แบบประเมินเป็นเพียงเครื่องมือคัดกรองเบื้องต้น ไม่ใช่การวินิจฉัยโรค หากมีอาการรุนแรงหรือกระทบชีวิตประจำวัน ควรปรึกษาผู้เชี่ยวชาญ หากคุณหรือคนใกล้ตัวกำลังเสี่ยงอันตรายทันที ให้ติดต่อบริการฉุกเฉินหรือไปห้องฉุกเฉิน</div>
    </div>
  </section>

  <section class="content-section" id="assessment-list">
    <h2 class="section-title">เลือกแบบประเมิน</h2>
    <p class="section-description">แบบประเมินที่เผยแพร่โดยผู้ดูแลระบบจะแสดงในส่วนนี้</p>

    <?php if (!empty($assessments)): ?>
      <div class="assessment-grid">
        <?php foreach ($assessments as $item): ?>
          <?php
            $audience = is_array($item['audience'] ?? null) ? $item['audience'] : [];
            $typeLabel = ($item['assessment_type'] ?? '') === 'standard' ? 'แบบประเมินมาตรฐาน' : 'แบบประเมิน MindCare';
            $minutes = isset($item['estimated_minutes']) && $item['estimated_minutes'] !== '' ? (int)$item['estimated_minutes'] : null;
            $initials = strtoupper(substr((string)($item['code'] ?? 'MC'), 0, 8));
          ?>
          <article class="assessment-card">
            <div class="card-banner" aria-hidden="true"><?= h($initials) ?></div>
            <div class="card-body">
              <div class="card-label"><?= h($typeLabel) ?></div>
              <h2><?= h($item['title'] ?? '') ?></h2>
              <p><?= h(trim((string)($item['description'] ?? '')) !== '' ? $item['description'] : 'อ่านคำแนะนำก่อนเริ่มทำแบบประเมินนี้') ?></p>
              <div class="audience-tags">
                <?php if (in_array('general', $audience, true)): ?><span>บุคคลทั่วไป</span><?php endif; ?>
                <?php if (in_array('student', $audience, true)): ?><span>นักเรียน/นักศึกษา</span><?php endif; ?>
              </div>
              <div class="card-meta">
                <?= $minutes !== null ? 'ใช้เวลาประมาณ ' . $minutes . ' นาที' : 'เวลาในการทำขึ้นอยู่กับจำนวนคำถาม' ?>
                <?php if (!empty($item['source_name'])): ?><br>แหล่งอ้างอิง: <?= h($item['source_name']) ?><?php endif; ?>
              </div>
              <?php if (!empty($item['source_url']) && ($item['assessment_type'] ?? '') === 'standard'): ?>
                

<a class="card-button"
   href="assessment_take.php?slug=<?= rawurlencode((string)$item['slug']) ?>">
    เริ่มทำแบบประเมิน →
</a>


              <?php else: ?>
                <span class="card-button disabled" aria-disabled="true">ระบบทำแบบประเมินกำลังพัฒนา</span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <h2>ยังไม่มีแบบประเมินที่เผยแพร่</h2>
        <p>เมื่อผู้ดูแลระบบเพิ่มแบบประเมิน ตรวจสอบข้อมูล และเผยแพร่จากหน้า Admin แล้ว รายการจะปรากฏที่นี่โดยอัตโนมัติ</p>
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
  <div class="footer-bottom"><p>จัดทำขึ้นเพื่อเป็นส่วนหนึ่งของโครงงานทางการศึกษา เพื่อเผยแพร่ความรู้เกี่ยวกับสุขภาพจิต</p></div>
</footer>
<?php include __DIR__ . '/may_chat_widget.php'; ?>
</body>
</html>
