<?php
session_start();
require_once __DIR__ . '/supabase_config.php';

/*
 * MindCare | Admin จัดการแบบประเมิน
 * ใช้ตาราง public.assessments จาก mindcare_assessment_schema.sql
 * หน้านี้จัดการข้อมูลหลักของแบบประเมินก่อน ส่วนคำถาม/ตัวเลือก/เกณฑ์คะแนน
 * จะจัดการผ่านหน้าถัดไป
 */

if (
    empty($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    !defined('ADMIN_EMAIL') ||
    strtolower((string)($_SESSION['admin_email'] ?? '')) !== strtolower((string)ADMIN_EMAIL)
) {
    header('Location: admin.php');
    exit;
}

if (empty($_SESSION['assessment_csrf'])) {
    $_SESSION['assessment_csrf'] = bin2hex(random_bytes(32));
}

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function validId($value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return ($id !== false && $id > 0) ? (int)$id : null;
}

function validHttpUrl(string $url): bool
{
    if (trim($url) === '') {
        return true;
    }
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && in_array($scheme, ['http', 'https'], true);
}

function supaRequest(string $method, string $table, string $query = '', ?array $data = null, ?string $prefer = null): array
{
    $allowedTables = ['assessments'];
    if (!in_array($table, $allowedTables, true)) {
        return ['status' => 400, 'body' => 'Invalid table'];
    }

    if (
        !defined('SUPABASE_URL') ||
        !defined('SUPABASE_SECRET_KEY') ||
        trim((string)SUPABASE_URL) === '' ||
        trim((string)SUPABASE_SECRET_KEY) === ''
    ) {
        return ['status' => 500, 'body' => 'ไม่พบ SUPABASE_URL หรือ SUPABASE_SECRET_KEY ใน supabase_config.php'];
    }

    if (!function_exists('curl_init')) {
        return ['status' => 500, 'body' => 'เซิร์ฟเวอร์ยังไม่ได้เปิดใช้งาน PHP cURL'];
    }

    $url = rtrim((string)SUPABASE_URL, '/') . '/rest/v1/' . $table;
    if ($query !== '') {
        $url .= '?' . $query;
    }

    $key = trim((string)SUPABASE_SECRET_KEY);
    $headers = [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    if ($prefer !== null) {
        $headers[] = 'Prefer: ' . $prefer;
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30
    ];

    if ($data !== null) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['status' => 500, 'body' => 'แปลงข้อมูล JSON ไม่สำเร็จ'];
        }
        $options[CURLOPT_POSTFIELDS] = $json;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log('MindCare assessments cURL error: ' . $error);
        return ['status' => 500, 'body' => $error];
    }

    curl_close($ch);
    return ['status' => $status, 'body' => $body];
}

function supaOk(array $response): bool
{
    return isset($response['status']) && $response['status'] >= 200 && $response['status'] < 300;
}

function supaRows(array $response): array
{
    $decoded = json_decode((string)($response['body'] ?? ''), true);
    return is_array($decoded) ? $decoded : [];
}

function redirectPage(string $result = ''): void
{
    $url = 'admin_assessments.php';
    if ($result !== '') {
        $url .= '?result=' . rawurlencode($result);
    }
    header('Location: ' . $url);
    exit;
}

$error = '';
$notice = '';

