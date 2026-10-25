<?php
require_once __DIR__ . '/mc_assessment_lib.php';
$slug = trim((string)($_GET['slug'] ?? $_POST['slug'] ?? ''));
if ($slug === '') { http_response_code(400); exit('ไม่พบแบบประเมิน'); }
$aRes = mc_query('mc_assessments', 'select=*&slug=eq.' . rawurlencode($slug) . '&status=eq.published&limit=1');
if (!$aRes['ok'] || empty($aRes['data'][0])) { http_response_code(404); exit('ไม่พบแบบประเมิน หรือยังไม่ได้เผยแพร่'); }
$a = $aRes['data'][0]; $aid = (int)$a['id'];
$qRes = mc_query('mc_assessment_questions', 'select=id,question_code,question_text,help_text,sort_order,is_required&assessment_id=eq.' . $aid . '&order=sort_order.asc');
$questions = $qRes['ok'] ? $qRes['data'] : [];
$optionMap = [];
if ($questions) {
  $ids = array_map(fn($q)=>(int)$q['id'], $questions);
  $oRes = mc_query(
    'mc_assessment_options',
    'select=id,question_id,option_label,score,sort_order&question_id=in.('
    . implode(',', $ids)
    . ')&order=sort_order.asc'
);
  if ($oRes['ok']) foreach ($oRes['data'] as $o) $optionMap[(int)$o['question_id']][] = $o;
}
$result = null; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $answers = $_POST['answer'] ?? [];
  if (!$questions) $error = 'แบบประเมินนี้ยังไม่มีคำถามที่ตรวจสอบแล้ว';
  else {
    $total = 0; $count = 0; $missing = false;
    foreach ($questions as $q) {
      $qid = (int)$q['id'];
      if (!isset($answers[$qid]) || !isset($optionMap[$qid])) { if (!empty($q['is_required'])) $missing = true; continue; }
      $valid = false;
      foreach ($optionMap[$qid] as $op) if ((string)$op['id'] === (string)$answers[$qid]) { $total += (int)$op['score']; $valid = true; break; }
      if ($valid) $count++;
    }
    if ($missing || $count === 0) $error = 'กรุณาตอบคำถามที่จำเป็นให้ครบทุกข้อ';
    else {
      $score = ($a['scoring_method'] ?? 'sum') === 'average' ? $total / $count : $total;
     $bRes = mc_query(
    'mc_assessment_bands',
    'select=min_score,max_score,result_label,interpretation,recommendation,severity_level'
    . '&assessment_id=eq.' . $aid
    . '&min_score=lte.' . rawurlencode((string)$score)
    . '&max_score=gte.' . rawurlencode((string)$score)
    . '&order=sort_order.asc&limit=1'
);

$band = ($bRes['ok'] && !empty($bRes['data'][0]))
    ? $bRes['data'][0]
    : null;
      $result = ['score'=>$score,'band'=>$band,'count'=>$count];
    }
  }
}
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= mc_h($a['title']) ?> | MindCare</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Tahoma,Arial,sans-serif;color:#17264b;background:#f5f8fc;line-height:1.8}.top{background:#111d43;color:#fff;padding:15px 5%}.top a{color:#fff;text-decoration:none}main{max-width:850px;margin:30px auto;padding:0 18px}.panel{background:#fff;border:1px solid #dce8f3;border-radius:14px;padding:26px;margin-bottom:18px}.panel h1{font-size:27px;color:#173b65;margin:0 0 8px}.sub{color:#586981;font-size:14px}.q{padding:18px 0;border-bottom:1px solid #e5ebf2}.q:last-child{border-bottom:0}.q legend{font-weight:bold;margin-bottom:10px}.opt{display:flex;gap:10px;align-items:flex-start;padding:8px 10px;border-radius:8px}.opt:hover{background:#f0f7ff}.btn{border:0;background:#173b65;color:white;padding:12px 18px;border-radius:8px;font-size:15px;cursor:pointer}.error,.notice{padding:14px;border-radius:8px;margin:12px 0}.error{background:#fee2e2;color:#991b1b}.notice{background:#fff8e5;color:#6b4f0b}.score{font-size:24px;font-weight:bold}.result-high{border-left:5px solid #c62828}.result-moderate{border-left:5px solid #d6a43b}.result-low{border-left:5px solid #318b88}
</style></head><body><div class="top"><a href="assessment.php">← กลับไปหน้าแบบประเมิน</a></div><main>
<section class="panel"><h1><?= mc_h($a['title']) ?></h1><div class="sub"><?= mc_h($a['description']) ?></div><div class="notice">แบบประเมินนี้เป็นเครื่องมือคัดกรองเบื้องต้น ไม่ใช่การวินิจฉัยโรค และไม่ควรใช้แทนคำแนะนำจากผู้เชี่ยวชาญ</div></section>
<?php if ($error): ?><div class="error"><?= mc_h($error) ?></div><?php endif; ?>

