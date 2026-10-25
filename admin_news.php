<?php
session_start();
require_once __DIR__ . '/supabase_config.php';

/*
 * MindCare ADMIN: จัดการข่าวสาร
 * ใช้ตาราง public.news โดยตรง
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

if (empty($_SESSION['news_csrf'])) {
    $_SESSION['news_csrf'] = bin2hex(random_bytes(32));
}

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
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

function supaRequest(string $method, string $query = '', ?array $data = null, ?string $prefer = null): array {
    if (
        !defined('SUPABASE_URL') ||
        !defined('SUPABASE_SECRET_KEY') ||
        trim((string)SUPABASE_URL) === '' ||
        trim((string)SUPABASE_SECRET_KEY) === ''
    ) {
        return ['status' => 500, 'body' => 'ไม่พบ SUPABASE_URL หรือ SUPABASE_SECRET_KEY ใน supabase_config.php'];
    }

    $url = rtrim((string)SUPABASE_URL, '/') . '/rest/v1/news';
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
        error_log('MindCare news request error: ' . $error);
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

function redirectNews(string $result = ''): void {
    $url = 'admin_news.php';
    if ($result !== '') $url .= '?' . http_build_query(['result' => $result]);
    header('Location: ' . $url);
    exit;
}

$error = '';
$notice = '';
$loadError = '';
$articles = [];
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals((string)$_SESSION['news_csrf'], (string)$_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณากลับไปโหลดหน้าใหม่');
    }

    $action = (string)($_POST['action'] ?? '');
    $id = validId($_POST['id'] ?? null);

    if ($action === 'save') {
        $title = trim((string)($_POST['title'] ?? ''));
        $summary = trim((string)($_POST['summary'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $imageUrl = trim((string)($_POST['image_url'] ?? ''));
        $sourceUrl = trim((string)($_POST['source_url'] ?? ''));
        $youtubeUrl = trim((string)($_POST['youtube_url'] ?? ''));
        $tiktokUrl = trim((string)($_POST['tiktok_url'] ?? ''));
        $facebookUrl = trim((string)($_POST['facebook_url'] ?? ''));
        $instagramUrl = trim((string)($_POST['instagram_url'] ?? ''));
        $otherVideoUrl = trim((string)($_POST['other_video_url'] ?? ''));
        $category = trim((string)($_POST['category'] ?? 'ข่าวสาร'));
        $published = isset($_POST['is_published']);
        $publishedAtInput = trim((string)($_POST['published_at'] ?? ''));

        if ($title === '') {
            $error = 'กรุณากรอกหัวข้อข่าว';
        } elseif ($content === '') {
            $error = 'กรุณากรอกเนื้อหาข่าว';
        } elseif (!validUrl($imageUrl)) {
            $error = 'URL รูปปกไม่ถูกต้อง ต้องเป็นลิงก์ http:// หรือ https://';
        } elseif (!validUrl($sourceUrl) || !validUrl($youtubeUrl) || !validUrl($tiktokUrl) || !validUrl($facebookUrl) || !validUrl($instagramUrl) || !validUrl($otherVideoUrl)) {
            $error = 'URL แหล่งข่าวหรือคลิปไม่ถูกต้อง ต้องเป็นลิงก์ http:// หรือ https://';
        } elseif (function_exists('mb_strlen') && mb_strlen($title, 'UTF-8') > 250) {
            $error = 'หัวข้อข่าวยาวเกิน 250 ตัวอักษร';
        } else {
            $publishedAt = null;
            if ($publishedAtInput !== '') {
                $timestamp = strtotime($publishedAtInput);
                if ($timestamp === false) {
                    $error = 'รูปแบบวันที่เผยแพร่ไม่ถูกต้อง';
                } else {
                    $publishedAt = date('c', $timestamp);
                }
            } elseif ($published) {
                $publishedAt = date('c');
            }

            if ($error === '') {
                $articleData = [
                    'title' => $title,
                    'summary' => $summary !== '' ? $summary : null,
                    'content' => $content,
                    'image_url' => $imageUrl !== '' ? $imageUrl : null,
                    'source_url' => $sourceUrl !== '' ? $sourceUrl : null,
                    'youtube_url' => $youtubeUrl !== '' ? $youtubeUrl : null,
                    'tiktok_url' => $tiktokUrl !== '' ? $tiktokUrl : null,
                    'facebook_url' => $facebookUrl !== '' ? $facebookUrl : null,
                    'instagram_url' => $instagramUrl !== '' ? $instagramUrl : null,
                    'other_video_url' => $otherVideoUrl !== '' ? $otherVideoUrl : null,
                    'category' => $category !== '' ? $category : 'ข่าวสาร',
                    'is_published' => $published,
                    'published_at' => $publishedAt
                ];

                if ($id !== null) {
                    $check = supaRequest('GET', 'select=id&id=eq.' . $id . '&limit=1');
                    $checkRows = supaRows($check);

                    if (!supaOk($check) || empty($checkRows)) {
                        $error = 'ไม่พบข่าวที่ต้องการแก้ไข';
                    } else {
                        $savedResponse = supaRequest(
                            'PATCH',
                            'id=eq.' . $id,
                            $articleData,
                            'return=representation'
                        );
                    }
                } else {
                    $last = supaRequest('GET', 'select=sort_order&order=sort_order.desc,id.desc&limit=1');
                    if (!supaOk($last)) {
                        $error = 'อ่านลำดับข่าวไม่สำเร็จ ตรวจสอบว่าตาราง news มีคอลัมน์ sort_order';
                    } else {
                        $lastRows = supaRows($last);
                        $articleData['sort_order'] = !empty($lastRows)
                            ? ((int)($lastRows[0]['sort_order'] ?? -1) + 1)
                            : 0;
                        $savedResponse = supaRequest('POST', '', $articleData, 'return=representation');
                    }
                }

                if ($error === '' && isset($savedResponse) && supaOk($savedResponse)) {
                    redirectNews('saved');
                } elseif ($error === '' && isset($savedResponse)) {
                    error_log('MindCare save news error: ' . $savedResponse['body']);
                    $error = 'บันทึกข่าวไม่สำเร็จ (HTTP ' . $savedResponse['status'] . ')';
                }
            }
        }
    } elseif ($action === 'delete' && $id !== null) {
        $response = supaRequest('DELETE', 'id=eq.' . $id, null, 'return=minimal');
        if (supaOk($response)) {
            redirectNews('deleted');
        }
        error_log('MindCare delete news error: ' . $response['body']);
        $error = 'ลบข่าวไม่สำเร็จ (HTTP ' . $response['status'] . ')';
    } elseif ($action === 'toggle' && $id !== null) {
        $published = (($_POST['published'] ?? '') === '1');
        $data = ['is_published' => $published];
        if ($published) $data['published_at'] = date('c');

        $response = supaRequest('PATCH', 'id=eq.' . $id, $data, 'return=minimal');
        if (supaOk($response)) redirectNews('updated');
        $error = 'เปลี่ยนสถานะเผยแพร่ไม่สำเร็จ';
    } elseif ($action === 'move' && $id !== null) {
        $direction = (($_POST['direction'] ?? '') === 'up') ? -1 : 1;
        $response = supaRequest('GET', 'select=id,sort_order&order=sort_order.asc,id.asc');
        $rows = supaRows($response);

        if (!supaOk($response)) {
            $error = 'โหลดลำดับข่าวไม่สำเร็จ';
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
                redirectNews();
            }

            $current = $rows[$index];
            $other = $rows[$otherIndex];
            $first = supaRequest('PATCH', 'id=eq.' . (int)$current['id'], ['sort_order' => (int)$other['sort_order']], 'return=minimal');
            $second = supaRequest('PATCH', 'id=eq.' . (int)$other['id'], ['sort_order' => (int)$current['sort_order']], 'return=minimal');

            if (supaOk($first) && supaOk($second)) redirectNews('ordered');
            $error = 'จัดลำดับข่าวไม่สำเร็จ';
        }
    }
}

if (isset($_GET['result'])) {
    $messages = [
        'saved' => 'บันทึกข่าวเรียบร้อยแล้ว',
        'deleted' => 'ลบข่าวเรียบร้อยแล้ว',
        'updated' => 'เปลี่ยนสถานะเผยแพร่เรียบร้อยแล้ว',
        'ordered' => 'จัดลำดับข่าวเรียบร้อยแล้ว'
    ];
    if (isset($messages[$_GET['result']])) $notice = $messages[$_GET['result']];
}

$listResponse = supaRequest(
    'GET',
    'select=id,title,summary,content,image_url,source_url,youtube_url,tiktok_url,facebook_url,instagram_url,other_video_url,category,is_published,published_at,created_at,sort_order&order=sort_order.asc,id.asc'
);

if (supaOk($listResponse)) {
    $articles = supaRows($listResponse);
} else {
    error_log('MindCare load news error: ' . $listResponse['body']);
    $loadError = 'โหลดรายการข่าวไม่สำเร็จ (HTTP ' . $listResponse['status'] . ') ตรวจสอบตาราง news และการเชื่อมต่อ Supabase';
}

$editId = validId($_GET['edit'] ?? null);
if ($editId !== null) {
    $editResponse = supaRequest(
        'GET',
        'select=id,title,summary,content,image_url,source_url,youtube_url,tiktok_url,facebook_url,instagram_url,other_video_url,category,is_published,published_at,sort_order&id=eq.' . $editId . '&limit=1'
    );
    $editRows = supaRows($editResponse);

    if (supaOk($editResponse) && !empty($editRows)) {
        $edit = $editRows[0];
    } else {
        $error = 'ไม่พบข่าวที่เลือกแก้ไข';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>จัดการข่าวสาร | MindCare ADMIN</title>
<style>
:root{--navy:#111d43;--blue:#234e9b;--green:#16805d;--red:#bd251b;--ink:#17264b;--muted:#64748b;--line:#d5e0ee;--bg:#f0f5fa}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Tahoma,sans-serif;font-size:15px;line-height:1.6}
header{background:var(--navy);color:#fff;padding:20px max(22px,calc((100% - 1360px)/2));display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}
.brand h1{font-size:28px;margin:0 0 8px;font-weight:700;line-height:1.4}
.brand p{margin:0;font-size:14px;color:#e0e8f7}
nav{font-size:14px;padding-top:5px}
nav a{color:white;text-decoration:none}
nav a:hover{text-decoration:underline}
main{max-width:1360px;margin:26px auto;padding:0 18px}
.columns{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:18px;align-items:start}
.panel{background:#fff;padding:22px;border-radius:14px;box-shadow:0 3px 14px #15294b0b;min-width:0}
.panel h2{font-size:21px;margin:8px 0 14px;line-height:1.5}
.helper{font-size:13px;color:var(--muted);line-height:1.7;margin:0 0 16px}
label{display:block;font-weight:bold;margin:12px 0 6px;font-size:14px}
input[type=text],input[type=url],input[type=datetime-local],textarea,select{width:100%;padding:10px 11px;border:1px solid #cbd8e8;border-radius:8px;background:#fff;color:var(--ink);font:inherit;font-size:14px}
input:focus,textarea:focus,select:focus{outline:2px solid #b7cdf5;border-color:#7196d5}
textarea{min-height:110px;resize:vertical;line-height:1.7}
button,.btn{display:inline-flex;justify-content:center;align-items:center;gap:5px;border:0;border-radius:8px;padding:9px 13px;font:inherit;font-size:13px;font-weight:bold;cursor:pointer;text-decoration:none;white-space:nowrap}
.primary{background:var(--blue);color:#fff}.green{background:var(--green);color:#fff}.gray{background:#64748b;color:#fff}.red{background:var(--red);color:#fff}
button:hover,.btn:hover{filter:brightness(.95)}
.form-actions,.card-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
.publish{display:flex;align-items:center;gap:8px;margin-top:14px;font-weight:normal}
.publish input{width:16px;height:16px}
.alert{padding:12px 15px;border-radius:9px;margin:0 0 16px;background:#e0f7e9;color:#17663a;line-height:1.6}
.alert.error{background:#fee4e2;color:#9b1c1c}
.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 15px}
.toolbar strong{font-size:18px}
.article-item{border:1px solid #d8e3f0;border-radius:11px;padding:14px;margin:12px 0;overflow-wrap:anywhere;background:#fff}
.article-item h3{font-size:17px;line-height:1.6;margin:10px 0}
.meta{font-size:12px;color:var(--muted);margin:7px 0;line-height:1.6}
.status{display:inline-block;font-size:12px;padding:4px 9px;border-radius:20px;background:#e0f7e9;color:#17663a}
.status.off{background:#fee4e2;color:#9b1c1c}
.excerpt{font-size:14px;line-height:1.7;white-space:pre-line;margin:10px 0;color:#53627c;overflow-wrap:anywhere}
.cover-preview{display:block;max-width:230px;max-height:150px;object-fit:contain;margin-top:10px;border-radius:8px}
.empty{border:1px dashed #cbd8e8;border-radius:10px;text-align:center;padding:28px 15px;color:var(--muted);line-height:1.8}
form.inline{display:inline}
.small-note{font-size:12px;color:var(--muted);line-height:1.7}
@media(max-width:900px){.columns{grid-template-columns:1fr}header{padding:18px 20px}}
@media(max-width:560px){main{padding:0 12px;margin:18px auto}.panel{padding:16px}.brand h1{font-size:23px}.card-actions button,.card-actions .btn{font-size:12px;padding:8px 9px}}
</style>
</head>
<body>
<header>
    <div class="brand">
        <h1>MindCare ADMIN</h1>
        <p>จัดการข่าวสารและบทความของเว็บไซต์</p>
    </div>
    <nav>
        <a href="admin_dashboard.php">← กลับแดชบอร์ด</a>
        &nbsp; | &nbsp;
        <a href="admin_logout.php">ออกจากระบบ</a>
    </nav>
</header>

<main>
    <?php if ($notice !== ''): ?><div class="alert"><?= h($notice) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($loadError !== ''): ?><div class="alert error"><?= h($loadError) ?></div><?php endif; ?>

    <div class="columns">
        <section class="panel" id="form-panel">
            <h2><?= $edit ? 'แก้ไขข่าวสาร' : 'เพิ่มข่าวสาร' ?></h2>
            <p class="helper">กรอกหัวข้อ คำโปรย เนื้อหาข่าว รูปปก แหล่งข่าว และลิงก์คลิปจากแพลตฟอร์มต่าง ๆ ข่าวที่เผยแพร่แล้วจะแสดงบนหน้า news.php</p>

            <form method="post" id="newsForm">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['news_csrf']) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : '' ?>">

                <label for="title">หัวข้อข่าว *</label>
                <input id="title" type="text" name="title" required maxlength="250"
                       value="<?= h($edit['title'] ?? '') ?>" placeholder="พิมพ์หัวข้อข่าว">

                <label for="summary">คำโปรย / สรุปข่าว</label>
                <textarea id="summary" name="summary" rows="3" placeholder="ข้อความสั้นที่แสดงบนการ์ดข่าว"><?= h($edit['summary'] ?? '') ?></textarea>

                <label for="content">เนื้อหาข่าว *</label>
                <textarea id="content" name="content" rows="9" required placeholder="พิมพ์เนื้อหาข่าวฉบับเต็ม"><?= h($edit['content'] ?? '') ?></textarea>

                <label for="image_url">URL รูปปก</label>
                <input id="image_url" type="url" name="image_url" value="<?= h($edit['image_url'] ?? '') ?>"
                       placeholder="https://example.com/news-image.jpg">
                <img id="imagePreview" class="cover-preview" alt="ตัวอย่างรูปปก"
                     src="<?= h($edit['image_url'] ?? '') ?>"
                     style="<?= !empty($edit['image_url']) ? '' : 'display:none' ?>"
                     onerror="this.style.display='none'">

                <label for="category">หมวดข่าว</label>
                <input id="category" type="text" name="category" maxlength="100"
                       value="<?= h($edit['category'] ?? 'ข่าวสาร') ?>" placeholder="เช่น สุขภาพจิต ข่าวประชาสัมพันธ์">

                <label for="source_url">URL แหล่งข่าว (ถ้ามี)</label>
                <input id="source_url" type="url" name="source_url" value="<?= h($edit['source_url'] ?? '') ?>"
                       placeholder="https://example.com/source">

                <h3 class="section-heading">ลิงก์คลิปวิดีโอ (ไม่บังคับ)</h3>
                <p class="helper">กรอกเฉพาะแพลตฟอร์มที่มีคลิป ไม่จำเป็นต้องกรอกทุกช่อง</p>
                <label for="youtube_url">YouTube</label>
                <input id="youtube_url" type="url" name="youtube_url" value="<?= h($edit['youtube_url'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=...">
                <label for="tiktok_url">TikTok</label>
                <input id="tiktok_url" type="url" name="tiktok_url" value="<?= h($edit['tiktok_url'] ?? '') ?>" placeholder="https://www.tiktok.com/@.../video/...">
                <label for="facebook_url">Facebook / Facebook Watch</label>
                <input id="facebook_url" type="url" name="facebook_url" value="<?= h($edit['facebook_url'] ?? '') ?>" placeholder="https://www.facebook.com/...">
                <label for="instagram_url">Instagram / Reels</label>
                <input id="instagram_url" type="url" name="instagram_url" value="<?= h($edit['instagram_url'] ?? '') ?>" placeholder="https://www.instagram.com/reel/...">
                <label for="other_video_url">แพลตฟอร์มอื่น (เช่น Vimeo)</label>
                <input id="other_video_url" type="url" name="other_video_url" value="<?= h($edit['other_video_url'] ?? '') ?>" placeholder="https://...">

                <label for="published_at">วันที่เผยแพร่ (ไม่บังคับ)</label>
                <input id="published_at" type="datetime-local" name="published_at"
                       value="<?php
                           $dateValue = (string)($edit['published_at'] ?? '');
                           echo $dateValue !== '' ? h(date('Y-m-d\TH:i', strtotime($dateValue))) : '';
                       ?>">

                <label class="publish">
                    <input type="checkbox" name="is_published" value="1"
                        <?= !isset($edit['is_published']) || !empty($edit['is_published']) ? 'checked' : '' ?>>
                    เผยแพร่บนเว็บไซต์
                </label>

                <div class="form-actions">
                    <button type="submit" class="primary"><?= $edit ? 'บันทึกการแก้ไข' : 'เพิ่มข่าว' ?></button>
                    <?php if ($edit): ?>
                        <a class="btn gray" href="admin_news.php">ยกเลิก</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="toolbar">
                <strong>รายการข่าวสาร</strong>
                <span class="small-note">ทั้งหมด <?= count($articles) ?> รายการ</span>
            </div>

            <?php if (empty($articles) && $loadError === ''): ?>
                <div class="empty">ยังไม่มีข่าวสาร<br>เพิ่มข่าวรายการแรกได้จากแบบฟอร์มด้านซ้าย</div>
            <?php endif; ?>

            <?php foreach ($articles as $article): ?>
                <?php
                    $excerpt = trim((string)($article['summary'] ?? $article['content'] ?? ''));
                    if (function_exists('mb_strlen') && mb_strlen($excerpt, 'UTF-8') > 180) {
                        $excerpt = mb_substr($excerpt, 0, 180, 'UTF-8') . '…';
                    } elseif (!function_exists('mb_strlen') && strlen($excerpt) > 180) {
                        $excerpt = substr($excerpt, 0, 180) . '…';
                    }
                ?>
                <article class="article-item">
                    <span class="status <?= !empty($article['is_published']) ? '' : 'off' ?>">
                        <?= !empty($article['is_published']) ? 'เผยแพร่แล้ว' : 'ฉบับร่าง' ?>
                    </span>
                    <h3><?= h($article['title'] ?? '') ?></h3>
                    <p class="meta">หมวด: <?= h($article['category'] ?? 'ข่าวสาร') ?></p>
                    <p class="meta">วันที่เผยแพร่: <?= !empty($article['published_at']) ? h(date('d/m/Y H:i', strtotime($article['published_at']))) : 'ยังไม่ได้กำหนด' ?></p>
                    <p class="excerpt"><?= h($excerpt !== '' ? $excerpt : 'ยังไม่มีคำโปรยหรือเนื้อหาสรุป') ?></p>
                    <?php if (!empty($article['image_url'])): ?>
                        <img class="cover-preview" src="<?= h($article['image_url']) ?>" alt="รูปปกข่าว" loading="lazy" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <?php if (!empty($article['source_url'])): ?>
                        <p class="meta"><a href="<?= h($article['source_url']) ?>" target="_blank" rel="noopener noreferrer">เปิดแหล่งข่าว</a></p>
                    <?php endif; ?>
                    <?php foreach (['youtube_url' => 'YouTube', 'tiktok_url' => 'TikTok', 'facebook_url' => 'Facebook', 'instagram_url' => 'Instagram', 'other_video_url' => 'วิดีโออื่น'] as $videoField => $videoLabel): ?>
                        <?php if (!empty($article[$videoField])): ?>
                            <p class="meta"><a href="<?= h($article[$videoField]) ?>" target="_blank" rel="noopener noreferrer">เปิดคลิป <?= h($videoLabel) ?> ↗</a></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <p class="meta">ลำดับ <?= (int)($article['sort_order'] ?? 0) ?> · ID <?= (int)$article['id'] ?></p>

                    <div class="card-actions">
                        <a class="btn primary" href="admin_news.php?edit=<?= (int)$article['id'] ?>#form-panel">แก้ไข</a>

                        <form class="inline" method="post">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['news_csrf']) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                            <input type="hidden" name="published" value="<?= !empty($article['is_published']) ? '0' : '1' ?>">
                            <button type="submit" class="gray"><?= !empty($article['is_published']) ? 'ซ่อน' : 'เผยแพร่' ?></button>
                        </form>

                        <form class="inline" method="post" onsubmit="return confirm('ยืนยันลบข่าวนี้หรือไม่?')">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['news_csrf']) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                            <button type="submit" class="red">ลบ</button>
                        </form>

                        <form class="inline" method="post">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['news_csrf']) ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="gray" title="เลื่อนขึ้น" aria-label="เลื่อนขึ้น">↑</button>
                        </form>

                        <form class="inline" method="post">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['news_csrf']) ?>">
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
const imageInput = document.getElementById('image_url');
const imagePreview = document.getElementById('imagePreview');

imageInput.addEventListener('input', () => {
    const url = imageInput.value.trim();
    if (!url) {
        imagePreview.removeAttribute('src');
        imagePreview.style.display = 'none';
        return;
    }
    imagePreview.onload = () => { imagePreview.style.display = 'block'; };
    imagePreview.onerror = () => { imagePreview.style.display = 'none'; };
    imagePreview.src = url;
});
</script>
</body>
</html>
