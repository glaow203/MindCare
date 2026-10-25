<?php
session_start();
require_once __DIR__ . '/supabase_config.php';

/* =========================
   ตรวจสอบสิทธิ์ผู้ดูแลระบบ
========================= */
if (
    empty($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    strtolower($_SESSION['admin_email'] ?? '') !== strtolower(ADMIN_EMAIL)
) {
    header('Location: admin.php');
    exit;
}

if (empty($_SESSION['tips_csrf'])) {
    $_SESSION['tips_csrf'] = bin2hex(random_bytes(32));
}

/* =========================
   ฟังก์ชันพื้นฐาน
========================= */
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function idValue($value) {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return ($id !== false && $id > 0) ? $id : null;
}

function validUrl($url) {
    if ($url === '') {
        return true;
    }

    return filter_var($url, FILTER_VALIDATE_URL)
        && in_array(
            strtolower((string)parse_url($url, PHP_URL_SCHEME)),
            ['http', 'https'],
            true
        );
}

function ok($response) {
    return $response['status'] >= 200 && $response['status'] < 300;
}

function rows($response) {
    $data = json_decode($response['body'], true);
    return is_array($data) ? $data : [];
}

function redirectTo($query = '') {
    global $pageGroup;

    $params = ['group' => $pageGroup];

    if ($query !== '') {
        parse_str($query, $extraParams);
        $params = array_merge($params, $extraParams);
    }

    header('Location: admin_tips.php?' . http_build_query($params));
    exit;
}

/* =========================
   เชื่อมต่อ Supabase REST API
========================= */
function requestSupabase(
    $method,
    $table,
    $query = '',
    $data = null,
    $prefer = null
) {
    $allowed = [
        'knowledge_tips',
        'knowledge_tip_sections'
    ];

    if (!in_array($table, $allowed, true)) {
        return ['status' => 400, 'body' => 'Invalid table'];
    }

    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . $table;

    if ($query !== '') {
        $url .= '?' . $query;
    }

    $key = trim(SUPABASE_SECRET_KEY);

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
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return ['status' => 500, 'body' => 'JSON encoding failed'];
        }

        $options[CURLOPT_POSTFIELDS] = $json;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);

        error_log('Supabase connection error: ' . $error);

        return ['status' => 500, 'body' => $error];
    }

    curl_close($ch);

    return [
        'status' => $status,
        'body' => $body
    ];
}

/* =========================
   ตัวแปรสำหรับหน้าเว็บ
========================= */

$pageGroup = $_GET['group'] ?? $_POST['group'] ?? 'tips';

if (!in_array($pageGroup, ['tips', 'depression_intro'], true)) {
    $pageGroup = 'tips';
}

$message = '';
$messageType = 'success';
$editTip = null;
$blocks = [];
$tips = [];
$loadError = '';