<?php if ($result): ?>
<?php
    $band = $result['band'] ?? null;
    $severity = $band['severity_level'] ?? 'low';

    $resultClass = in_array($severity, ['high', 'urgent'], true)
        ? 'result-high'
        : ($severity === 'moderate' ? 'result-moderate' : 'result-low');
?>
<section class="panel <?= mc_h($resultClass) ?>">
    <h2>ผลการประเมินเบื้องต้น</h2>

    <p class="score">
        คะแนน:
        <?= rtrim(rtrim(number_format((float)$result['score'], 2, '.', ''), '0'), '.') ?>
    </p>

    <?php if ($band): ?>
        <h3><?= mc_h($band['result_label'] ?? '') ?></h3>

        <?php if (!empty($band['interpretation'])): ?>
            <p><?= nl2br(mc_h($band['interpretation'])) ?></p>
        <?php endif; ?>

        <?php if (!empty($band['recommendation'])): ?>
            <h3>คำแนะนำ</h3>
            <p><?= nl2br(mc_h($band['recommendation'])) ?></p>
        <?php endif; ?>

    <?php else: ?>
        <p>
            ไม่พบเกณฑ์แปลผลสำหรับคะแนนนี้
            กรุณาติดต่อผู้ดูแลเว็บไซต์
        </p>
    <?php endif; ?>

    <p>
        ผลนี้เป็นเพียงการคัดกรองเบื้องต้น
        ไม่ใช่การวินิจฉัยโรค
    </p>

    <p>
        <a href="https://www.google.com/maps/search/?api=1&query=คลินิกจิตเวชใกล้ฉัน"
           target="_blank" rel="noopener noreferrer">
            ค้นหาคลินิกจิตเวชใกล้ฉัน
        </a>
        ·
        <a href="tel:1323">สายด่วนสุขภาพจิต 1323</a>
    </p>

    <a href="assessment.php">กลับไปหน้าแบบประเมิน</a>
</section>
<?php elseif (!$questions): ?>
<section class="panel"><p>แบบประเมินนี้ยังไม่มีคำถามที่พร้อมใช้งาน หรือยังไม่ได้รับการตรวจสอบจากผู้ดูแลระบบ</p></section>
<?php else: ?><form method="post" class="panel"><input type="hidden" name="slug" value="<?= mc_h($slug) ?>">
<?php foreach ($questions as $i=>$q): $qid=(int)$q['id']; ?><fieldset class="q" style="border:0;margin:0;padding-left:0;padding-right:0"><legend><?= ($i+1) . '. ' . mc_h($q['question_text']) ?><?= !empty($q['is_required']) ? ' *' : '' ?></legend><?php if (!empty($q['help_text'])): ?><div class="sub"><?= mc_h($q['help_text']) ?></div><?php endif; ?>
<?php foreach (($optionMap[$qid] ?? []) as $op): ?><label class="opt"><input type="radio" name="answer[<?= $qid ?>]" value="<?= (int)$op['id'] ?>" <?= !empty($q['is_required']) ? 'required' : '' ?>> <span><?= mc_h($op['option_label']) ?></span></label><?php endforeach; ?>
<?php if (empty($optionMap[$qid])): ?><p class="error">คำถามนี้ยังไม่มีตัวเลือกคำตอบที่ตั้งค่าไว้</p><?php endif; ?></fieldset><?php endforeach; ?>
<button class="btn" type="submit">ส่งคำตอบและดูผล</button></form><?php endif; ?>
</main></body></html>