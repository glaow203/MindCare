<?php
session_start();
require_once __DIR__ . '/supabase_config.php';

/*
 * MindCare ADMIN: จัดการข้อมูลอาการและสาเหตุ
 * ใช้ตาราง knowledge_tips และ knowledge_tip_sections ที่มีอยู่
 * แยกหมวดด้วย knowledge_tips.content_group = symptoms / causes
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

if (empty($_SESSION['symptoms_csrf'])) {
    $_SESSION['symptoms_csrf'] = bin2hex(random_bytes(32));
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function validId($value): ?int {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return ($id !== false && $id > 0) ? (int)$id : null;
}

function validUrl(string $url): bool {
    if (trim($url) === '') return true;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return false;
    return in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
}

function supaRequest(string $method, string $table, string $query = '', ?array $data = null, ?string $prefer = null): array {
    $allowedTables = ['knowledge_tips', 'knowledge_tip_sections'];
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

    $url = rtrim((string)SUPABASE_URL, '/') . '/rest/v1/' . $table;
    if ($query !== '') $url .= '?' . $query;

    $key = trim((string)SUPABASE_SECRET_KEY);
    $headers = [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    if ($prefer !== null) $headers[] = 'Prefer: ' . $prefer;

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30
    ];

    if ($data !== null) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) return ['status' => 500, 'body' => 'JSON encode failed'];
        $options[CURLOPT_POSTFIELDS] = $json;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log('MindCare Supabase request error: ' . $error);
        return ['status' => 500, 'body' => $error];
    }

    curl_close($ch);
    return ['status' => $status, 'body' => (string)$body];
}

function supaOk(array $response): bool {
    return $response['status'] >= 200 && $response['status'] < 300;
}

function supaRows(array $response): array {
    $decoded = json_decode((string)($response['body'] ?? ''), true);
    return is_array($decoded) ? $decoded : [];
}

function redirectGroup(string $group, string $result = ''): void {
    $params = ['group' => $group];
    if ($result !== '') $params['result'] = $result;
    header('Location: admin_symptoms.php?' . http_build_query($params));
    exit;
}

$group = (string)($_GET['group'] ?? $_POST['group'] ?? 'symptoms');
if (!in_array($group, ['symptoms', 'causes'], true)) $group = 'symptoms';

$error = '';
$notice = '';
$loadError = '';
$articles = [];
$edit = null;
$editBlocks = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals((string)$_SESSION['symptoms_csrf'], (string)$_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณากลับไปโหลดหน้าใหม่');
    }

    $action = (string)($_POST['action'] ?? '');
    $id = validId($_POST['id'] ?? null);

    if ($action === 'save') {
        $title = trim((string)($_POST['title'] ?? ''));
        $coverUrl = trim((string)($_POST['image_url'] ?? ''));
        $published = isset($_POST['is_published']);
        $incomingBlocks = $_POST['blocks'] ?? [];
        if (!is_array($incomingBlocks)) $incomingBlocks = [];

        if ($title === '') {
            $error = 'กรุณากรอกชื่อเรื่อง';
        } elseif (!validUrl($coverUrl)) {
            $error = 'URL รูปปกไม่ถูกต้อง ต้องเป็นลิงก์ http:// หรือ https://';
        } elseif (count($incomingBlocks) > 100) {
            $error = 'เพิ่มเนื้อหาได้ไม่เกิน 100 ส่วนต่อบทความ';
        } else {
            $preparedBlocks = [];
            $combinedText = [];
            $invalid = false;

            foreach ($incomingBlocks as $block) {
                if (!is_array($block)) {
                    $invalid = true;
                    break;
                }

                $type = (string)($block['block_type'] ?? '');
                $content = trim((string)($block['content'] ?? ''));
                $imageUrl = trim((string)($block['image_url'] ?? ''));
                $blockId = validId($block['id'] ?? null);

                if (!in_array($type, ['heading', 'text', 'image'], true)) {
                    $invalid = true;
                    break;
                }

                if ($type === 'image') {
                    if ($imageUrl === '' || !validUrl($imageUrl)) {
                        $invalid = true;
                        break;
                    }
                    $content = '';
                } else {
                    if ($content === '') {
                        $invalid = true;
                        break;
                    }
                    $imageUrl = '';
                    $combinedText[] = $content;
                }

                $preparedBlocks[] = [
                    'id' => $blockId,
                    'block_type' => $type,
                    'content' => $content,
                    'image_url' => $imageUrl
                ];
            }

            if ($invalid) {
                $error = 'กรุณาตรวจสอบข้อมูลในแต่ละส่วนให้ครบ และตรวจสอบ URL รูปภาพ';
            } else {
                $articleData = [
                    'title' => $title,
                    'content' => implode("\n\n", $combinedText),
                    'image_url' => $coverUrl !== '' ? $coverUrl : null,
                    'is_published' => $published,
                    'content_group' => $group
                ];

                $savedResponse = null;
                $articleId = $id;

                if ($id !== null) {
                    $check = supaRequest(
                        'GET',
                        'knowledge_tips',
                        'select=id,content_group&id=eq.' . $id . '&limit=1'
                    );
                    $checkRows = supaRows($check);

                    if (!supaOk($check) || empty($checkRows) || ($checkRows[0]['content_group'] ?? '') !== $group) {
                        $error = 'ไม่พบบทความที่ต้องการแก้ไขในหมวดนี้';
                    } else {
                        $savedResponse = supaRequest(
                            'PATCH',
                            'knowledge_tips',
                            'id=eq.' . $id . '&content_group=eq.' . rawurlencode($group),
                            $articleData,
                            'return=representation'
                        );
                    }
                } else {
                    $last = supaRequest(
                        'GET',
                        'knowledge_tips',
                        'select=sort_order&content_group=eq.' . rawurlencode($group) . '&order=sort_order.desc&limit=1'
                    );

                    if (!supaOk($last)) {
                        $error = 'อ่านลำดับข้อมูลไม่สำเร็จ ตรวจสอบว่าตาราง knowledge_tips มีคอลัมน์ sort_order';
                    } else {
                        $lastRows = supaRows($last);
                        $articleData['sort_order'] = !empty($lastRows)
                            ? ((int)($lastRows[0]['sort_order'] ?? -1) + 1)
                            : 0;

                        $savedResponse = supaRequest(
                            'POST',
                            'knowledge_tips',
                            '',
                            $articleData,
                            'return=representation'
                        );
                    }
                }

                if ($error === '' && is_array($savedResponse) && supaOk($savedResponse)) {
                    $savedRows = supaRows($savedResponse);
                    if ($articleId === null) $articleId = validId($savedRows[0]['id'] ?? null);

                    if ($articleId === null) {
                        $error = 'บันทึกข้อมูลแล้ว แต่ไม่สามารถอ่าน ID ของข้อมูลกลับมาได้';
                    } else {
                        $oldResponse = supaRequest(
                            'GET',
                            'knowledge_tip_sections',
                            'select=id,knowledge_tip_id,block_type,content,image_url,alt_text,sort_order'
                            . '&knowledge_tip_id=eq.' . $articleId
                            . '&order=sort_order.asc,id.asc'
                        );

                        if (!supaOk($oldResponse)) {
                            $error = 'บันทึกข้อมูลหลักแล้ว แต่โหลดเนื้อหาย่อยเดิมไม่สำเร็จ';
                        } else {
                            $oldBlocks = supaRows($oldResponse);
                            $oldIds = array_map(static fn($row) => (int)$row['id'], $oldBlocks);

                            foreach ($preparedBlocks as $block) {
                                if ($block['id'] !== null && !in_array($block['id'], $oldIds, true)) {
                                    $error = 'พบ ID ของเนื้อหาย่อยที่ไม่ตรงกับบทความนี้';
                                    break;
                                }
                            }

                            if ($error === '') {
                                $keptOldIds = [];
                                foreach ($preparedBlocks as $position => $block) {
                                    $sectionData = [
                                        'knowledge_tip_id' => $articleId,
                                        'block_type' => $block['block_type'],
                                        'content' => $block['content'],
                                        'image_url' => $block['image_url'] !== '' ? $block['image_url'] : null,
                                        'alt_text' => null,
                                        'sort_order' => $position
                                    ];

                                    if ($block['id'] !== null) {
                                        $sectionResponse = supaRequest(
                                            'PATCH',
                                            'knowledge_tip_sections',
                                            'id=eq.' . $block['id'] . '&knowledge_tip_id=eq.' . $articleId,
                                            $sectionData,
                                            'return=minimal'
                                        );
                                        $keptOldIds[] = $block['id'];
                                    } else {
                                        $sectionResponse = supaRequest(
                                            'POST',
                                            'knowledge_tip_sections',
                                            '',
                                            $sectionData,
                                            'return=minimal'
                                        );
                                    }

                                    if (!supaOk($sectionResponse)) {
                                        error_log('MindCare save section error: ' . $sectionResponse['body']);
                                        $error = 'บันทึกเนื้อหาย่อยไม่สำเร็จ (HTTP ' . $sectionResponse['status'] . ')';
                                        break;
                                    }
                                }

                                if ($error === '') {
                                    foreach ($oldBlocks as $oldBlock) {
                                        $oldBlockId = (int)$oldBlock['id'];
                                        if (!in_array($oldBlockId, $keptOldIds, true)) {
                                            $deleteResponse = supaRequest(
                                                'DELETE',
                                                'knowledge_tip_sections',
                                                'id=eq.' . $oldBlockId . '&knowledge_tip_id=eq.' . $articleId,
                                                null,
                                                'return=minimal'
                                            );
                                            if (!supaOk($deleteResponse)) {
                                                $error = 'ลบเนื้อหาย่อยที่นำออกไม่สำเร็จ';
                                                break;
                                            }
                                        }
                                    }
                                }

                                if ($error === '') redirectGroup($group, 'saved');
                            }
                        }
                    }
                } elseif ($error === '' && is_array($savedResponse)) {
                    error_log('MindCare save article error: ' . $savedResponse['body']);
                    $error = 'บันทึกข้อมูลไม่สำเร็จ (HTTP ' . $savedResponse['status'] . ')';
                }
            }
        }
    } elseif ($action === 'delete' && $id !== null) {
        $check = supaRequest('GET', 'knowledge_tips', 'select=id,content_group&id=eq.' . $id . '&limit=1');
        $checkRows = supaRows($check);

        if (!supaOk($check) || empty($checkRows) || ($checkRows[0]['content_group'] ?? '') !== $group) {
            $error = 'ไม่พบข้อมูลในหมวดนี้';
        } else {
            $deleteSections = supaRequest(
                'DELETE',
                'knowledge_tip_sections',
                'knowledge_tip_id=eq.' . $id,
                null,
                'return=minimal'
            );

            if (supaOk($deleteSections)) {
                $deleteArticle = supaRequest(
                    'DELETE',
                    'knowledge_tips',
                    'id=eq.' . $id . '&content_group=eq.' . rawurlencode($group),
                    null,
                    'return=minimal'
                );
                if (supaOk($deleteArticle)) redirectGroup($group, 'deleted');
                $error = 'ลบข้อมูลไม่สำเร็จ (HTTP ' . $deleteArticle['status'] . ')';
            } else {
                $error = 'ลบเนื้อหาย่อยไม่สำเร็จ (HTTP ' . $deleteSections['status'] . ')';
            }
        }
    } elseif ($action === 'toggle' && $id !== null) {
        $published = (($_POST['published'] ?? '') === '1');
        $response = supaRequest(
            'PATCH',
            'knowledge_tips',
            'id=eq.' . $id . '&content_group=eq.' . rawurlencode($group),
            ['is_published' => $published],
            'return=minimal'
        );

        if (supaOk($response)) redirectGroup($group, 'updated');
        $error = 'เปลี่ยนสถานะเผยแพร่ไม่สำเร็จ';
    } elseif ($action === 'move' && $id !== null) {
        $direction = (($_POST['direction'] ?? '') === 'up') ? -1 : 1;
        $response = supaRequest(
            'GET',
            'knowledge_tips',
            'select=id,sort_order&content_group=eq.' . rawurlencode($group) . '&order=sort_order.asc,id.asc'
        );
        $rows = supaRows($response);

        if (!supaOk($response)) {
            $error = 'โหลดลำดับข้อมูลไม่สำเร็จ';
        } else {
            $index = null;
            foreach ($rows as $i => $row) {
                if ((int)$row['id'] === $id) {
                    $index = $i;
                    break;
                }
            }

            $otherIndex = $index === null ? null : $index + $direction;
            if ($otherIndex === null || !isset($rows[$otherIndex])) {
                redirectGroup($group);
            }

            $current = $rows[$index];
            $other = $rows[$otherIndex];
            $first = supaRequest(
                'PATCH',
                'knowledge_tips',
                'id=eq.' . (int)$current['id'] . '&content_group=eq.' . rawurlencode($group),
                ['sort_order' => (int)$other['sort_order']],
                'return=minimal'
            );
            $second = supaRequest(
                'PATCH',
                'knowledge_tips',
                'id=eq.' . (int)$other['id'] . '&content_group=eq.' . rawurlencode($group),
                ['sort_order' => (int)$current['sort_order']],
                'return=minimal'
            );

            if (supaOk($first) && supaOk($second)) redirectGroup($group, 'ordered');
            $error = 'จัดลำดับข้อมูลไม่สำเร็จ';
        }
    }
}

if (isset($_GET['result'])) {
    $messages = [
        'saved' => 'บันทึกข้อมูลและเนื้อหาย่อยเรียบร้อยแล้ว',
        'deleted' => 'ลบข้อมูลเรียบร้อยแล้ว',
        'updated' => 'เปลี่ยนสถานะเผยแพร่เรียบร้อยแล้ว',
        'ordered' => 'จัดลำดับข้อมูลเรียบร้อยแล้ว'
    ];
    if (isset($messages[$_GET['result']])) $notice = $messages[$_GET['result']];
}

$listResponse = supaRequest(
    'GET',
    'knowledge_tips',
    'select=id,title,content,image_url,is_published,content_group,created_at,sort_order'
    . '&content_group=eq.' . rawurlencode($group)
    . '&order=sort_order.asc,id.asc'
);

if (supaOk($listResponse)) {
    $articles = supaRows($listResponse);
} else {
    error_log('MindCare load articles error: ' . $listResponse['body']);
    $loadError = 'โหลดรายการไม่สำเร็จ (HTTP ' . $listResponse['status'] . ') ตรวจสอบการเชื่อมต่อ Supabase และคอลัมน์ sort_order';
}

$editId = validId($_GET['edit'] ?? null);
if ($editId !== null) {
    $editResponse = supaRequest(
        'GET',
        'knowledge_tips',
        'select=id,title,content,image_url,is_published,content_group,sort_order&id=eq.' . $editId . '&limit=1'
    );
    $editRows = supaRows($editResponse);

    if (supaOk($editResponse) && !empty($editRows) && ($editRows[0]['content_group'] ?? '') === $group) {
        $edit = $editRows[0];
        $blocksResponse = supaRequest(
            'GET',
            'knowledge_tip_sections',
            'select=id,knowledge_tip_id,block_type,content,image_url,alt_text,sort_order'
            . '&knowledge_tip_id=eq.' . $editId
            . '&order=sort_order.asc,id.asc'
        );

        if (supaOk($blocksResponse)) {
            $editBlocks = supaRows($blocksResponse);
            if (empty($editBlocks) && trim((string)($edit['content'] ?? '')) !== '') {
                $editBlocks[] = [
                    'id' => '',
                    'block_type' => 'text',
                    'content' => (string)$edit['content'],
                    'image_url' => ''
                ];
            }
        } else {
            $error = 'โหลดเนื้อหาย่อยไม่สำเร็จ (HTTP ' . $blocksResponse['status'] . ')';
        }
    } else {
        $error = 'ไม่พบข้อมูลที่เลือก หรือข้อมูลไม่ได้อยู่ในหมวดนี้';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $group === 'symptoms' ? 'จัดการอาการ' : 'จัดการสาเหตุ' ?> | MindCare ADMIN</title>
<style>
*{box-sizing:border-box}
:root{
    --navy:#111d43;
    --blue:#234e9b;
    --green:#16805d;
    --red:#b42318;
    --ink:#17264b;
    --muted:#64748b;
    --line:#d5e0ee;
    --bg:#f0f5fa;
}
body{
    margin:0;
    font-family:Arial,Tahoma,sans-serif;
    font-size:15px;
    line-height:1.7;
    background:var(--bg);
    color:var(--ink);
}
header{
    background:var(--navy);
    color:#fff;
    padding:20px 5%;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    flex-wrap:wrap;
}
header h1,.brand h1{
    margin:0 0 8px;
    font-size:28px;
    line-height:1.4;
    font-weight:700;
}
header p,.brand p{
    margin:0;
    color:#e0e8f7;
    font-size:14px;
    line-height:1.6;
}
header nav{
    font-size:14px;
    padding-top:5px;
}
header nav a{
    color:#fff;
    text-decoration:none;
}
header nav a:hover{text-decoration:underline}
main{
    max-width:1200px;
    margin:28px auto;
    padding:0 16px;
}
.tabs{
    display:flex;
    gap:10px;
    margin:0 0 20px;
    flex-wrap:wrap;
}
.tab{
    display:inline-block;
    padding:10px 16px;
    border-radius:8px;
    background:#e0e8f3;
    color:var(--ink);
    font-size:15px;
    font-weight:bold;
    line-height:1.4;
    text-decoration:none;
}
.tab.active{background:var(--blue);color:#fff}
.columns{
    display:grid;
    grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);
    gap:20px;
    align-items:start;
}
.panel{
    min-width:0;
    background:#fff;
    padding:22px;
    border-radius:14px;
    margin-bottom:22px;
    box-shadow:0 3px 12px #0000000a;
}
.panel h2{
    margin:0 0 14px;
    font-size:22px;
    line-height:1.5;
}
.panel h3{
    font-size:19px;
    line-height:1.5;
}
.helper,.small-note,.meta{
    color:var(--muted);
    font-size:13px;
    line-height:1.7;
}
.helper{margin:0 0 16px}
label{
    display:block;
    font-weight:bold;
    margin:12px 0 6px;
    font-size:15px;
    line-height:1.5;
}
input[type=text],input[type=url],textarea,select{
    width:100%;
    padding:11px;
    border:1px solid #cbd6e5;
    border-radius:8px;
    background:#fff;
    color:var(--ink);
    font-family:inherit;
    font-size:15px;
    line-height:1.5;
}
input:focus,textarea:focus,select:focus{
    outline:2px solid #b7cdf5;
    border-color:#7196d5;
}
textarea{min-height:120px;resize:vertical;line-height:1.8}
.section-heading{font-size:20px;margin:22px 0 12px}
.block{
    border:1px solid #cbd6e5;
    border-radius:12px;
    padding:16px;
    margin:16px 0;
    background:#fbfdff;
}
.block-top,.toolbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}
.block-top strong{font-size:15px}
.block-actions,.block-tools,.form-actions,.card-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-top:12px;
}
button,.btn{
    display:inline-flex;
    justify-content:center;
    align-items:center;
    gap:5px;
    border:0;
    border-radius:8px;
    padding:10px 14px;
    font-family:inherit;
    font-size:15px;
    font-weight:normal;
    line-height:1.4;
    cursor:pointer;
    text-decoration:none;
    white-space:nowrap;
}
.primary{background:var(--blue);color:#fff}
.green{background:var(--green);color:#fff}
.gray{background:#64748b;color:#fff}
.red{background:var(--red);color:#fff}
button:hover,.btn:hover{filter:brightness(.95)}
.publish{
    display:flex;
    align-items:center;
    gap:8px;
    margin-top:14px;
    font-weight:normal;
}
.publish input{width:16px;height:16px}
.alert{
    padding:12px 15px;
    border-radius:9px;
    margin:0 0 16px;
    background:#e0f7e9;
    color:#17663a;
    line-height:1.7;
}
.alert.error{background:#fee4e2;color:#9b1c1c}
.toolbar{margin:0 0 15px}
.toolbar strong{font-size:18px}
.article-item{
    border:1px solid #dce5ef;
    padding:16px;
    border-radius:10px;
    margin-top:14px;
    overflow-wrap:anywhere;
    background:#fff;
}
.article-item h3{
    font-size:18px;
    line-height:1.6;
    margin:10px 0;
}
.meta{margin:8px 0}
.status{
    display:inline-block;
    font-size:13px;
    padding:4px 9px;
    border-radius:20px;
    background:#e0f7e9;
    color:#17663a;
}
.status.off{background:#fee4e2;color:#9b1c1c}
.excerpt{
    font-size:14px;
    line-height:1.8;
    white-space:pre-line;
    margin:10px 0;
    color:#53627c;
    overflow-wrap:anywhere;
}
.cover-preview{
    display:block;
    max-width:220px;
    max-height:150px;
    object-fit:contain;
    margin-top:10px;
    border-radius:8px;
}
.empty{
    border:1px dashed #cbd6e5;
    border-radius:10px;
    text-align:center;
    padding:28px 15px;
    color:var(--muted);
    line-height:1.8;
}
form.inline{display:inline}
details{font-size:14px;margin:10px 0}
summary{cursor:pointer}
.small-note{font-size:13px}
@media(max-width:850px){
    .columns{grid-template-columns:1fr}
}
@media(max-width:600px){
    main{padding:0 12px;margin:18px auto}
    .panel{padding:16px}
    header{padding:18px 20px}
    header h1,.brand h1{font-size:23px}
    .block-actions button{padding:8px 9px}
    .card-actions button,.card-actions .btn{font-size:13px;padding:8px 9px}
    .tab{font-size:14px;padding:9px 12px}
}
</style>
</head>
<body>
<header>
    <div class="brand">
        <h1>MindCare ADMIN</h1>
        <p>จัดการข้อมูล<?= $group === 'symptoms' ? 'อาการ' : 'สาเหตุ' ?>ของภาวะซึมเศร้า</p>
    </div>
    <nav>
        <a href="admin_dashboard.php">← กลับแดชบอร์ด</a>
        &nbsp; | &nbsp;
        <a href="admin_logout.php">ออกจากระบบ</a>
    </nav>
</header>

<main>
    <nav class="tabs" aria-label="เลือกหมวดข้อมูล">
        <a class="tab <?= $group === 'symptoms' ? 'active' : '' ?>" href="admin_symptoms.php?group=symptoms">จัดการอาการ</a>
        <a class="tab <?= $group === 'causes' ? 'active' : '' ?>" href="admin_symptoms.php?group=causes">จัดการสาเหตุ</a>
    </nav>

    <?php if ($notice !== ''): ?><div class="alert"><?= h($notice) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($loadError !== ''): ?><div class="alert error"><?= h($loadError) ?></div><?php endif; ?>

    <div class="columns">
        <!-- ฟอร์มเพิ่ม/แก้ไขด้านซ้าย -->
        <section class="panel" id="form-panel">
            <h2><?= $edit ? 'แก้ไขข้อมูล' : 'เพิ่มข้อมูล' ?><?= $group === 'symptoms' ? 'อาการ' : 'สาเหตุ' ?></h2>
            <p class="helper">เพิ่มชื่อเรื่อง รูปปก และเนื้อหาเป็นส่วน ๆ สามารถเพิ่มหัวข้อ ข้อความ และรูปภาพ แล้วจัดลำดับเนื้อหาได้</p>

            <form method="post" id="articleForm">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['symptoms_csrf']) ?>">
                <input type="hidden" name="group" value="<?= h($group) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : '' ?>">

                <label for="title">ชื่อเรื่อง</label>
                <input id="title" type="text" name="title" required maxlength="250"
                       value="<?= h($edit['title'] ?? '') ?>"
                       placeholder="<?= $group === 'symptoms' ? 'เช่น อาการด้านอารมณ์' : 'เช่น ปัจจัยด้านพันธุกรรม' ?>">

                <label for="image_url">URL รูปปก (ไม่บังคับ)</label>
                <input id="image_url" type="url" name="image_url"
                       value="<?= h($edit['image_url'] ?? '') ?>"
                       placeholder="https://example.com/cover.jpg">
                <?php if (!empty($edit['image_url'])): ?>
                    <img class="cover-preview" src="<?= h($edit['image_url']) ?>" alt="ตัวอย่างรูปปก" onerror="this.style.display='none'">
                <?php endif; ?>

                <h3 class="section-heading">เนื้อหา</h3>
                <div id="blocksContainer"></div>

                <div class="block-tools">
                    <button type="button" class="green" onclick="addBlock('heading')">＋ เพิ่มหัวข้อ</button>
                    <button type="button" class="green" onclick="addBlock('text')">＋ เพิ่มข้อความ</button>
                    <button type="button" class="green" onclick="addBlock('image')">＋ เพิ่มรูปภาพ</button>
                </div>

                <label class="publish">
                    <input type="checkbox" name="is_published" value="1"
                        <?= !isset($edit['is_published']) || !empty($edit['is_published']) ? 'checked' : '' ?>>
                    เผยแพร่บนเว็บไซต์
                </label>

                <div class="form-actions">
                    <button type="submit" class="primary"><?= $edit ? 'บันทึกการแก้ไข' : 'สร้างข้อมูล' ?></button>
                    <?php if ($edit): ?>
                        <a class="btn gray" href="admin_symptoms.php?group=<?= h($group) ?>">ยกเลิก</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- รายการข้อมูลด้านขวา -->
        <section class="panel">
            <div class="toolbar">
                <strong>รายการ<?= $group === 'symptoms' ? 'อาการ' : 'สาเหตุ' ?></strong>
                <span class="small-note">ทั้งหมด <?= count($articles) ?> รายการ</span>
            </div>

            <?php if (empty($articles) && $loadError === ''): ?>
                <div class="empty">ยังไม่มีข้อมูลในหมวดนี้<br>เพิ่มข้อมูลรายการแรกได้จากแบบฟอร์มด้านซ้าย</div>
            <?php endif; ?>

            <?php foreach ($articles as $article): ?>
                <?php
                    $excerpt = trim((string)($article['content'] ?? ''));
                    if (function_exists('mb_strlen') && mb_strlen($excerpt, 'UTF-8') > 180) {
                        $excerpt = mb_substr($excerpt, 0, 180, 'UTF-8') . '…';
                    } elseif (strlen($excerpt) > 180 && !function_exists('mb_strlen')) {
                        $excerpt = substr($excerpt, 0, 180) . '…';
                    }
                ?>
                <article class="article-item">
                    <span class="status <?= !empty($article['is_published']) ? '' : 'off' ?>">
                        <?= !empty($article['is_published']) ? 'เผยแพร่แล้ว' : 'ฉบับร่าง' ?>
                    </span>
                    <h3><?= h($article['title'] ?? '') ?></h3>
                    <p class="meta">ประเภท: <?= $group === 'symptoms' ? 'อาการ' : 'สาเหตุ' ?></p>
                    <p class="meta">สถานะ: <?= !empty($article['is_published']) ? 'เผยแพร่แล้ว' : 'ซ่อนอยู่' ?></p>

                    <?php if (!empty($article['content'])): ?>
                        <details>
                            <summary>ดูเนื้อหาสรุป</summary>
                            <p class="excerpt"><?= h($excerpt) ?></p>
                        </details>
                    <?php else: ?>
                        <p class="excerpt">ยังไม่มีเนื้อหาสรุป</p>
                    <?php endif; ?>

                    <p class="meta">ลำดับ <?= (int)($article['sort_order'] ?? 0) ?> · ID <?= (int)$article['id'] ?></p>

                    <div class="card-actions">
                        <a class="btn primary" href="admin_symptoms.php?group=<?= h($group) ?>&edit=<?= (int)$article['id'] ?>#form-panel">แก้ไขเนื้อหา</a>

                        <form class="inline" method="post">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['symptoms_csrf']) ?>">
                            <input type="hidden" name="group" value="<?= h($group) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                            <input type="hidden" name="published" value="<?= !empty($article['is_published']) ? '0' : '1' ?>">
                            <button type="submit" class="gray"><?= !empty($article['is_published']) ? 'ซ่อน' : 'เผยแพร่' ?></button>
                        </form>

                        <form class="inline" method="post" onsubmit="return confirm('ยืนยันลบข้อมูลนี้และเนื้อหาย่อยทั้งหมดหรือไม่?')">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['symptoms_csrf']) ?>">
                            <input type="hidden" name="group" value="<?= h($group) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                            <button type="submit" class="red">ลบ</button>
                        </form>

                        <form class="inline" method="post">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['symptoms_csrf']) ?>">
                            <input type="hidden" name="group" value="<?= h($group) ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="gray" title="เลื่อนขึ้น" aria-label="เลื่อนขึ้น">↑</button>
                        </form>

                        <form class="inline" method="post">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['symptoms_csrf']) ?>">
                            <input type="hidden" name="group" value="<?= h($group) ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="gray" title="เลื่อนลง" aria-label="เลื่อนลง">↓</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
</main>

<script>
const initialBlocks = <?= json_encode(array_map(static function ($block) {
    return [
        'id' => $block['id'] ?? '',
        'block_type' => $block['block_type'] ?? 'text',
        'content' => $block['content'] ?? '',
        'image_url' => $block['image_url'] ?? ''
    ];
}, $editBlocks), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

const container = document.getElementById('blocksContainer');

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
    }[ch]));
}

function reindexBlocks() {
    [...container.querySelectorAll('.block')].forEach((block, index) => {
        block.querySelectorAll('[data-field]').forEach(field => {
            field.name = `blocks[${index}][${field.dataset.field}]`;
        });
        block.querySelector('.block-number').textContent = `ส่วนเนื้อหาที่ ${index + 1}`;
    });
}

function addBlock(type, data = {}) {
    // แสดงส่วนเนื้อหาเฉพาะเมื่อผู้ดูแลกดเพิ่ม หรือเปิดแก้ไขข้อมูลเดิม
    container.hidden = false;
    const block = document.createElement('div');
    block.className = 'block';

    let field = '';
    if (type === 'heading') {
        field = `<label>หัวข้อ *</label>
                 <input type="text" data-field="content" value="${escapeHtml(data.content || '')}" placeholder="พิมพ์หัวข้อ" required>`;
    } else if (type === 'text') {
        field = `<label>ข้อความ *</label>
                 <textarea data-field="content" placeholder="พิมพ์เนื้อหาที่ต้องการ" required>${escapeHtml(data.content || '')}</textarea>`;
    } else {
        field = `<label>URL รูปภาพ *</label>
                 <input type="url" data-field="image_url" value="${escapeHtml(data.image_url || '')}" placeholder="https://example.com/image.jpg" required>
                 <img class="cover-preview" alt="ตัวอย่างรูปภาพ" hidden>`;
    }

    block.innerHTML = `
        <input type="hidden" data-field="id" value="${escapeHtml(data.id || '')}">
        <input type="hidden" data-field="block_type" value="${escapeHtml(type)}">
        <div class="block-top">
            <strong class="block-number"></strong>
            <div class="block-actions">
                <button type="button" class="gray" onclick="moveBlock(this,-1)">↑ ขึ้น</button>
                <button type="button" class="gray" onclick="moveBlock(this,1)">↓ ลง</button>
                <button type="button" class="red" onclick="removeBlock(this)">ลบส่วนนี้</button>
            </div>
        </div>
        ${field}
    `;

    container.appendChild(block);
    reindexBlocks();
    const input = block.querySelector('[data-field="image_url"]');
    if (input && input.value) updateImagePreview(input);
}

function moveBlock(button, direction) {
    const block = button.closest('.block');
    if (direction < 0 && block.previousElementSibling) {
        container.insertBefore(block, block.previousElementSibling);
    } else if (direction > 0 && block.nextElementSibling) {
        container.insertBefore(block.nextElementSibling, block);
    }
    reindexBlocks();
}

function removeBlock(button) {
    if (confirm('ต้องการลบส่วนเนื้อหานี้หรือไม่?')) {
        button.closest('.block').remove();
        reindexBlocks();
    }
}

function updateImagePreview(input) {
    const preview = input.parentElement.querySelector('img.cover-preview');
    if (!preview) return;
    const url = input.value.trim();

    if (!url) {
        preview.removeAttribute('src');
        preview.hidden = true;
        return;
    }

    preview.onload = () => { preview.hidden = false; };
    preview.onerror = () => { preview.hidden = true; };
    preview.src = url;
}

container.addEventListener('input', event => {
    if (event.target.matches('[data-field="image_url"]')) updateImagePreview(event.target);
});

// ตอนเพิ่มข้อมูลใหม่ ซ่อนพื้นที่บล็อกไว้ก่อน ไม่สร้าง “ส่วนเนื้อหาที่ 1” เอง
// พื้นที่จะแสดงเมื่อผู้ดูแลกด “เพิ่มหัวข้อ / เพิ่มข้อความ / เพิ่มรูปภาพ”
container.hidden = initialBlocks.length === 0;
initialBlocks.forEach(block => addBlock(block.block_type || 'text', block));

document.getElementById('articleForm').addEventListener('submit', event => {
    const blocks = [...container.querySelectorAll('.block')];
    for (const block of blocks) {
        const type = block.querySelector('[data-field="block_type"]').value;
        const content = block.querySelector('[data-field="content"]');
        const image = block.querySelector('[data-field="image_url"]');

        if (type === 'image' && image && !image.value.trim()) {
            event.preventDefault();
            image.focus();
            alert('กรุณากรอก URL รูปภาพในส่วนเนื้อหา');
            return;
        }
        if (type !== 'image' && content && !content.value.trim()) {
            event.preventDefault();
            content.focus();
            alert('กรุณากรอกข้อความในส่วนเนื้อหา');
            return;
        }
    }
});
</script>
</body>
</html>