/* =========================
   รับข้อมูลจากแบบฟอร์ม
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['tips_csrf'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณากลับไปโหลดหน้าใหม่');
    }

    $action = $_POST['action'] ?? '';
    $tipId = idValue($_POST['id'] ?? null);

    /* ---------- บันทึกบทความ ---------- */
    if ($action === 'save') {
        $title = trim($_POST['title'] ?? '');
$cover = trim($_POST['image_url'] ?? '');
$published = isset($_POST['is_published']);

$contentGroup = $_POST['group'] ?? 'depression_intro';

if (!in_array($contentGroup, ['tips', 'depression_intro'], true)) {
    $contentGroup = 'invalid';
}
$submittedBlocks = $_POST['blocks'] ?? [];

if (!is_array($submittedBlocks)) {
    $submittedBlocks = [];
}

       if (!in_array($contentGroup, ['tips', 'depression_intro'], true)) {
    $message = 'กรุณาเลือกประเภทเนื้อหาให้ถูกต้อง';
    $messageType = 'error';
} elseif ($title === '') {
            $message = 'กรุณากรอกชื่อบทความ';
            $messageType = 'error';
        } elseif (!validUrl($cover)) {
            $message = 'URL รูปปกไม่ถูกต้อง';
            $messageType = 'error';
        } elseif (count($submittedBlocks) > 200) {
            $message = 'มีส่วนเนื้อหามากเกินไป';
            $messageType = 'error';
        } else {
           
/* รวมข้อความจากบล็อกหัวข้อและข้อความ */
$mainContentParts = [];

foreach ($submittedBlocks as $block) {
    if (!is_array($block)) {
        continue;
    }

    $blockType = $block['block_type'] ?? '';
    $blockContent = trim($block['content'] ?? '');

    if (
        in_array($blockType, ['heading', 'text'], true)
        && $blockContent !== ''
    ) {
        $mainContentParts[] = $blockContent;
    }
}

$mainContent = implode("\n\n", $mainContentParts);
/* ข้อมูลหลักสำหรับตาราง knowledge_tips */
$articleData = [
    'title' => $title,
    'content' => $mainContent !== '' ? $mainContent : $title,
    'image_url' => $cover !== '' ? $cover : null,
    'is_published' => $published,
    'content_group' => $contentGroup
];

            if ($tipId !== null) {
                $r = requestSupabase(
                    'PATCH',
                    'knowledge_tips',
                    'id=eq.' . $tipId,
                    $articleData,
                    'return=representation'
                );
            } else {
                $r = requestSupabase(
                    'POST',
                    'knowledge_tips',
                    '',
                    $articleData,
                    'return=representation'
                );
            }

            $saved = rows($r);
            $actualTipId = $tipId;

            if ($tipId === null && !empty($saved[0]['id'])) {
                $actualTipId = idValue($saved[0]['id']);
            }

            if (!ok($r) || $actualTipId === null) {
                error_log('Save article error: ' . $r['body']);

               $message = 'บันทึกบทความไม่สำเร็จ (HTTP '
    . $r['status'] . '): ' . $r['body'];
            } else {
                /*
                 * โหลดส่วนเนื้อหาเดิมของบทความ
                 * ใช้ knowledge_tip_sections เท่านั้น
                 */
                $oldResult = requestSupabase(
                    'GET',
                    'knowledge_tip_sections',
                    'select=id,knowledge_tip_id,block_type,content,image_url,alt_text,sort_order'
                    . '&knowledge_tip_id=eq.' . $actualTipId
                );

                if (!ok($oldResult)) {
                    error_log('Load sections error: ' . $oldResult['body']);

                    $message = 'บันทึกข้อมูลบทความแล้ว แต่โหลดส่วนเนื้อหาเดิมไม่สำเร็จ';
                    $messageType = 'error';
                } else {
                    $oldBlocks = rows($oldResult);
                    $oldIds = [];

                    foreach ($oldBlocks as $old) {
                        $oldIds[] = (int)$old['id'];
                    }

                    $prepared = [];
                    $invalid = false;

                    /*
                     * ตรวจสอบทุกส่วนก่อนเริ่มบันทึกส่วนเนื้อหา
                     */
                    foreach ($submittedBlocks as $block) {
                        if (!is_array($block)) {
                            $invalid = true;
                            break;
                        }

                        $type = $block['block_type'] ?? 'text';
                        $content = trim($block['content'] ?? '');
                        $image = trim($block['image_url'] ?? '');
                        $blockId = idValue($block['id'] ?? null);

                        if (!in_array($type, ['heading', 'text', 'image'], true)) {
                            $invalid = true;
                            break;
                        }

                        if (!validUrl($image)) {
                            $invalid = true;
                            break;
                        }

                        if ($type === 'heading' && $content === '') {
                            $invalid = true;
                            break;
                        }

                        if ($type === 'text' && $content === '') {
                            $invalid = true;
                            break;
                        }

                        if ($type === 'image' && $image === '') {
                            $invalid = true;
                            break;
                        }

                        /*
                         * ตรวจสอบว่า ID ที่ส่งมาเป็นส่วนของบทความนี้จริง
                         */
                        if (
                            $blockId !== null &&
                            !in_array($blockId, $oldIds, true)
                        ) {
                            $invalid = true;
                            break;
                        }

                        $prepared[] = [
                            'id' => $blockId,
                            'block_type' => $type,
                            'content' => $type === 'image' ? '' : $content,
                            'image_url' => $type === 'image' ? $image : null
                        ];
                    }

                    if ($invalid) {
                        $message = 'ข้อมูลบางส่วนไม่ครบ URL ไม่ถูกต้อง หรือส่วนเนื้อหาไม่ตรงกับบทความ';
                        $messageType = 'error';
                    } else {
                        $failed = false;
                        $keptIds = [];

                        /*
                         * เพิ่มหรือแก้ไขส่วนเนื้อหาตามลำดับ
                         */
                        foreach ($prepared as $position => $block) {
                            $blockData = [
                                'knowledge_tip_id' => $actualTipId,
                                'block_type' => $block['block_type'],
                                'content' => $block['content'],
                                'image_url' => $block['image_url'],
                                'alt_text' => null,
                                'sort_order' => $position
                            ];

                            if ($block['id'] !== null) {
                                $r = requestSupabase(
                                    'PATCH',
                                    'knowledge_tip_sections',
                                    'id=eq.' . $block['id']
                                    . '&knowledge_tip_id=eq.' . $actualTipId,
                                    $blockData,
                                    'return=minimal'
                                );

                                $keptIds[] = $block['id'];
                            } else {
                                $r = requestSupabase(
                                    'POST',
                                    'knowledge_tip_sections',
                                    '',
                                    $blockData,
                                    'return=minimal'
                                );
                            }

                            if (!ok($r)) {
                                error_log('Save section error: ' . $r['body']);
                                $failed = true;
                                break;
                            }
                        }

                        /*
                         * ลบส่วนเดิมที่ไม่มีอยู่ในแบบฟอร์มแล้ว
                         */
                        if (!$failed) {
                            foreach ($oldBlocks as $old) {
                                $oldId = (int)$old['id'];

                                if (!in_array($oldId, $keptIds, true)) {
                                    $r = requestSupabase(
                                        'DELETE',
                                        'knowledge_tip_sections',
                                        'id=eq.' . $oldId
                                        . '&knowledge_tip_id=eq.' . $actualTipId,
                                        null,
                                        'return=minimal'
                                    );

                                    if (!ok($r)) {
                                        error_log('Delete section error: ' . $r['body']);
                                        $failed = true;
                                        break;
                                    }
                                }
                            }
                        }

                        if ($failed) {
                            $message = 'บันทึกเนื้อหาไม่ครบทุกส่วน กรุณาตรวจสอบ Supabase แล้วลองอีกครั้ง';
                            $messageType = 'error';
                        } else {
                            redirectTo('edit=' . $actualTipId . '&result=saved');
                        }
                    }
                }
            }
        }
    }

    /* ---------- ลบบทความ ---------- */
    elseif ($action === 'delete' && $tipId !== null) {
        /*
         * ตาราง knowledge_tip_sections ควรมี Foreign Key
         * ON DELETE CASCADE ไปยัง knowledge_tips.id
         */
        $r = requestSupabase(
            'DELETE',
            'knowledge_tips',
            'id=eq.' . $tipId,
            null,
            'return=minimal'
        );

        if (ok($r)) {
            redirectTo('result=deleted');
        }

        error_log('Delete article error: ' . $r['body']);

        $message = 'ลบบทความไม่สำเร็จ กรุณาตรวจสอบการเชื่อมโยงตาราง';
        $messageType = 'error';
    }

    /* ---------- เปลี่ยนสถานะเผยแพร่ ---------- */
    elseif ($action === 'toggle' && $tipId !== null) {
        $published = ($_POST['published'] ?? '') === '1';

        $r = requestSupabase(
            'PATCH',
            'knowledge_tips',
            'id=eq.' . $tipId,
            ['is_published' => $published],
            'return=minimal'
        );

        if (ok($r)) {
            redirectTo('result=updated');
        }

        error_log('Toggle article error: ' . $r['body']);

        $message = 'เปลี่ยนสถานะไม่สำเร็จ';
        $messageType = 'error';
    }
}


