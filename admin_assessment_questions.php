<?php
session_start();
require_once __DIR__ . '/supabase_config.php';

/* MindCare - จัดการคำถาม ตัวเลือก และเกณฑ์แปลผลแบบประเมิน */
if (
    empty($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    !defined('ADMIN_EMAIL') ||
    strtolower((string)($_SESSION['admin_email'] ?? '')) !== strtolower((string)ADMIN_EMAIL)
) {
    header('Location: admin.php');
    exit;
}

if (empty($_SESSION['assessment_questions_csrf'])) {
    $_SESSION['assessment_questions_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['assessment_questions_csrf'];

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function dbRequest(string $method, string $table, string $query = '', ?array $payload = null, ?string $prefer = null): array {
    if (
        !defined('SUPABASE_URL') || trim((string)SUPABASE_URL) === '' ||
        !defined('SUPABASE_SECRET_KEY') || trim((string)SUPABASE_SECRET_KEY) === '' ||
        !function_exists('curl_init')
    ) {
        return ['status' => 500, 'body' => 'กรุณาตรวจสอบ SUPABASE_URL, SUPABASE_SECRET_KEY และ PHP cURL'];
    }

    $key = trim((string)SUPABASE_SECRET_KEY);
    $url = rtrim((string)SUPABASE_URL, '/') . '/rest/v1/' . $table;
    if ($query !== '') $url .= '?' . $query;

    $headers = [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    if ($prefer !== null) $headers[] = 'Prefer: ' . $prefer;

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30
    ];
    if ($payload !== null) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return ['status' => 500, 'body' => 'แปลงข้อมูล JSON ไม่สำเร็จ'];
        $opts[CURLOPT_POSTFIELDS] = $json;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['status' => 500, 'body' => $err];
    }
    curl_close($ch);
    return ['status' => $status, 'body' => $body];
}

function rows(array $response): array {
    $data = json_decode((string)($response['body'] ?? ''), true);
    return is_array($data) ? $data : [];
}
function ok(array $response): bool {
    return isset($response['status']) && $response['status'] >= 200 && $response['status'] < 300;
}
function goAssessment(int $assessmentId, string $msg): void {
    header('Location: admin_assessment_questions.php?assessment_id=' . $assessmentId . '&msg=' . rawurlencode($msg));
    exit;
}

$errors = [];
$notice = '';
$assessmentId = filter_input(INPUT_GET, 'assessment_id', FILTER_VALIDATE_INT);
if (!$assessmentId && isset($_POST['assessment_id'])) $assessmentId = filter_var($_POST['assessment_id'], FILTER_VALIDATE_INT);
$assessmentId = $assessmentId ? (int)$assessmentId : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sentCsrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $sentCsrf)) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณากลับไปโหลดหน้าใหม่');
    }

    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save_question') {
        $id = filter_var($_POST['question_id'] ?? '', FILTER_VALIDATE_INT);
        $text = trim((string)($_POST['question_text'] ?? ''));
        $code = trim((string)($_POST['question_code'] ?? ''));
        $help = trim((string)($_POST['help_text'] ?? ''));
        $type = (string)($_POST['question_type'] ?? 'single_choice');
        $required = isset($_POST['is_required']);
        $safetyReview = isset($_POST['safety_review_required']);
        $sort = (int)($_POST['sort_order'] ?? 0);
        $optionsRaw = trim((string)($_POST['options_text'] ?? ''));

        if ($assessmentId < 1) $errors[] = 'กรุณาเลือกแบบประเมินก่อน';
        if ($text === '') $errors[] = 'กรุณากรอกข้อความคำถาม';
        if (!in_array($type, ['single_choice','multiple_choice','text','number'], true)) $errors[] = 'ชนิดคำถามไม่ถูกต้อง';

        $parsedOptions = [];
        if (in_array($type, ['single_choice','multiple_choice'], true)) {
           
foreach (preg_split('/\r\n|\r|\n/', $optionsRaw) as $line) {

                $line = trim($line);
                if ($line === '') continue;
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) < 3) {
                    $errors[] = 'รูปแบบตัวเลือกไม่ถูกต้อง: ' . $line . ' (ใช้ รูปแบบ label|value|score|safety_level)';
                    continue;
                }
                $label = $parts[0];
                $value = $parts[1] !== '' ? $parts[1] : $parts[0];
                $score = is_numeric($parts[2]) ? (float)$parts[2] : null;
                $safety = $parts[3] ?? 'none';
                if ($label === '' || $score === null || !in_array($safety, ['none','follow_up','urgent'], true)) {
                    $errors[] = 'ตรวจสอบ label, score และ safety_level ของตัวเลือก: ' . $line;
                    continue;
                }
                $parsedOptions[] = [
                    'option_label' => $label,
                    'option_value' => $value,
                    'score' => $score,
                    'safety_level' => $safety
                ];
            }
            if (!$parsedOptions) $errors[] = 'คำถามแบบเลือกตอบต้องมีตัวเลือกอย่างน้อย 1 ข้อ';
        }

        if (!$errors) {
            $payload = [
                'assessment_id' => $assessmentId,
                'question_code' => $code !== '' ? $code : null,
                'question_text' => $text,
                'help_text' => $help !== '' ? $help : null,
                'question_type' => $type,
                'is_required' => $required,
                'safety_review_required' => $safetyReview,
                'sort_order' => $sort
            ];

            if ($id) {
                $resp = dbRequest('PATCH', 'assessment_questions', 'id=eq.' . $id . '&assessment_id=eq.' . $assessmentId, $payload, 'return=representation');
                $questionId = $id;
            } else {
                $resp = dbRequest('POST', 'assessment_questions', '', $payload, 'return=representation');
                $inserted = rows($resp);
                $questionId = (int)($inserted[0]['id'] ?? 0);
            }

            if (!ok($resp) || $questionId < 1) {
                $detail = json_decode((string)($resp['body'] ?? ''), true);
                $errors[] = 'บันทึกคำถามไม่สำเร็จ (HTTP ' . (int)($resp['status'] ?? 0) . '): ' . (is_array($detail) ? (string)($detail['message'] ?? $detail['details'] ?? '') : '');
            } else {
                if (in_array($type, ['single_choice','multiple_choice'], true)) {
                    $del = dbRequest('DELETE', 'assessment_options', 'question_id=eq.' . $questionId, null, 'return=minimal');
                    if (!ok($del)) {
                        $errors[] = 'บันทึกคำถามแล้ว แต่ลบตัวเลือกเดิมไม่สำเร็จ กรุณาตรวจสอบสิทธิ์ตาราง assessment_options';
                    } else {
                        foreach ($parsedOptions as $i => $option) {
                            $option['question_id'] = $questionId;
                            $option['sort_order'] = $i;
                            $ins = dbRequest('POST', 'assessment_options', '', $option, 'return=minimal');
                            if (!ok($ins)) {
                                $errors[] = 'บันทึกคำถามแล้ว แต่บันทึกตัวเลือกบางข้อไม่สำเร็จ (HTTP ' . (int)$ins['status'] . ')';
                                break;
                            }
                        }
                    }
                } else {
                    dbRequest('DELETE', 'assessment_options', 'question_id=eq.' . $questionId, null, 'return=minimal');
                }
                if (!$errors) goAssessment($assessmentId, 'saved_question');
            }
        }
    } elseif ($action === 'delete_question') {
        $id = filter_var($_POST['question_id'] ?? '', FILTER_VALIDATE_INT);
        if ($id) {
            $resp = dbRequest('DELETE', 'assessment_questions', 'id=eq.' . (int)$id . '&assessment_id=eq.' . $assessmentId, null, 'return=minimal');
            if (ok($resp)) goAssessment($assessmentId, 'deleted_question');
            $errors[] = 'ลบคำถามไม่สำเร็จ (HTTP ' . (int)$resp['status'] . ')';
        }
    } elseif ($action === 'save_rule') {
        $ruleId = filter_var($_POST['rule_id'] ?? '', FILTER_VALIDATE_INT);
        $code = trim((string)($_POST['result_code'] ?? ''));
        $label = trim((string)($_POST['result_label'] ?? ''));
        $minRaw = trim((string)($_POST['min_score'] ?? ''));
        $maxRaw = trim((string)($_POST['max_score'] ?? ''));
        $severity = (string)($_POST['severity_level'] ?? 'informational');
        $interpretation = trim((string)($_POST['interpretation'] ?? ''));
        $recommendation = trim((string)($_POST['recommendation'] ?? ''));
        $sort = (int)($_POST['rule_sort_order'] ?? 0);
        if ($assessmentId < 1) $errors[] = 'กรุณาเลือกแบบประเมินก่อน';
        if ($code === '' || $label === '') $errors[] = 'กรุณากรอกรหัสผลลัพธ์และชื่อผลลัพธ์';
        if ($minRaw !== '' && !is_numeric($minRaw)) $errors[] = 'คะแนนต่ำสุดต้องเป็นตัวเลข';
        if ($maxRaw !== '' && !is_numeric($maxRaw)) $errors[] = 'คะแนนสูงสุดต้องเป็นตัวเลข';
        if ($minRaw !== '' && $maxRaw !== '' && (float)$minRaw > (float)$maxRaw) $errors[] = 'คะแนนต่ำสุดต้องไม่มากกว่าคะแนนสูงสุด';
        if (!in_array($severity, ['informational','low','moderate','high','urgent'], true)) $errors[] = 'ระดับความรุนแรงไม่ถูกต้อง';

        if (!$errors) {
            $payload = [
                'assessment_id' => $assessmentId,
                'min_score' => $minRaw === '' ? null : (float)$minRaw,
                'max_score' => $maxRaw === '' ? null : (float)$maxRaw,
                'result_code' => $code,
                'result_label' => $label,
                'interpretation' => $interpretation,
                'recommendation' => $recommendation,
                'severity_level' => $severity,
                'sort_order' => $sort
            ];
            $resp = $ruleId
                ? dbRequest('PATCH', 'assessment_scoring_rules', 'id=eq.' . (int)$ruleId . '&assessment_id=eq.' . $assessmentId, $payload, 'return=minimal')
                : dbRequest('POST', 'assessment_scoring_rules', '', $payload, 'return=minimal');
            if (ok($resp)) goAssessment($assessmentId, 'saved_rule');
            $detail = json_decode((string)($resp['body'] ?? ''), true);
            $errors[] = 'บันทึกเกณฑ์แปลผลไม่สำเร็จ (HTTP ' . (int)($resp['status'] ?? 0) . '): ' . (is_array($detail) ? (string)($detail['message'] ?? '') : '');
        }
    } elseif ($action === 'delete_rule') {
        $id = filter_var($_POST['rule_id'] ?? '', FILTER_VALIDATE_INT);
        if ($id) {
            $resp = dbRequest('DELETE', 'assessment_scoring_rules', 'id=eq.' . (int)$id . '&assessment_id=eq.' . $assessmentId, null, 'return=minimal');
            if (ok($resp)) goAssessment($assessmentId, 'deleted_rule');
            $errors[] = 'ลบเกณฑ์แปลผลไม่สำเร็จ (HTTP ' . (int)$resp['status'] . ')';
        }
    }
}