$allowedTypes = ['standard', 'custom'];
$allowedStatuses = ['draft', 'published', 'archived'];
$allowedPermissions = ['not_applicable', 'needs_review', 'permission_required', 'authorized', 'permission_not_required'];
$allowedScoring = ['sum', 'weighted_sum', 'rule_based', 'manual_review', 'none'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['assessment_csrf'], $csrf)) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณากลับไปโหลดหน้าใหม่');
    }

    $action = (string)($_POST['action'] ?? '');
    $id = validId($_POST['id'] ?? null);

    if ($action === 'save') {
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
        $title = trim((string)($_POST['title'] ?? ''));
        $shortTitle = trim((string)($_POST['short_title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $instructions = trim((string)($_POST['instructions'] ?? ''));
        $audienceInput = $_POST['audience'] ?? [];
        $audience = [];
        if (is_array($audienceInput)) {
            foreach ($audienceInput as $person) {
                if (in_array($person, ['general', 'student'], true)) {
                    $audience[] = $person;
                }
            }
        }
        $audience = array_values(array_unique($audience));
        $type = (string)($_POST['assessment_type'] ?? 'custom');
        $status = (string)($_POST['status'] ?? 'draft');
        $permission = (string)($_POST['permission_status'] ?? 'not_applicable');
        $permissionNotes = trim((string)($_POST['permission_notes'] ?? ''));
        $sourceUrl = trim((string)($_POST['source_url'] ?? ''));
        $sourceName = trim((string)($_POST['source_name'] ?? ''));
        $scoringMethod = (string)($_POST['scoring_method'] ?? 'sum');
        $estimatedMinutesRaw = trim((string)($_POST['estimated_minutes'] ?? ''));
        $sortOrderRaw = trim((string)($_POST['sort_order'] ?? '0'));
        $showScore = isset($_POST['show_score_to_user']);
        $showInterpretation = isset($_POST['show_interpretation_to_user']);
        $safetyNotice = trim((string)($_POST['safety_notice'] ?? ''));

        if ($code === '' || !preg_match('/^[A-Z0-9_-]{2,40}$/', $code)) {
            $error = 'รหัสแบบประเมินต้องมี 2–40 ตัว และใช้ได้เฉพาะ A-Z, 0-9, _ หรือ -';
        } elseif ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $error = 'Slug ต้องเป็นภาษาอังกฤษตัวพิมพ์เล็ก ตัวเลข และใช้ขีดกลางคั่นคำ';
        } elseif ($title === '') {
            $error = 'กรุณากรอกชื่อแบบประเมิน';
        } elseif (empty($audience)) {
            $error = 'กรุณาเลือกกลุ่มผู้ใช้งานอย่างน้อย 1 กลุ่ม';
        } elseif (!in_array($type, $allowedTypes, true) || !in_array($status, $allowedStatuses, true)
            || !in_array($permission, $allowedPermissions, true) || !in_array($scoringMethod, $allowedScoring, true)) {
            $error = 'พบค่าบางช่องไม่ถูกต้อง กรุณาตรวจสอบข้อมูลอีกครั้ง';
        } elseif (!validHttpUrl($sourceUrl)) {
            $error = 'ลิงก์แหล่งที่มาต้องเป็น URL http:// หรือ https://';
        } elseif ($estimatedMinutesRaw !== '' && (!ctype_digit($estimatedMinutesRaw) || (int)$estimatedMinutesRaw < 1 || (int)$estimatedMinutesRaw > 600)) {
            $error = 'เวลาโดยประมาณต้องเป็นจำนวนเต็มตั้งแต่ 1 ถึง 600 นาที';
        } elseif ($sortOrderRaw !== '' && !preg_match('/^-?\d{1,6}$/', $sortOrderRaw)) {
            $error = 'ลำดับการแสดงผลต้องเป็นตัวเลข';
        } elseif ($status === 'published' && $type === 'standard' && !in_array($permission, ['authorized', 'permission_not_required'], true)) {
            $error = 'ยังเผยแพร่แบบประเมินมาตรฐานไม่ได้ กรุณาตรวจสอบสิทธิ์การใช้งานและเลือกสถานะสิทธิ์ที่ถูกต้องก่อน';
        } else {
            $payload = [
                'code' => $code,
                'slug' => $slug,
                'title' => $title,
                'short_title' => $shortTitle !== '' ? $shortTitle : null,
                'description' => $description,
                'audience' => $audience,
                'assessment_type' => $type,
                'source_url' => $sourceUrl !== '' ? $sourceUrl : null,
                'source_name' => $sourceName !== '' ? $sourceName : null,
                'permission_status' => $permission,
                'permission_notes' => $permissionNotes !== '' ? $permissionNotes : null,
                'status' => $status,
                'instructions' => $instructions,
                'estimated_minutes' => $estimatedMinutesRaw !== '' ? (int)$estimatedMinutesRaw : null,
                'scoring_method' => $scoringMethod,
                'show_score_to_user' => $showScore,
                'show_interpretation_to_user' => $showInterpretation,
                'safety_notice' => $safetyNotice !== '' ? $safetyNotice : null,
                'sort_order' => $sortOrderRaw !== '' ? (int)$sortOrderRaw : 0
            ];

            if ($id !== null) {
                $response = supaRequest('PATCH', 'assessments', 'id=eq.' . $id, $payload, 'return=representation');
            } else {
                $response = supaRequest('POST', 'assessments', '', $payload, 'return=representation');
            }

            if (supaOk($response)) {
                redirectPage('saved');
            }

            error_log('MindCare assessment save failed: ' . (string)($response['body'] ?? 'unknown'));
            $decodedError = json_decode((string)($response['body'] ?? ''), true);
            $detail = is_array($decodedError) ? (string)($decodedError['message'] ?? '') : '';
            if (($response['status'] ?? 0) === 403) {
                $error = 'บันทึกไม่สำเร็จ (HTTP 403) ตรวจสอบว่า SUPABASE_SECRET_KEY เป็น service_role/secret key ฝั่งเซิร์ฟเวอร์ และตาราง assessments มีอยู่จริง';
            } elseif (($response['status'] ?? 0) === 404) {
                $error = 'ไม่พบตาราง assessments กรุณารันไฟล์ mindcare_assessment_schema.sql ใน Supabase SQL Editor ก่อน';
            } else {
                $error = 'บันทึกแบบประเมินไม่สำเร็จ (HTTP ' . (int)($response['status'] ?? 0) . ')' . ($detail !== '' ? ' — ' . $detail : '');
            }
        }
    } elseif ($action === 'delete' && $id !== null) {
        $response = supaRequest('DELETE', 'assessments', 'id=eq.' . $id, null, 'return=minimal');
        if (supaOk($response)) {
            redirectPage('deleted');
        }
        $error = 'ลบแบบประเมินไม่สำเร็จ (HTTP ' . (int)($response['status'] ?? 0) . ') ตรวจสอบการเชื่อมต่อ Supabase';
    } elseif ($action === 'toggle' && $id !== null) {
        $newStatus = (string)($_POST['new_status'] ?? 'draft');
        if (!in_array($newStatus, $allowedStatuses, true)) {
            $error = 'สถานะที่เลือกไม่ถูกต้อง';
        } else {
            $checkResponse = supaRequest('GET', 'assessments', 'select=id,assessment_type,permission_status&id=eq.' . $id . '&limit=1');
            $checkRows = supaRows($checkResponse);
            if (!supaOk($checkResponse) || empty($checkRows)) {
                $error = 'ไม่พบแบบประเมินที่ต้องการเปลี่ยนสถานะ';
            } elseif (
                $newStatus === 'published' &&
                ($checkRows[0]['assessment_type'] ?? '') === 'standard' &&
                !in_array(($checkRows[0]['permission_status'] ?? ''), ['authorized', 'permission_not_required'], true)
            ) {
                $error = 'ยังเผยแพร่ไม่ได้ ต้องตรวจสอบสิทธิ์แบบประเมินมาตรฐานก่อน';
            } else {
                $response = supaRequest('PATCH', 'assessments', 'id=eq.' . $id, ['status' => $newStatus], 'return=minimal');
                if (supaOk($response)) {
                    redirectPage('updated');
                }
                $error = 'เปลี่ยนสถานะไม่สำเร็จ (HTTP ' . (int)($response['status'] ?? 0) . ')';
            }
        }
    } elseif ($action !== '') {
        $error = 'คำสั่งไม่ถูกต้อง';
    }
}

if (isset($_GET['result'])) {
    $messages = [
        'saved' => 'บันทึกข้อมูลแบบประเมินเรียบร้อยแล้ว',
        'deleted' => 'ลบแบบประเมินเรียบร้อยแล้ว',
        'updated' => 'เปลี่ยนสถานะแบบประเมินเรียบร้อยแล้ว'
    ];
    if (isset($messages[$_GET['result']])) {
        $notice = $messages[$_GET['result']];
    }
}

$listResponse = supaRequest(
    'GET',
    'assessments',
    'select=id,code,slug,title,short_title,description,audience,assessment_type,source_url,source_name,permission_status,permission_notes,status,estimated_minutes,scoring_method,sort_order,created_at&order=sort_order.asc,id.asc'
);
$assessments = supaOk($listResponse) ? supaRows($listResponse) : [];
if (!supaOk($listResponse)) {
    $statusCode = (int)($listResponse['status'] ?? 0);
    if ($statusCode === 403) {
        $error = $error !== '' ? $error : 'โหลดรายการไม่สำเร็จ (HTTP 403) ตรวจสอบ SUPABASE_SECRET_KEY และสิทธิ์ของตาราง assessments';
    } elseif ($statusCode === 404) {
        $error = $error !== '' ? $error : 'ไม่พบตาราง assessments กรุณารัน mindcare_assessment_schema.sql ใน Supabase ก่อน';
    } else {
        $error = $error !== '' ? $error : 'โหลดรายการแบบประเมินไม่สำเร็จ (HTTP ' . $statusCode . ')';
    }
}

$edit = null;
$editId = validId($_GET['edit'] ?? null);
if ($editId !== null) {
    $editResponse = supaRequest('GET', 'assessments', 'select=*&id=eq.' . $editId . '&limit=1');
    $editRows = supaRows($editResponse);
    if (supaOk($editResponse) && !empty($editRows)) {
        $edit = $editRows[0];
    } else {
        $error = 'ไม่พบแบบประเมินที่เลือก';
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>จัดการแบบประเมิน | MindCare ADMIN</title>
<style>
:root{--navy:#111d43;--blue:#234e9b;--ink:#17264b;--muted:#53627c;--line:#e1e9f2;--bg:#f0f5fa;--green:#16836f;--red:#b83242}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Tahoma,sans-serif}
header{background:var(--navy);color:#fff;padding:20px 30px;display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}
header h1{margin:0;font-size:24px}header p{margin:6px 0 0;color:#dce6f8;font-size:14px}
header a{color:#fff;text-decoration:none;font-size:14px}
main{max-width:1200px;margin:28px auto;padding:0 20px}
.page-heading{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:20px}
h2{margin:0 0 6px;font-size:23px}.subheading{margin:0;color:var(--muted);line-height:1.7;font-size:14px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border:0;border-radius:8px;padding:10px 14px;font-size:14px;font-weight:bold;text-decoration:none;cursor:pointer;font-family:inherit}
.btn.primary{background:var(--blue);color:#fff}.btn.gray{background:#e8eef6;color:var(--ink)}.btn.green{background:var(--green);color:#fff}.btn.red{background:var(--red);color:#fff}
.alert{padding:13px 16px;border-radius:9px;margin:14px 0;background:#dcf5e8;color:#116148;font-size:14px}.alert.error{background:#ffe7e7;color:#9b1c28}
.summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:20px 0}
.stat{background:#fff;border:1px solid var(--line);border-radius:12px;padding:17px}.stat strong{display:block;font-size:24px}.stat span{font-size:13px;color:var(--muted)}
.assessment-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.card{background:#fff;border:1px solid var(--line);border-radius:13px;padding:19px;min-width:0;box-shadow:0 3px 12px #00000008;display:flex;flex-direction:column}
.card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}.card h3{font-size:18px;line-height:1.5;margin:9px 0 7px;overflow-wrap:anywhere}.code{font-size:12px;color:#52627d;background:#eef3fa;padding:5px 8px;border-radius:6px;display:inline-block}
.status{font-size:12px;font-weight:bold;padding:5px 9px;border-radius:20px;white-space:nowrap}.status.draft{background:#fff1cc;color:#805900}.status.published{background:#dcf5e8;color:#116148}.status.archived{background:#e8eaf0;color:#505a6d}
.type{font-size:12px;color:#27776c;font-weight:bold}.card p{font-size:14px;line-height:1.7;color:var(--muted);margin:6px 0 12px;overflow-wrap:anywhere}
.meta{font-size:12px;color:#748096;line-height:1.8;margin-top:auto}.actions{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px}.actions form{display:inline-flex}
.empty{background:#fff;border:1px dashed #b9cbd9;border-radius:12px;padding:32px;text-align:center;color:var(--muted)}
.backdrop{position:fixed;inset:0;background:#101a34b5;display:none;align-items:center;justify-content:center;padding:16px;z-index:100}.backdrop.open{display:flex}
.modal{background:#fff;width:min(900px,100%);max-height:94vh;overflow:auto;border-radius:15px;padding:22px;box-shadow:0 20px 70px #0004}
.modal-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}.modal-head h2{margin:0;font-size:21px}
.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}
.field{min-width:0}.field.full{grid-column:1/-1}
label{display:block;font-size:13px;font-weight:bold;margin:10px 0 6px}
input[type=text],input[type=url],input[type=number],select,textarea{width:100%;padding:10px 11px;border:1px solid #cbd6e5;border-radius:8px;font:inherit;font-size:14px;color:var(--ink);background:#fff}
textarea{min-height:76px;resize:vertical;line-height:1.6}
.checks{display:flex;gap:18px;flex-wrap:wrap;padding:9px 0}.checks label{display:flex;align-items:center;gap:7px;margin:0;font-weight:normal}
.help{font-size:12px;color:var(--muted);line-height:1.6;margin:5px 0}
.modal-footer{display:flex;justify-content:flex-end;gap:8px;margin-top:20px;flex-wrap:wrap}
@media(max-width:760px){.assessment-list{grid-template-columns:1fr}.summary{grid-template-columns:1fr 1fr}.fields{grid-template-columns:1fr}.field.full{grid-column:auto}.modal{padding:16px}header{padding:17px 20px}}
@media(max-width:450px){.summary{grid-template-columns:1fr}.page-heading h2{font-size:21px}}
</style>
</head>
<body>
<header>
  <div>
    <h1>MindCare ADMIN</h1>
    <p>ระบบจัดการเนื้อหาเว็บไซต์ · แบบประเมินสุขภาพใจ</p>
  </div>
  <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
    <a href="admin_dashboard.php">← กลับแดชบอร์ด</a>
    <form method="post" action="admin_logout.php" style="margin:0">
      <button class="btn gray" type="submit">ออกจากระบบ</button>
    </form>
  </div>
</header>

<main>
  <div class="page-heading">
    <div>
      <h2>จัดการแบบประเมิน</h2>
      <p class="subheading">เพิ่ม แก้ไข ลบ และเผยแพร่ข้อมูลแบบประเมินสำหรับบุคคลทั่วไปและนักเรียน/นักศึกษา</p>
    </div>
    <button class="btn primary" type="button" onclick="openNew()">＋ เพิ่มแบบประเมิน</button>
  </div>

  <?php if ($notice !== ''): ?><div class="alert"><?= h($notice) ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>

  <?php
    $publishedCount = 0;
    $draftCount = 0;
    foreach ($assessments as $row) {
        if (($row['status'] ?? '') === 'published') $publishedCount++;
        if (($row['status'] ?? '') === 'draft') $draftCount++;
    }
  ?>
  <section class="summary">
    <div class="stat"><strong><?= count($assessments) ?></strong><span>แบบประเมินทั้งหมด</span></div>
    <div class="stat"><strong><?= $publishedCount ?></strong><span>เผยแพร่แล้ว</span></div>
    <div class="stat"><strong><?= $draftCount ?></strong><span>ฉบับร่าง</span></div>
  </section>

  <?php if (empty($assessments)): ?>
    <div class="empty">
      <strong>ยังไม่มีข้อมูลแบบประเมิน</strong>
      <p>หากเพิ่งเริ่มต้น ให้รันไฟล์ mindcare_assessment_schema.sql ใน Supabase SQL Editor ก่อน</p>
      <button class="btn primary" type="button" onclick="openNew()">＋ เพิ่มแบบประเมินแรก</button>
    </div>
  <?php else: ?>
    <section class="assessment-list">
      <?php foreach ($assessments as $item): ?>
        <article class="card">
          <div class="card-top">
            <span class="code"><?= h($item['code'] ?? '') ?></span>
            <span class="status <?= h($item['status'] ?? 'draft') ?>">
              <?= ($item['status'] ?? '') === 'published' ? 'เผยแพร่แล้ว' : ((($item['status'] ?? '') === 'archived') ? 'เก็บถาวร' : 'ฉบับร่าง') ?>
            </span>
          </div>
          <div class="type"><?= ($item['assessment_type'] ?? '') === 'standard' ? 'แบบประเมินมาตรฐาน' : 'แบบประเมินที่สร้างโดย MindCare' ?></div>
          <h3><?= h($item['title'] ?? '') ?></h3>
          <p><?= h(trim((string)($item['description'] ?? '')) !== '' ? $item['description'] : 'ยังไม่มีคำอธิบาย') ?></p>
          <div class="meta">
            Slug: <?= h($item['slug'] ?? '') ?><br>
            กลุ่มผู้ใช้: <?= h(implode(', ', is_array($item['audience'] ?? null) ? $item['audience'] : [])) ?><br>
            วิธีคิดคะแนน: <?= h($item['scoring_method'] ?? 'sum') ?> · เวลาโดยประมาณ: <?= h($item['estimated_minutes'] ?? '-') ?> นาที<br>
            สิทธิ์การใช้งาน: <?= h($item['permission_status'] ?? '-') ?>
            <?php if (!empty($item['source_url'])): ?><br>แหล่งที่มา: <a href="<?= h($item['source_url']) ?>" target="_blank" rel="noopener noreferrer">เปิดลิงก์</a><?php endif; ?>
          </div>
          <div class="actions">
            
<a class="btn primary"
   href="admin_assessment_questions.php?assessment_id=<?= (int)$item['id'] ?>">
    จัดการคำถาม →
</a>

            <a class="btn gray" href="admin_assessments.php?edit=<?= (int)$item['id'] ?>">แก้ไข</a>
            <?php if (($item['status'] ?? '') === 'published'): ?>
              <form method="post" onsubmit="return confirm('ต้องการเปลี่ยนเป็นฉบับร่างหรือไม่?')">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['assessment_csrf']) ?>">
                <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="new_status" value="draft">
                <button class="btn green" type="submit">นำออกจากเว็บ</button>
              </form>
            <?php else: ?>
              <form method="post" onsubmit="return confirm('ต้องการเผยแพร่แบบประเมินนี้หรือไม่?')">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['assessment_csrf']) ?>">
                <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="new_status" value="published">
                <button class="btn green" type="submit">เผยแพร่</button>
              </form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('ยืนยันลบแบบประเมินนี้? ข้อมูลคำถาม ตัวเลือก และเกณฑ์คะแนนที่เชื่อมอยู่จะถูกลบตามไปด้วย')">
              <input type="hidden" name="csrf_token" value="<?= h($_SESSION['assessment_csrf']) ?>">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <button class="btn red" type="submit">ลบ</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>

<div class="backdrop <?= $edit ? 'open' : '' ?>" id="assessmentModal" onclick="if(event.target===this)closeModal()">
  <section class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-head">
      <h2 id="modalTitle"><?= $edit ? 'แก้ไขแบบประเมิน' : 'เพิ่มแบบประเมิน' ?></h2>
      <button class="btn gray" type="button" onclick="closeModal()">ปิด ✕</button>
    </div>
    <form method="post" id="assessmentForm">
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['assessment_csrf']) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : '' ?>">

      <div class="fields">
        <div class="field">
          <label for="code">รหัสแบบประเมิน *</label>
          <input id="code" name="code" type="text" required maxlength="40" value="<?= h($edit['code'] ?? '') ?>" placeholder="เช่น MC_MOOD01">
        </div>
        <div class="field">
          <label for="slug">Slug *</label>
          <input id="slug" name="slug" type="text" required maxlength="80" value="<?= h($edit['slug'] ?? '') ?>" placeholder="เช่น mood-check">
          <p class="help">ใช้เป็นชื่อ URL ภาษาอังกฤษตัวพิมพ์เล็ก เช่น mood-check</p>
        </div>
        <div class="field full">
          <label for="title">ชื่อแบบประเมิน *</label>
          <input id="title" name="title" type="text" required maxlength="250" value="<?= h($edit['title'] ?? '') ?>" placeholder="ชื่อที่แสดงให้ผู้ใช้งานเห็น">
        </div>
        <div class="field">
          <label for="short_title">ชื่อย่อ</label>
          <input id="short_title" name="short_title" type="text" maxlength="100" value="<?= h($edit['short_title'] ?? '') ?>" placeholder="เช่น แบบประเมินอารมณ์">
        </div>
        <div class="field">
          <label for="assessment_type">ประเภท</label>
          <select id="assessment_type" name="assessment_type">
            <option value="custom" <?= ($edit['assessment_type'] ?? 'custom') === 'custom' ? 'selected' : '' ?>>สร้างโดย MindCare</option>
            <option value="standard" <?= ($edit['assessment_type'] ?? '') === 'standard' ? 'selected' : '' ?>>แบบประเมินมาตรฐาน</option>
          </select>
        </div>
        <div class="field full">
          <label>กลุ่มผู้ใช้งาน *</label>
          <?php $audienceSelected = is_array($edit['audience'] ?? null) ? $edit['audience'] : ['general', 'student']; ?>
          <div class="checks">
            <label><input type="checkbox" name="audience[]" value="general" <?= in_array('general', $audienceSelected, true) ? 'checked' : '' ?>> บุคคลทั่วไป</label>
            <label><input type="checkbox" name="audience[]" value="student" <?= in_array('student', $audienceSelected, true) ? 'checked' : '' ?>> นักเรียน/นักศึกษา</label>
          </div>
        </div>
        <div class="field full">
          <label for="description">คำอธิบาย</label>
          <textarea id="description" name="description" maxlength="3000" placeholder="อธิบายวัตถุประสงค์ของแบบประเมิน"><?= h($edit['description'] ?? '') ?></textarea>
        </div>
        <div class="field full">
          <label for="instructions">คำแนะนำก่อนเริ่มทำ</label>
          <textarea id="instructions" name="instructions" maxlength="5000" placeholder="อธิบายวิธีตอบคำถามและช่วงเวลาที่ใช้พิจารณา"><?= h($edit['instructions'] ?? '') ?></textarea>
        </div>
        <div class="field">
          <label for="estimated_minutes">เวลาโดยประมาณ (นาที)</label>
          <input id="estimated_minutes" name="estimated_minutes" type="number" min="1" max="600" value="<?= h($edit['estimated_minutes'] ?? '') ?>" placeholder="เช่น 5">
        </div>
        <div class="field">
          <label for="scoring_method">วิธีคำนวณคะแนน</label>
          <?php $scoreValue = $edit['scoring_method'] ?? 'sum'; ?>
          <select id="scoring_method" name="scoring_method">
            <option value="sum" <?= $scoreValue === 'sum' ? 'selected' : '' ?>>รวมคะแนน</option>
            <option value="weighted_sum" <?= $scoreValue === 'weighted_sum' ? 'selected' : '' ?>>รวมคะแนนแบบมีน้ำหนัก</option>
            <option value="rule_based" <?= $scoreValue === 'rule_based' ? 'selected' : '' ?>>ประเมินตามเงื่อนไข</option>
            <option value="manual_review" <?= $scoreValue === 'manual_review' ? 'selected' : '' ?>>ต้องให้ผู้เชี่ยวชาญประเมิน</option>
            <option value="none" <?= $scoreValue === 'none' ? 'selected' : '' ?>>ไม่แสดงคะแนน</option>
          </select>
        </div>
        <div class="field">
          <label for="status">สถานะ</label>
          <?php $statusValue = $edit['status'] ?? 'draft'; ?>
          <select id="status" name="status">
            <option value="draft" <?= $statusValue === 'draft' ? 'selected' : '' ?>>ฉบับร่าง</option>
            <option value="published" <?= $statusValue === 'published' ? 'selected' : '' ?>>เผยแพร่</option>
            <option value="archived" <?= $statusValue === 'archived' ? 'selected' : '' ?>>เก็บถาวร</option>
          </select>
        </div>
        <div class="field">
          <label for="sort_order">ลำดับการแสดงผล</label>
          <input id="sort_order" name="sort_order" type="number" value="<?= h($edit['sort_order'] ?? 0) ?>">
        </div>
        <div class="field full">
          <label for="source_url">ลิงก์แหล่งที่มา (ถ้ามี)</label>
          <input id="source_url" name="source_url" type="url" value="<?= h($edit['source_url'] ?? '') ?>" placeholder="https://...">
        </div>
        <div class="field">
          <label for="source_name">ชื่อแหล่งที่มา</label>
          <input id="source_name" name="source_name" type="text" value="<?= h($edit['source_name'] ?? '') ?>" placeholder="เช่น หน่วยงาน/ผู้พัฒนา">
        </div>
        <div class="field">
          <label for="permission_status">สถานะสิทธิ์การใช้งาน</label>
          <?php $permissionValue = $edit['permission_status'] ?? 'not_applicable'; ?>
          <select id="permission_status" name="permission_status">
            <option value="not_applicable" <?= $permissionValue === 'not_applicable' ? 'selected' : '' ?>>ไม่เกี่ยวข้อง (แบบสร้างเอง)</option>
            <option value="needs_review" <?= $permissionValue === 'needs_review' ? 'selected' : '' ?>>รอตรวจสอบ</option>
            <option value="permission_required" <?= $permissionValue === 'permission_required' ? 'selected' : '' ?>>ต้องขออนุญาต</option>
            <option value="authorized" <?= $permissionValue === 'authorized' ? 'selected' : '' ?>>ได้รับอนุญาตแล้ว</option>
            <option value="permission_not_required" <?= $permissionValue === 'permission_not_required' ? 'selected' : '' ?>>ตรวจสอบแล้วว่าไม่ต้องขออนุญาต</option>
          </select>
        </div>
        <div class="field full">
          <label for="permission_notes">บันทึกเรื่องสิทธิ์/ข้อควรตรวจสอบ</label>
          <textarea id="permission_notes" name="permission_notes" maxlength="5000" placeholder="บันทึกข้อมูลการอนุญาต แหล่งอ้างอิง หรือข้อจำกัดการใช้งาน"><?= h($edit['permission_notes'] ?? '') ?></textarea>
        </div>
        <div class="field full">
          <label for="safety_notice">คำเตือนหรือข้อความด้านความปลอดภัย</label>
          <textarea id="safety_notice" name="safety_notice" maxlength="3000" placeholder="เช่น แบบประเมินนี้ไม่ใช้แทนการวินิจฉัยโดยผู้เชี่ยวชาญ"><?= h($edit['safety_notice'] ?? '') ?></textarea>
        </div>
        <div class="field full">
          <div class="checks">
            <label><input type="checkbox" name="show_score_to_user" value="1" <?= !isset($edit['show_score_to_user']) || !empty($edit['show_score_to_user']) ? 'checked' : '' ?>> แสดงคะแนนแก่ผู้ใช้งาน</label>
            <label><input type="checkbox" name="show_interpretation_to_user" value="1" <?= !isset($edit['show_interpretation_to_user']) || !empty($edit['show_interpretation_to_user']) ? 'checked' : '' ?>> แสดงคำแปลผลแก่ผู้ใช้งาน</label>
          </div>
          <p class="help">แบบประเมินมาตรฐานจะเผยแพร่ได้เมื่อสถานะสิทธิ์เป็น “ได้รับอนุญาตแล้ว” หรือ “ตรวจสอบแล้วว่าไม่ต้องขออนุญาต” เท่านั้น</p>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn gray" onclick="closeModal()">ยกเลิก</button>
        <button type="submit" class="btn primary">บันทึกแบบประเมิน</button>
      </div>
    </form>
  </section>
</div>

<script>
function openNew() {
  window.location.href = 'admin_assessments.php?new=1';
}
function closeModal() {
  window.location.href = 'admin_assessments.php';
}
<?php if (isset($_GET['new']) && $_GET['new'] === '1' && !$edit): ?>
document.getElementById('assessmentModal').classList.add('open');
<?php endif; ?>
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape' && document.getElementById('assessmentModal').classList.contains('open')) {
    closeModal();
  }
});
</script>
</body>
</html>