/* =========================
   โหลดรายการบทความตามประเภท
========================= */
$r = requestSupabase(
    'GET',
    'knowledge_tips',
    'select=id,title,content,image_url,is_published,content_group,created_at'
    . '&content_group=eq.' . rawurlencode($pageGroup)
    . '&order=created_at.desc'
);

if (ok($r)) {
    $tips = rows($r);
} else {
    error_log('Load tips error: ' . $r['body']);
    $loadError = 'โหลดรายการบทความไม่สำเร็จ (HTTP ' . $r['status'] . ')';
}


/* =========================
   โหลดบทความสำหรับแก้ไข
========================= */
$editId = idValue($_GET['edit'] ?? null);

if ($editId !== null) {
    $r = requestSupabase(
        'GET',
        'knowledge_tips',
        'select=id,title,content,image_url,is_published,content_group,created_at&id=eq.'
        . $editId . '&limit=1'
    );

    if (ok($r)) {
        $data = rows($r);

        if (!empty($data)) {
            $editTip = $data[0];
        }
    } else {
        error_log('Load edit article error: ' . $r['body']);
        $message = 'โหลดบทความไม่สำเร็จ (HTTP ' . $r['status'] . ')';
        $messageType = 'error';
    }

    if ($editTip !== null) {
        $r = requestSupabase(
            'GET',
            'knowledge_tip_sections',
            'select=id,knowledge_tip_id,block_type,content,image_url,alt_text,sort_order'
            . '&knowledge_tip_id=eq.' . $editId
            . '&order=sort_order.asc,id.asc'
        );

        if (ok($r)) {
            $blocks = rows($r);

            /*
             * ถ้ายังไม่มีส่วนเนื้อหาแยกบล็อก
             * ให้นำเนื้อหาเดิมจาก knowledge_tips.content
             * มาแสดงเป็นช่องข้อความชั่วคราวในแบบฟอร์ม
             *
             * ยังไม่บันทึกหรือย้ายข้อมูลอัตโนมัติ
             */
            if (
                empty($blocks) &&
                trim((string)($editTip['content'] ?? '')) !== ''
            ) {
                $blocks[] = [
                    'id' => '',
                    'knowledge_tip_id' => $editId,
                    'block_type' => 'text',
                    'content' => $editTip['content'],
                    'image_url' => '',
                    'alt_text' => '',
                    'sort_order' => 0
                ];
            }
        } else {
            error_log('Load article sections error: ' . $r['body']);
            $message = 'โหลดส่วนเนื้อหาไม่สำเร็จ (HTTP ' . $r['status'] . ')';
            $messageType = 'error';
        }
    } elseif (!$loadError && !$message) {
        $message = 'ไม่พบบทความที่ต้องการแก้ไข';
        $messageType = 'error';
    }
}