$assessmentsResp = dbRequest('GET', 'assessments', 'select=id,code,title,status&order=sort_order.asc,id.asc');
$assessmentRows = ok($assessmentsResp) ? rows($assessmentsResp) : [];
$selectedAssessment = null;
foreach ($assessmentRows as $a) {
    if ((int)$a['id'] === $assessmentId) $selectedAssessment = $a;
}
if (!$assessmentId && $assessmentRows) {
    $assessmentId = (int)$assessmentRows[0]['id'];
    $selectedAssessment = $assessmentRows[0];
}

$questions = [];
$optionsByQuestion = [];
$rules = [];
$editQuestion = null;
$editOptions = [];
$editRule = null;
if ($assessmentId > 0) {
    $qResp = dbRequest('GET', 'assessment_questions', 'select=*&assessment_id=eq.' . $assessmentId . '&order=sort_order.asc,id.asc');
    if (ok($qResp)) $questions = rows($qResp);
    else $errors[] = 'โหลดคำถามไม่สำเร็จ (HTTP ' . (int)$qResp['status'] . '). ตรวจสอบตาราง assessment_questions และ SUPABASE_SECRET_KEY';

    $rResp = dbRequest('GET', 'assessment_scoring_rules', 'select=*&assessment_id=eq.' . $assessmentId . '&order=sort_order.asc,id.asc');
    if (ok($rResp)) $rules = rows($rResp);
    else $errors[] = 'โหลดเกณฑ์แปลผลไม่สำเร็จ (HTTP ' . (int)$rResp['status'] . '). ตรวจสอบตาราง assessment_scoring_rules';

    if ($questions) {
        $ids = array_map(static fn($q) => (int)$q['id'], $questions);
        $or = 'question_id=in.(' . implode(',', $ids) . ')';
        $oResp = dbRequest('GET', 'assessment_options', 'select=*&' . $or . '&order=sort_order.asc,id.asc');
        if (ok($oResp)) {
            foreach (rows($oResp) as $o) $optionsByQuestion[(int)$o['question_id']][] = $o;
        } else {
            $errors[] = 'โหลดตัวเลือกไม่สำเร็จ (HTTP ' . (int)$oResp['status'] . '). ตรวจสอบตาราง assessment_options';
        }
    }

    $editQid = filter_input(INPUT_GET, 'edit_question', FILTER_VALIDATE_INT);
    if ($editQid) {
        foreach ($questions as $q) {
            if ((int)$q['id'] === (int)$editQid) {
                $editQuestion = $q;
                $editOptions = $optionsByQuestion[(int)$q['id']] ?? [];
                break;
            }
        }
    }
    $editRid = filter_input(INPUT_GET, 'edit_rule', FILTER_VALIDATE_INT);
    if ($editRid) foreach ($rules as $r) if ((int)$r['id'] === (int)$editRid) $editRule = $r;
}

$msgs = [
    'saved_question' => 'บันทึกคำถามและตัวเลือกแล้ว',
    'deleted_question' => 'ลบคำถามแล้ว',
    'saved_rule' => 'บันทึกเกณฑ์แปลผลแล้ว',
    'deleted_rule' => 'ลบเกณฑ์แปลผลแล้ว'
];
$msgKey = (string)($_GET['msg'] ?? '');
if (isset($msgs[$msgKey])) $notice = $msgs[$msgKey];

$optionsText = '';
foreach ($editOptions as $o) {
    $optionsText .= (string)$o['option_label'] . '|' . (string)$o['option_value'] . '|' . (string)$o['score'] . '|' . (string)($o['safety_level'] ?? 'none') . "\n";
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>จัดการคำถามแบบประเมิน | MindCare ADMIN</title>
<style>
:root{--navy:#111d43;--blue:#234e9b;--ink:#17264b;--muted:#53627c;--line:#dce5f0;--bg:#f0f5fa;--green:#16836f;--red:#bd3342}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Tahoma,sans-serif;font-size:15px;line-height:1.55}
header{background:var(--navy);color:#fff;padding:18px 28px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}header h1{font-size:22px;margin:0}header p{margin:4px 0 0;color:#dce6f8;font-size:13px}.head-actions{display:flex;gap:10px;align-items:center}.head-actions a{color:#fff;text-decoration:none}
main{max-width:1240px;margin:28px auto;padding:0 18px}.topline{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:18px}.topline h2{margin:0 0 4px;font-size:23px}.muted,.help{color:var(--muted);font-size:13px}.panel{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;margin-bottom:22px;box-shadow:0 3px 12px #00000006}.panel h3{margin:0 0 14px;font-size:19px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.field{display:flex;flex-direction:column;gap:6px}.field.full{grid-column:1/-1}label{font-weight:600;font-size:13px}input,select,textarea{width:100%;border:1px solid #cbd9eb;border-radius:8px;padding:10px 11px;background:#fff;color:var(--ink);font:inherit}textarea{min-height:105px;resize:vertical}button,.btn{border:0;border-radius:8px;padding:10px 14px;font:inherit;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}.primary{background:var(--blue);color:#fff}.gray{background:#e8eef6;color:var(--ink)}.green{background:var(--green);color:#fff}.red{background:var(--red);color:#fff}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.alert{padding:12px 15px;border-radius:9px;background:#d9f4e7;color:#146548;margin-bottom:14px}.alert.error{background:#ffe6e6;color:#a51f2d}.selector{max-width:600px;margin-bottom:20px}.list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.item{border:1px solid var(--line);border-radius:12px;padding:17px;background:#fff;min-width:0}.item h4{margin:7px 0;font-size:16px;overflow-wrap:anywhere}.tag{font-size:11px;background:#edf2fa;border-radius:5px;padding:3px 7px;color:#40577c}.option-list{font-size:12px;color:var(--muted);white-space:pre-wrap;overflow-wrap:anywhere}.checkline{display:flex;gap:8px;align-items:center}.checkline input{width:auto}.divider{border:0;border-top:1px solid var(--line);margin:22px 0}.empty{border:1px dashed #bdcde0;background:#fff;border-radius:12px;padding:25px;text-align:center;color:var(--muted)}@media(max-width:760px){.grid,.list{grid-template-columns:1fr}.field.full{grid-column:auto}header{padding:16px 18px}.panel{padding:17px}main{margin-top:18px}}
</style>
</head>
<body>
<header>
  <div><h1>MindCare ADMIN</h1><p>ระบบจัดการคำถาม ตัวเลือก และเกณฑ์แปลผลแบบประเมิน</p></div>
  <div class="head-actions"><a href="admin_assessments.php">← กลับหน้ารายการแบบประเมิน</a><form method="post" action="admin_logout.php"><button class="gray" type="submit">ออกจากระบบ</button></form></div>
</header>
<main>
  <div class="topline"><div><h2>จัดการคำถามแบบประเมิน</h2><div class="muted">เพิ่ม แก้ไข ลบคำถาม ตัวเลือกคะแนน และเกณฑ์แปลผลของแบบประเมินที่เลือก</div></div></div>
  <?php if ($notice !== ''): ?><div class="alert"><?=h($notice)?></div><?php endif; ?>
  <?php foreach ($errors as $e): ?><div class="alert error"><?=h($e)?></div><?php endforeach; ?>

  <section class="panel selector">
    <label for="assessmentPicker">เลือกแบบประเมิน</label>
    <select id="assessmentPicker" onchange="if(this.value) location.href='admin_assessment_questions.php?assessment_id='+this.value">
      <option value="">-- เลือกแบบประเมิน --</option>
      <?php foreach ($assessmentRows as $a): ?>
        <option value="<?=(int)$a['id']?>" <?=((int)$a['id']===$assessmentId?'selected':'')?>><?=h($a['code'])?> — <?=h($a['title'])?> (<?=h($a['status'])?>)</option>
      <?php endforeach; ?>
    </select>
    <?php if (!$assessmentRows): ?><p class="help">ยังไม่พบรายการแบบประเมิน ให้สร้างรายการจากหน้า admin_assessments.php ก่อน</p><?php endif; ?>
  </section>

  <?php if ($selectedAssessment): ?>
  <div class="topline"><div><strong><?=h($selectedAssessment['title'])?></strong><div class="muted">รหัส <?=h($selectedAssessment['code'])?> · สถานะ <?=h($selectedAssessment['status'])?></div></div></div>

  <section class="panel">
    <h3><?= $editQuestion ? 'แก้ไขคำถาม' : 'เพิ่มคำถามใหม่' ?></h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=h($csrf)?>">
      <input type="hidden" name="assessment_id" value="<?=$assessmentId?>">
      <input type="hidden" name="action" value="save_question">
      <input type="hidden" name="question_id" value="<?=h($editQuestion['id'] ?? '')?>">
      <div class="grid">
        <div class="field"><label>รหัสคำถาม (ไม่บังคับ)</label><input name="question_code" maxlength="100" value="<?=h($editQuestion['question_code'] ?? '')?>" placeholder="เช่น Q1"></div>
        <div class="field"><label>ชนิดคำถาม</label><select name="question_type" id="questionType" onchange="toggleOptions()">
          <?php foreach (['single_choice'=>'เลือกคำตอบเดียว','multiple_choice'=>'เลือกได้หลายข้อ','text'=>'คำตอบเป็นข้อความ','number'=>'คำตอบเป็นตัวเลข'] as $v=>$label): ?>
          <option value="<?=h($v)?>" <?= (($editQuestion['question_type'] ?? 'single_choice')===$v?'selected':'') ?>><?=h($label)?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="field full"><label>ข้อความคำถาม *</label><textarea name="question_text" required maxlength="10000"><?=h($editQuestion['question_text'] ?? '')?></textarea></div>
        <div class="field full"><label>คำอธิบายเพิ่มเติม (ไม่บังคับ)</label><textarea name="help_text" maxlength="5000"><?=h($editQuestion['help_text'] ?? '')?></textarea></div>
        <div class="field"><label>ลำดับแสดงผล</label><input type="number" name="sort_order" value="<?=h($editQuestion['sort_order'] ?? count($questions))?>"></div>
        <div class="field"><label class="checkline"><input type="checkbox" name="is_required" value="1" <?=(!isset($editQuestion['is_required']) || !empty($editQuestion['is_required'])?'checked':'')?>> ต้องตอบคำถามนี้</label><label class="checkline"><input type="checkbox" name="safety_review_required" value="1" <?=(!empty($editQuestion['safety_review_required'])?'checked':'')?>> คำถามนี้ต้องตรวจสอบด้านความปลอดภัยเป็นพิเศษ</label></div>
        <div class="field full" id="optionsField"><label>ตัวเลือกคำตอบ (หนึ่งตัวเลือกต่อหนึ่งบรรทัด) *</label><textarea name="options_text" placeholder="ข้อความที่แสดง|ค่าที่บันทึก|คะแนน|ระดับความปลอดภัย&#10;ไม่เลย|0|0|none&#10;บางครั้ง|1|1|none&#10;บ่อยครั้ง|2|2|follow_up&#10;ระดับเร่งด่วน|3|3|urgent"><?=h($optionsText)?></textarea><div class="help">รูปแบบ: label|value|score|safety_level · safety_level ใช้ none, follow_up หรือ urgent · ใช้กับคำถามที่เป็นตัวเลือกเท่านั้น</div></div>
      </div>
      <div class="actions"><button class="primary" type="submit">บันทึกคำถาม</button><?php if ($editQuestion): ?><a class="btn gray" href="admin_assessment_questions.php?assessment_id=<?=$assessmentId?>">ยกเลิกแก้ไข</a><?php endif; ?></div>
    </form>
  </section>

  <section class="panel">
    <h3>รายการคำถาม (<?=count($questions)?>)</h3>
    <?php if (!$questions): ?><div class="empty">ยังไม่มีคำถาม เพิ่มคำถามได้จากแบบฟอร์มด้านบน</div><?php else: ?>
    <div class="list">
      <?php foreach ($questions as $q): $qOpts=$optionsByQuestion[(int)$q['id']]??[]; ?>
      <article class="item">
        <span class="tag"><?=h($q['question_code'] ?: ('Q'.(int)$q['sort_order']))?></span>
        <span class="tag"><?=h($q['question_type'])?></span>
        <h4><?=h($q['question_text'])?></h4>
        <?php if (!empty($q['help_text'])): ?><p class="muted"><?=nl2br(h($q['help_text']))?></p><?php endif; ?>
        <?php if ($qOpts): ?><div class="option-list"><?php foreach ($qOpts as $o): ?>• <?=h($o['option_label'])?> — คะแนน <?=h($o['score'])?><?=($o['safety_level']??'none')!=='none'?' · '.h($o['safety_level']):''?>&#10;<?php endforeach; ?></div><?php endif; ?>
        <div class="actions"><a class="btn gray" href="admin_assessment_questions.php?assessment_id=<?=$assessmentId?>&edit_question=<?=(int)$q['id']?>">แก้ไข</a>
          <form method="post" onsubmit="return confirm('ยืนยันลบคำถามนี้และตัวเลือกทั้งหมดหรือไม่?')"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="assessment_id" value="<?=$assessmentId?>"><input type="hidden" name="action" value="delete_question"><input type="hidden" name="question_id" value="<?=(int)$q['id']?>"><button class="red" type="submit">ลบ</button></form>
        </div>
      </article>
      <?php endforeach; ?>
    </div><?php endif; ?>
  </section>

  <section class="panel">
    <h3><?= $editRule ? 'แก้ไขเกณฑ์แปลผล' : 'เพิ่มเกณฑ์แปลผลคะแนน' ?></h3>
    <p class="help">กำหนดช่วงคะแนนและข้อความแนะนำสำหรับแบบประเมินที่สร้างเองหรือมีสิทธิ์ใช้แล้วเท่านั้น ตรวจสอบเกณฑ์จากคู่มือที่ได้รับอนุญาตก่อนเผยแพร่</p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="assessment_id" value="<?=$assessmentId?>">
      <input type="hidden" name="action" value="save_rule"><input type="hidden" name="rule_id" value="<?=h($editRule['id'] ?? '')?>">
      <div class="grid">
        <div class="field"><label>รหัสผลลัพธ์ *</label><input name="result_code" required maxlength="100" value="<?=h($editRule['result_code'] ?? '')?>" placeholder="เช่น low"></div>
        <div class="field"><label>ชื่อผลลัพธ์ *</label><input name="result_label" required maxlength="250" value="<?=h($editRule['result_label'] ?? '')?>" placeholder="เช่น ระดับต่ำ"></div>
        <div class="field"><label>คะแนนต่ำสุด (เว้นว่างได้)</label><input type="number" step="0.01" name="min_score" value="<?=h($editRule['min_score'] ?? '')?>"></div>
        <div class="field"><label>คะแนนสูงสุด (เว้นว่างได้)</label><input type="number" step="0.01" name="max_score" value="<?=h($editRule['max_score'] ?? '')?>"></div>
        <div class="field"><label>ระดับความรุนแรง</label><select name="severity_level"><?php foreach (['informational'=>'ข้อมูลทั่วไป','low'=>'ต่ำ','moderate'=>'ปานกลาง','high'=>'สูง','urgent'=>'เร่งด่วน'] as $v=>$label): ?><option value="<?=h($v)?>" <?= (($editRule['severity_level'] ?? 'informational')===$v?'selected':'') ?>><?=h($label)?></option><?php endforeach; ?></select></div>
        <div class="field"><label>ลำดับ</label><input type="number" name="rule_sort_order" value="<?=h($editRule['sort_order'] ?? count($rules))?>"></div>
        <div class="field full"><label>คำอธิบายผลลัพธ์</label><textarea name="interpretation"><?=h($editRule['interpretation'] ?? '')?></textarea></div>
        <div class="field full"><label>คำแนะนำ / ขั้นตอนต่อไป</label><textarea name="recommendation"><?=h($editRule['recommendation'] ?? '')?></textarea></div>
      </div>
      <div class="actions"><button class="primary" type="submit">บันทึกเกณฑ์แปลผล</button><?php if ($editRule): ?><a class="btn gray" href="admin_assessment_questions.php?assessment_id=<?=$assessmentId?>">ยกเลิกแก้ไข</a><?php endif; ?></div>
    </form>
    <hr class="divider">
    <h3>เกณฑ์แปลผลที่ตั้งไว้ (<?=count($rules)?>)</h3>
    <?php if (!$rules): ?><div class="empty">ยังไม่มีเกณฑ์แปลผล</div><?php else: ?>
      <div class="list"><?php foreach ($rules as $r): ?>
        <article class="item"><span class="tag"><?=h($r['result_code'])?></span><span class="tag"><?=h($r['severity_level'])?></span><h4><?=h($r['result_label'])?></h4>
          <p class="muted">ช่วงคะแนน: <?=h($r['min_score'] ?? 'ไม่กำหนด')?> – <?=h($r['max_score'] ?? 'ไม่กำหนด')?></p>
          <?php if (!empty($r['interpretation'])): ?><p><?=nl2br(h($r['interpretation']))?></p><?php endif; ?>
          <?php if (!empty($r['recommendation'])): ?><p class="muted"><?=nl2br(h($r['recommendation']))?></p><?php endif; ?>
          <div class="actions"><a class="btn gray" href="admin_assessment_questions.php?assessment_id=<?=$assessmentId?>&edit_rule=<?=(int)$r['id']?>">แก้ไข</a>
            <form method="post" onsubmit="return confirm('ยืนยันลบเกณฑ์แปลผลนี้หรือไม่?')"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="assessment_id" value="<?=$assessmentId?>"><input type="hidden" name="action" value="delete_rule"><input type="hidden" name="rule_id" value="<?=(int)$r['id']?>"><button class="red" type="submit">ลบ</button></form>
          </div>
        </article>
      <?php endforeach; ?></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</main>
<script>
function toggleOptions(){
  const type = document.getElementById('questionType').value;
  document.getElementById('optionsField').style.display = ['single_choice','multiple_choice'].includes(type) ? 'flex' : 'none';
}
toggleOptions();
</script>
</body>
</html>