if (isset($_GET['result'])) {
    $messages = [
        'saved' => 'บันทึกบทความและส่วนเนื้อหาเรียบร้อยแล้ว',
        'deleted' => 'ลบบทความเรียบร้อยแล้ว',
        'updated' => 'เปลี่ยนสถานะเผยแพร่เรียบร้อยแล้ว'
    ];

    if (isset($messages[$_GET['result']])) {
        $message = $messages[$_GET['result']];
        $messageType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>
    <?= $pageGroup === 'depression_intro'
        ? 'จัดการความรู้เกี่ยวกับภาวะซึมเศร้า'
        : 'จัดการเกร็ดความรู้' ?> | MindCare
</title>

<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,Tahoma,sans-serif;background:#f0f5fa;color:#17264b}
header{background:#111d43;color:white;padding:20px 5%;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
header h1{margin:0}
header a{color:white;text-decoration:none}
main{max-width:1200px;margin:28px auto;padding:0 16px}
.panel{background:white;padding:22px;border-radius:14px;margin-bottom:22px;box-shadow:0 3px 12px #0000000a}
label{display:block;font-weight:bold;margin:12px 0 6px}
input[type=text],input[type=url],textarea,select{width:100%;padding:11px;border:1px solid #cbd6e5;border-radius:8px;font:inherit}
textarea{min-height:120px;resize:vertical;line-height:1.8}
button,.btn{display:inline-block;border:0;border-radius:8px;padding:10px 14px;background:#234e9b;color:white;cursor:pointer;text-decoration:none;font:inherit}
.secondary{background:#64748b}
.danger{background:#b42318}
.add{background:#16805d}
.success,.error{padding:12px;border-radius:8px;margin-bottom:16px}
.success{background:#e0f7e9;color:#17663a}
.error{background:#fee4e2;color:#9b1c1c}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.small,.meta{color:#64748b;font-size:13px;line-height:1.6}
.columns{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:20px;align-items:start}
.block{border:1px solid #cbd6e5;border-radius:12px;padding:16px;margin:16px 0;background:#fbfdff}
.block-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.block-head h3{margin:0}
.cover{max-width:220px;max-height:150px;object-fit:contain;display:block;margin-top:10px}
.tip{border:1px solid #dce5ef;padding:16px;border-radius:10px;margin-top:14px;overflow-wrap:anywhere}
.check{display:flex;align-items:center;gap:8px}
.check input{width:auto}
form.inline{display:inline}
button:disabled{opacity:.55;cursor:not-allowed}
@media(max-width:850px){.columns{grid-template-columns:1fr}}
</style>
</head>

<body>
<header>
    <div>
        <h1>MindCare ADMIN</h1>
<p>
    <?= $pageGroup === 'depression_intro'
        ? 'จัดการข้อมูลรู้จักภาวะซึมเศร้า'
        : 'จัดการเกร็ดความรู้' ?>
</p>
    </div>

    <nav>
        <a href="admin_dashboard.php">← กลับแดชบอร์ด</a>
        &nbsp; | &nbsp;
        <a href="admin_logout.php">ออกจากระบบ</a>
    </nav>
</header>

<main>

<?php if ($message): ?>
<div class="<?= h($messageType) ?>"><?= h($message) ?></div>
<?php endif; ?>

<?php if ($loadError): ?>
<div class="error"><?= h($loadError) ?></div>
<?php endif; ?>

<div class="columns">

<!-- =========================
     ฟอร์มเพิ่มและแก้ไขบทความ
========================= -->
<section class="panel">
    <h2>
    <?= $editTip
        ? 'แก้ไขบทความ'
        : ($pageGroup === 'depression_intro'
            ? 'เพิ่มข้อมูลรู้จักภาวะซึมเศร้า'
            : 'เพิ่มเกร็ดความรู้') ?>
</h2>

    <p class="small">
        เพิ่มหัวข้อ ข้อความ และรูปภาพเป็นส่วน ๆ
        ใช้ปุ่มขึ้นและลงเพื่อจัดลำดับเนื้อหา
    </p>

    <form method="post" id="article-form">
        <input type="hidden" name="group" value="<?= h($pageGroup) ?>">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tips_csrf']) ?>">
        <input type="hidden" name="action" value="save">

        <?php if ($editTip): ?>
        <input type="hidden" name="id" value="<?= (int)$editTip['id'] ?>">
        <?php endif; ?>
<label for="title">ชื่อเรื่อง</label>
<input
    id="title"
    type="text"
    name="title"
    required
    maxlength="200"
    value="<?= h($editTip['title'] ?? '') ?>"
>

        <label for="image_url">URL รูปปก (ไม่บังคับ)</label>
<input
    id="image_url"
    type="url"
    name="image_url"
    placeholder="https://example.com/cover.jpg"
    value="<?= h($editTip['image_url'] ?? '') ?>"
>

        <?php if (!empty($editTip['image_url'])): ?>
        <img
            class="cover"
            src="<?= h($editTip['image_url']) ?>"
            alt="รูปปก"
            onerror="this.style.display='none'"
        >
        <?php endif; ?>

        <h2>เนื้อหา</h2>

        <div id="blocks">
        <?php foreach ($blocks as $i => $b): ?>
        <div class="block">

            <div class="block-head">
                <h3>ส่วนเนื้อหา</h3>

                <div class="actions">
                    <button type="button" class="secondary move-up">↑ ขึ้น</button>
                    <button type="button" class="secondary move-down">↓ ลง</button>
                    <button type="button" class="danger remove-block">ลบส่วนนี้</button>
                </div>
            </div>

            <input
                type="hidden"
                data-field="id"
                name="blocks[<?= $i ?>][id]"
                value="<?= (int)$b['id'] ?>"
            >

            <label>ประเภทเนื้อหา</label>
            <select
                data-field="block_type"
                name="blocks[<?= $i ?>][block_type]"
                class="block-type"
            >
                <option value="heading" <?= $b['block_type'] === 'heading' ? 'selected' : '' ?>>หัวข้อ</option>
                <option value="text" <?= $b['block_type'] === 'text' ? 'selected' : '' ?>>ข้อความ</option>
                <option value="image" <?= $b['block_type'] === 'image' ? 'selected' : '' ?>>รูปภาพ</option>
            </select>

            <div data-group="title">
                <label>หัวข้อ</label>
                <input
                    type="text"
                    data-field="content"
                    name="blocks[<?= $i ?>][content]"
                    value="<?= $b['block_type'] === 'heading' ? h($b['content'] ?? '') : '' ?>"
                    placeholder="พิมพ์หัวข้อ"
                >
            </div>

            <div data-group="content">
                <label>ข้อความ</label>
                <textarea
                    data-field="content"
                    name="blocks[<?= $i ?>][content]"
                    placeholder="พิมพ์เนื้อหาที่ต้องการ"
                ><?= $b['block_type'] === 'text' ? h($b['content'] ?? '') : '' ?></textarea>
            </div>

            <div data-group="image_url">
                <label>URL รูปภาพ</label>
                <input
                    type="url"
                    data-field="image_url"
                    name="blocks[<?= $i ?>][image_url]"
                    value="<?= h($b['image_url'] ?? '') ?>"
                    placeholder="https://example.com/image.jpg"
                >

                <?php if (!empty($b['image_url'])): ?>
                <img
                    class="cover"
                    src="<?= h($b['image_url']) ?>"
                    alt="ภาพตัวอย่าง"
                    onerror="this.style.display='none'"
                >
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>
        </div>

        <div class="actions">
            <button type="button" class="add" id="add-heading">+ เพิ่มหัวข้อ</button>
            <button type="button" class="add" id="add-text">+ เพิ่มข้อความ</button>
            <button type="button" class="add" id="add-image">+ เพิ่มรูปภาพ</button>
        </div>

        <label class="check">
            <input
                type="checkbox"
                name="is_published"
                value="1"
                <?= !empty($editTip['is_published']) ? 'checked' : '' ?>
            >
            เผยแพร่บนเว็บไซต์
        </label>

        <div class="actions">
            <button type="submit">
                <?= $editTip ? 'บันทึกการแก้ไข' : 'สร้างบทความ' ?>
            </button>

            <?php if ($editTip): ?>
            <a class="btn secondary"
   href="admin_tips.php?group=<?= h($pageGroup) ?>">
    ยกเลิก
</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- =========================
     รายการบทความ
========================= -->
<section class="panel">
    <h2>รายการบทความ</h2>
    <p>ทั้งหมด <?= count($tips) ?> รายการ</p>

    <?php if (empty($tips) && !$loadError): ?>
    <p class="small">ยังไม่มีบทความ</p>
    <?php endif; ?>

    <?php foreach ($tips as $tip): ?>
    <article class="tip">

        <h3><?= h($tip['title'] ?? '') ?></h3>
        <p class="meta">
    ประเภท:
    <?=
        ($tip['content_group'] ?? 'tips') === 'depression_intro'
        ? 'รู้จักภาวะซึมเศร้า'
        : 'เกร็ดความรู้'
    ?>
</p>

        <p class="meta">
            สถานะ:
            <?= !empty($tip['is_published']) ? 'เผยแพร่แล้ว' : 'ซ่อนอยู่' ?>
        </p>

        <?php if (!empty($tip['image_url'])): ?>
        <img
            class="cover"
            src="<?= h($tip['image_url']) ?>"
            alt="รูปปก"
            onerror="this.style.display='none'"
        >
        <?php endif; ?>

        <?php if (!empty($tip['content'])): ?>
        <details>
            <summary>ข้อความเดิมใน content</summary>
            <p><?= h($tip['content']) ?></p>
        </details>
        <?php endif; ?>

        <div class="actions">
            <a class="btn"
   href="admin_tips.php?group=<?= h($pageGroup) ?>&edit=<?= (int)$tip['id'] ?>">
    แก้ไขเนื้อหา
</a>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tips_csrf']) ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$tip['id'] ?>">
                <input
                    type="hidden"
                    name="published"
                    value="<?= !empty($tip['is_published']) ? '0' : '1' ?>"
                >

                <button class="secondary" type="submit">
                    <?= !empty($tip['is_published']) ? 'ซ่อน' : 'เผยแพร่' ?>
                </button>
            </form>

            <form
                method="post"
                onsubmit="return confirm('ยืนยันลบบทความนี้หรือไม่?')"
            >
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tips_csrf']) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$tip['id'] ?>">

                <button class="danger" type="submit">ลบ</button>
            </form>
        </div>
    </article>
    <?php endforeach; ?>
</section>

</div>
</main>


<script>
const container = document.getElementById('blocks');

/* จัดลำดับชื่อฟิลด์ใหม่ทุกครั้ง */
function renumberBlocks() {
    const allBlocks = container.querySelectorAll('.block');

    allBlocks.forEach((block, index) => {
        block.querySelectorAll('[data-field]').forEach(field => {
            field.name = `blocks[${index}][${field.dataset.field}]`;
        });
    });
}

/* แสดงเฉพาะช่องที่ตรงกับประเภทเนื้อหา */
function updateBlock(block) {
    const type = block.querySelector('.block-type').value;

    const groups = {
        heading: block.querySelector('[data-group="title"]'),
        text: block.querySelector('[data-group="content"]'),
        image: block.querySelector('[data-group="image_url"]')
    };

    Object.entries(groups).forEach(([groupType, group]) => {
        const active = type === groupType;

        group.hidden = !active;

        group.querySelectorAll('input, textarea, select').forEach(field => {
            field.disabled = !active;
        });
    });
}

/* สร้างส่วนเนื้อหา */
function makeBlock(type) {
    const block = document.createElement('div');
    block.className = 'block';

    block.innerHTML = `
        <div class="block-head">
            <h3>ส่วนเนื้อหาใหม่</h3>
            <div class="actions">
                <button type="button" class="secondary move-up">↑ ขึ้น</button>
                <button type="button" class="secondary move-down">↓ ลง</button>
                <button type="button" class="danger remove-block">ลบส่วนนี้</button>
            </div>
        </div>

        <input type="hidden" data-field="id" value="">

        <label>ประเภทเนื้อหา</label>
        <select data-field="block_type" class="block-type">
            <option value="heading">หัวข้อ</option>
            <option value="text">ข้อความ</option>
            <option value="image">รูปภาพ</option>
        </select>

        <div data-group="title">
            <label>หัวข้อ</label>
            <input type="text" data-field="content"
                   placeholder="พิมพ์หัวข้อ">
        </div>

        <div data-group="content">
            <label>ข้อความ</label>
            <textarea data-field="content"
                      placeholder="พิมพ์เนื้อหาที่ต้องการ"></textarea>
        </div>

        <div data-group="image_url">
            <label>URL รูปภาพ</label>
            <input type="url" data-field="image_url"
                   placeholder="https://example.com/image.jpg">
            <img class="cover" alt="ภาพตัวอย่าง" hidden>
        </div>
    `;

    block.querySelector('.block-type').value = type;
    container.appendChild(block);

    updateBlock(block);
    renumberBlocks();

    block.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
    });
}

/* ปุ่มเพิ่มหัวข้อ ข้อความ และรูปภาพ */
document.getElementById('add-heading').addEventListener('click', () => {
    makeBlock('heading');
});

document.getElementById('add-text').addEventListener('click', () => {
    makeBlock('text');
});

document.getElementById('add-image').addEventListener('click', () => {
    makeBlock('image');
});

/* เมื่อเปลี่ยนประเภทเนื้อหา */
container.addEventListener('change', event => {
    if (event.target.matches('.block-type')) {
        updateBlock(event.target.closest('.block'));
        renumberBlocks();
    }
});

/* แสดงตัวอย่างรูปภาพ */
container.addEventListener('input', event => {
    if (event.target.dataset.field !== 'image_url') return;

    const block = event.target.closest('.block');
    const preview = block.querySelector('img.cover');
    const url = event.target.value.trim();

    if (!url) {
        preview.removeAttribute('src');
        preview.hidden = true;
        return;
    }

    preview.onerror = () => {
        preview.hidden = true;
    };

    preview.onload = () => {
        preview.hidden = false;
    };

    preview.src = url;
});

/* ปุ่มลบและเลื่อนลำดับ */
container.addEventListener('click', event => {
    const button = event.target.closest('button');

    if (!button) return;

    const block = button.closest('.block');

    if (!block || !container.contains(block)) return;

    if (button.classList.contains('remove-block')) {
        if (confirm('ต้องการลบส่วนเนื้อหานี้หรือไม่?')) {
            block.remove();
            renumberBlocks();
        }
    }

    if (button.classList.contains('move-up')) {
        const previous = block.previousElementSibling;

        if (previous) {
            container.insertBefore(block, previous);
            renumberBlocks();
        }
    }

    if (button.classList.contains('move-down')) {
        const next = block.nextElementSibling;

        if (next) {
            container.insertBefore(next, block);
            renumberBlocks();
        }
    }
});

/* ตั้งค่าเริ่มต้นให้บล็อกที่โหลดจากฐานข้อมูล */
container.querySelectorAll('.block').forEach(block => {
    updateBlock(block);
});

renumberBlocks();
</script>


</body>
</html>