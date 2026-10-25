
<?php
session_start();

require_once __DIR__ . '/supabase_config.php';

/*
|--------------------------------------------------------------------------
| ตรวจสอบสิทธิ์แอดมิน
|--------------------------------------------------------------------------
*/
if (
    empty($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    empty($_SESSION['admin_email']) ||
    $_SESSION['admin_email'] !== ADMIN_EMAIL
) {
    header('Location: admin_login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| ฟังก์ชันพื้นฐาน
|--------------------------------------------------------------------------
*/
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function apiRequest(
    string $method,
    string $resource,
    ?array $body = null,
    array $extraHeaders = []
): array {
    $allowedResources = ['knowledge_tips', 'knowledge_tip_blocks'];

    $resourceName = explode('?', $resource, 2)[0];

    if (!in_array($resourceName, $allowedResources, true)) {
        return [
            'ok' => false,
            'status' => 400,
            'data' => null,
            'error' => 'ไม่อนุญาตให้เรียกตารางนี้'
        ];
    }

    if (
        !defined('SUPABASE_URL') ||
        !defined('SUPABASE_SECRET_KEY')
    ) {
        return [
            'ok' => false,
            'status' => 500,
            'data' => null,
            'error' => 'ไม่พบการตั้งค่า Supabase'
        ];
    }

    $url = rtrim(SUPABASE_URL, '/') .
        '/rest/v1/' . $resource;

    $headers = array_merge([
        'apikey: ' . SUPABASE_SECRET_KEY,
        'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
        'Content-Type: application/json',
    ], $extraHeaders);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);

    if ($body !== null) {
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($body, JSON_UNESCAPED_UNICODE)
        );
    }

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'error' => $curlError ?: 'เชื่อมต่อ Supabase ไม่สำเร็จ'
        ];
    }

    $decoded = null;

    if ($response !== '') {
        $decoded = json_decode($response, true);
    }

    $ok = $status >= 200 && $status < 300;

    $error = null;

    if (!$ok) {
        if (is_array($decoded)) {
            $error = $decoded['message']
                ?? $decoded['details']
                ?? $decoded['hint']
                ?? null;
        }

        if (!$error) {
            $error = 'Supabase ตอบกลับ HTTP ' . $status;
        }
    }

    return [
        'ok' => $ok,
        'status' => $status,
        'data' => $decoded,
        'error' => $error
    ];
}

function loadBlocks(int $tipId): array
{
    $resource = 'knowledge_tip_blocks'
        . '?select=id,block_type,content,image_url,alt_text,sort_order'
        . '&knowledge_tip_id=eq.' . $tipId
        . '&order=sort_order.asc,id.asc';

    $result = apiRequest('GET', $resource);

    if (!$result['ok'] || !is_array($result['data'])) {
        return [
            'ok' => false,
            'data' => [],
            'error' => $result['error'] ?? 'โหลดเนื้อหาไม่สำเร็จ'
        ];
    }

    return [
        'ok' => true,
        'data' => $result['data'],
        'error' => null
    ];
}

function deleteBlocksByIds(int $tipId, array $ids): array
{
    $ids = array_values(array_filter(
        array_map('intval', $ids),
        fn($id) => $id > 0
    ));

    if (!$ids) {
        return [
            'ok' => true,
            'error' => null
        ];
    }

    $resource = 'knowledge_tip_blocks'
        . '?knowledge_tip_id=eq.' . $tipId
        . '&id=in.(' . implode(',', $ids) . ')';

    $result = apiRequest('DELETE', $resource);

    return [
        'ok' => $result['ok'],
        'error' => $result['error']
    ];
}

/*
|--------------------------------------------------------------------------
| เตรียม CSRF token
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['tips_csrf'])) {
    $_SESSION['tips_csrf'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| รับ ID ของเกร็ดความรู้
|--------------------------------------------------------------------------
*/
$tipId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$tipId || $tipId < 1) {
    http_response_code(400);
    exit('ไม่พบ ID ของเกร็ดความรู้ กรุณากลับไปเลือกเกร็ดความรู้ก่อน');
}

/*
|--------------------------------------------------------------------------
| โหลดเกร็ดความรู้
|--------------------------------------------------------------------------
*/
$tipResult = apiRequest(
    'GET',
    'knowledge_tips?select=id,title,content,image_url'
        . '&id=eq.' . (int)$tipId
        . '&limit=1'
);

if (
    !$tipResult['ok'] ||
    !is_array($tipResult['data']) ||
    empty($tipResult['data'][0])
) {
    http_response_code(404);
    exit(
        'ไม่พบเกร็ดความรู้รายการนี้ '
        . h($tipResult['error'] ?? '')
    );
}

$tip = $tipResult['data'][0];

/*
|--------------------------------------------------------------------------
| ข้อความแจ้งผล
|--------------------------------------------------------------------------
*/
$message = '';
$messageType = 'success';

/*
|--------------------------------------------------------------------------
| บันทึกข้อมูล
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['tips_csrf'] ?? '');

    if (
        $postedToken === '' ||
        $sessionToken === '' ||
        !hash_equals($sessionToken, $postedToken)
    ) {
        $message = 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่';
        $messageType = 'error';
    } else {
        $newTitle = trim((string)($_POST['tip_title'] ?? ''));
        $rawBlocks = $_POST['blocks'] ?? [];

        if ($newTitle === '') {
            $message = 'กรุณากรอกชื่อเกร็ดความรู้';
            $messageType = 'error';
        } elseif (mb_strlen($newTitle) > 250) {
            $message = 'ชื่อเกร็ดความรู้ยาวเกินไป';
            $messageType = 'error';
        } elseif (!is_array($rawBlocks)) {
            $message = 'รูปแบบข้อมูลเนื้อหาไม่ถูกต้อง';
            $messageType = 'error';
        } elseif (count($rawBlocks) > 100) {
            $message = 'เพิ่มเนื้อหาได้ไม่เกิน 100 บล็อกต่อครั้ง';
            $messageType = 'error';
        } else {
            /*
             * ตรวจสอบและจัดรูปแบบบล็อกทั้งหมดก่อนบันทึก
             */
            $newBlocks = [];
            $validationError = '';

            foreach (array_values($rawBlocks) as $index => $block) {
                if (!is_array($block)) {
                    $validationError = 'รูปแบบบล็อกเนื้อหาไม่ถูกต้อง';
                    break;
                }

                $type = (string)($block['block_type'] ?? '');
                $content = trim((string)($block['content'] ?? ''));
                $imageUrl = trim((string)($block['image_url'] ?? ''));
                $altText = trim((string)($block['alt_text'] ?? ''));

                if (!in_array($type, ['heading', 'text', 'image'], true)) {
                    $validationError = 'พบประเภทเนื้อหาที่ไม่รองรับ';
                    break;
                }

                if ($type === 'heading') {
                    if ($content === '') {
                        $validationError =
                            'กรุณากรอกหัวข้อย่อยในบล็อกที่ ' . ($index + 1);
                        break;
                    }

                    if (mb_strlen($content) > 500) {
                        $validationError =
                            'หัวข้อย่อยในบล็อกที่ ' . ($index + 1)
                            . ' ยาวเกินไป';
                        break;
                    }

                    $newBlocks[] = [
                        'knowledge_tip_id' => (int)$tipId,
                        'block_type' => 'heading',
                        'content' => $content,
                        'image_url' => '',
                        'alt_text' => '',
                        'sort_order' => $index + 1,
                    ];
                } elseif ($type === 'text') {
                    if ($content === '') {
                        $validationError =
                            'กรุณากรอกข้อความในบล็อกที่ ' . ($index + 1);
                        break;
                    }

                    $newBlocks[] = [
                        'knowledge_tip_id' => (int)$tipId,
                        'block_type' => 'text',
                        'content' => $content,
                        'image_url' => '',
                        'alt_text' => '',
                        'sort_order' => $index + 1,
                    ];
                } else {
                    if ($imageUrl === '') {
                        $validationError =
                            'กรุณากรอก URL รูปภาพในบล็อกที่ ' . ($index + 1);
                        break;
                    }

                    if (
                        !filter_var($imageUrl, FILTER_VALIDATE_URL) ||
                        !in_array(
                            strtolower((string)parse_url($imageUrl, PHP_URL_SCHEME)),
                            ['http', 'https'],
                            true
                        )
                    ) {
                        $validationError =
                            'URL รูปภาพในบล็อกที่ ' . ($index + 1)
                            . ' ไม่ถูกต้อง';
                        break;
                    }

                    $newBlocks[] = [
                        'knowledge_tip_id' => (int)$tipId,
                        'block_type' => 'image',
                        'content' => $content,
                        'image_url' => $imageUrl,
                        'alt_text' => $altText,
                        'sort_order' => $index + 1,
                    ];
                }
            }

            if ($validationError !== '') {
                $message = $validationError;
                $messageType = 'error';
            } else {
                /*
                 * โหลดบล็อกเก่าไว้ก่อน เพื่อไม่ให้ลบข้อมูลเดิม
                 * หากไม่สามารถโหลดข้อมูลเก่าได้
                 */
                $oldResult = loadBlocks((int)$tipId);

                if (!$oldResult['ok']) {
                    $message = 'ไม่สามารถตรวจสอบเนื้อหาเดิมได้: '
                        . $oldResult['error'];
                    $messageType = 'error';
                } else {
                    $oldIds = [];

                    foreach ($oldResult['data'] as $oldBlock) {
                        if (isset($oldBlock['id'])) {
                            $oldIds[] = (int)$oldBlock['id'];
                        }
                    }

                    /*
                     * ขั้นที่ 1: เพิ่มบล็อกชุดใหม่ก่อน
                     * ข้อมูลเก่ายังอยู่ หากการเพิ่มชุดใหม่ล้มเหลว
                     */
                    $newIds = [];
                    $insertOk = true;
                    $insertError = '';

                    if (!empty($newBlocks)) {
                        $insertResult = apiRequest(
                            'POST',
                            'knowledge_tip_blocks',
                            $newBlocks,
                            ['Prefer: return=representation']
                        );

                        if (
                            !$insertResult['ok'] ||
                            !is_array($insertResult['data'])
                        ) {
                            $insertOk = false;
                            $insertError = $insertResult['error']
                                ?? 'เพิ่มเนื้อหาใหม่ไม่สำเร็จ';
                        } else {
                            foreach ($insertResult['data'] as $inserted) {
                                if (isset($inserted['id'])) {
                                    $newIds[] = (int)$inserted['id'];
                                }
                            }

                            if (count($newIds) !== count($newBlocks)) {
                                $insertOk = false;
                                $insertError =
                                    'ไม่สามารถยืนยันรายการเนื้อหาที่บันทึกได้';
                            }
                        }
                    }

                    if (!$insertOk) {
                        /*
                         * ลบชุดใหม่ที่เพิ่งเพิ่ม เฉพาะกรณีที่ทราบ ID
                         * เป็นการพยายามคืนสภาพข้อมูลเดิม
                         */
                        deleteBlocksByIds((int)$tipId, $newIds);

                        $message = 'บันทึกเนื้อหาไม่สำเร็จ: ' . $insertError;
                        $messageType = 'error';
                    } else {
                        /*
                         * ขั้นที่ 2: ลบเฉพาะบล็อกเดิมของบทความนี้
                         */
                        $deleteResult = deleteBlocksByIds(
                            (int)$tipId,
                            $oldIds
                        );

                        if (!$deleteResult['ok']) {
                            // พยายามลบชุดใหม่ที่เพิ่งเพิ่ม หากลบชุดเก่าไม่ได้
                            deleteBlocksByIds((int)$tipId, $newIds);

                            $message = 'เพิ่มเนื้อหาใหม่แล้ว แต่ลบชุดเดิมไม่สำเร็จ '
                                . 'กรุณาตรวจสอบข้อมูลใน Supabase: '
                                . $deleteResult['error'];
                            $messageType = 'error';
                        } else {
                            /*
                             * ขั้นที่ 3: อัปเดตชื่อบทความ
                             */
                            $titleResult = apiRequest(
                                'PATCH',
                                'knowledge_tips?id=eq.' . (int)$tipId,
                                ['title' => $newTitle],
                                ['Prefer: return=minimal']
                            );

                            if (!$titleResult['ok']) {
                                $message = 'บันทึกเนื้อหาแล้ว แต่เปลี่ยนชื่อเกร็ดความรู้ไม่สำเร็จ: '
                                    . $titleResult['error'];
                                $messageType = 'error';
                            } else {
                                $tip['title'] = $newTitle;

                                $message = 'บันทึกชื่อและเนื้อหาเกร็ดความรู้เรียบร้อยแล้ว';
                                $messageType = 'success';
                            }
                        }
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| โหลดบล็อกสำหรับแสดงในหน้าแก้ไข
|--------------------------------------------------------------------------
*/
$blocksResult = loadBlocks((int)$tipId);
$blocks = $blocksResult['data'];

if (!$blocksResult['ok'] && $message === '') {
    $message = 'โหลดเนื้อหาไม่ได้: ' . $blocksResult['error'];
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>แก้ไขเกร็ดความรู้ | MindCare</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Tahoma, Arial, sans-serif;
            background: #f3f7fb;
            color: #17264b;
            line-height: 1.7;
        }

        .topbar {
            background: #111d43;
            color: #fff;
            padding: 14px 22px;
        }

        .topbar-inner {
            max-width: 1100px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .brand {
            font-size: 21px;
            font-weight: bold;
        }

        .topbar a {
            color: #fff;
            text-decoration: none;
        }

        .container {
            max-width: 1000px;
            margin: 28px auto;
            padding: 0 16px 40px;
        }

        .panel {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 22px;
            box-shadow: 0 5px 22px rgba(23, 59, 101, 0.07);
        }

        h1 {
            margin-top: 0;
            font-size: 27px;
        }

        h2 {
            font-size: 21px;
            margin-top: 0;
        }

        .muted {
            color: #63718b;
            font-size: 14px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input[type="text"],
        textarea,
        select {
            width: 100%;
            border: 1px solid #cad5e3;
            border-radius: 9px;
            padding: 11px 12px;
            font: inherit;
            color: #17264b;
            background: #fff;
        }

        textarea {
            min-height: 130px;
            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: 2px solid #b8dcd9;
            border-color: #318b88;
        }

        .field {
            margin-bottom: 16px;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0 22px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            border-radius: 9px;
            padding: 10px 16px;
            font: inherit;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        .primary {
            background: #173b65;
            color: #fff;
        }

        .secondary {
            background: #e8f1f8;
            color: #173b65;
        }

        .danger {
            background: #fff0f0;
            color: #a12626;
        }

        .btn:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .block {
            border: 1px solid #d9e3ee;
            border-radius: 13px;
            padding: 18px;
            margin-bottom: 16px;
            background: #fbfdff;
        }

        .block-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .block-title {
            font-weight: bold;
            font-size: 17px;
        }

        .block-actions {
            display: flex;
            gap: 7px;
        }

        .block-actions .btn {
            padding: 6px 10px;
            font-size: 14px;
        }

        .image-preview {
            display: block;
            max-width: 100%;
            max-height: 260px;
            margin-top: 12px;
            border-radius: 10px;
            object-fit: contain;
            background: #edf2f7;
        }

        .notice {
            border-radius: 10px;
            padding: 13px 16px;
            margin-bottom: 18px;
        }

        .notice.success {
            background: #e9f7ef;
            color: #17643a;
        }

        .notice.error {
            background: #fff0f0;
            color: #9b2525;
        }

        .empty {
            text-align: center;
            padding: 24px;
            border: 1px dashed #b9c8d9;
            border-radius: 12px;
            color: #63718b;
        }

        .save-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .save-note {
            color: #63718b;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            .panel {
                padding: 17px;
            }

            h1 {
                font-size: 23px;
            }

            .save-bar .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">MindCare | ระบบจัดการเนื้อหา</div>
        <a href="admin_tips.php">← กลับไปหน้าเกร็ดความรู้</a>
    </div>
</header>

<main class="container">

    <section class="panel">
        <h1>แก้ไขเกร็ดความรู้</h1>

        <p class="muted">
            แก้ไขชื่อ เพิ่มหัวข้อย่อย ข้อความ หรือรูปภาพ
            แล้วกดบันทึกเพื่อจัดเก็บข้อมูล
        </p>

        <?php if ($message !== ''): ?>
            <div class="notice <?= h($messageType) ?>">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="editor-form">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($_SESSION['tips_csrf']) ?>"
            >

            <div class="field">
                <label for="tip-title">ชื่อเกร็ดความรู้</label>
                <input
                    type="text"
                    id="tip-title"
                    name="tip_title"
                    value="<?= h($tip['title'] ?? '') ?>"
                    maxlength="250"
                    required
                    placeholder="กรอกชื่อเกร็ดความรู้"
                >
            </div>

            <h2>เนื้อหาเกร็ดความรู้</h2>

            <p class="muted">
                ใช้ปุ่มเพิ่มเนื้อหาเพื่อสร้างบล็อก
                สามารถเลื่อนขึ้นหรือลงเพื่อเปลี่ยนลำดับได้
            </p>

            <div class="toolbar">
                <button type="button" class="btn secondary" id="add-heading">
                    + เพิ่มหัวข้อย่อย
                </button>

                <button type="button" class="btn secondary" id="add-text">
                    + เพิ่มข้อความ
                </button>

                <button type="button" class="btn secondary" id="add-image">
                    + เพิ่มรูปภาพ
                </button>
            </div>

            <div id="blocks">
                <?php foreach ($blocks as $index => $block): ?>
                    <div class="block" data-type="<?= h($block['block_type']) ?>">
                        <div class="block-header">
                            <div class="block-title">
                                <?php
                                if ($block['block_type'] === 'heading') {
                                    echo 'หัวข้อย่อย';
                                } elseif ($block['block_type'] === 'image') {
                                    echo 'รูปภาพ';
                                } else {
                                    echo 'ข้อความ';
                                }
                                ?>
                            </div>

                            <div class="block-actions">
                                <button
                                    type="button"
                                    class="btn secondary move-up"
                                    aria-label="เลื่อนขึ้น"
                                    title="เลื่อนขึ้น"
                                >↑</button>

                                <button
                                    type="button"
                                    class="btn secondary move-down"
                                    aria-label="เลื่อนลง"
                                    title="เลื่อนลง"
                                >↓</button>

                                <button
                                    type="button"
                                    class="btn danger remove-block"
                                >ลบ</button>
                            </div>
                        </div>

                        <?php if ($block['block_type'] === 'heading'): ?>
                            <div class="field">
                                <label>หัวข้อย่อย</label>
                                <input
                                    type="text"
                                    class="block-content"
                                    value="<?= h($block['content'] ?? '') ?>"
                                    maxlength="500"
                                    placeholder="เช่น อาการที่ควรสังเกต"
                                    required
                                >
                            </div>

                        <?php elseif ($block['block_type'] === 'image'): ?>
                            <div class="field">
                                <label>URL รูปภาพ</label>
                                <input
                                    type="text"
                                    class="block-image-url"
                                    value="<?= h($block['image_url'] ?? '') ?>"
                                    placeholder="https://example.com/image.jpg"
                                    required
                                >
                            </div>

                            <div class="field">
                                <label>คำอธิบายรูปภาพ (Alt text)</label>
                                <input
                                    type="text"
                                    class="block-alt-text"
                                    value="<?= h($block['alt_text'] ?? '') ?>"
                                    placeholder="อธิบายรูปภาพสั้น ๆ"
                                >
                            </div>

                            <div class="field">
                                <label>คำบรรยายใต้ภาพ (ถ้ามี)</label>
                                <textarea
                                    class="block-content"
                                    placeholder="กรอกคำบรรยายใต้ภาพ"
                                ><?= h($block['content'] ?? '') ?></textarea>
                            </div>

                            <img
                                class="image-preview"
                                src="<?= h($block['image_url'] ?? '') ?>"
                                alt="<?= h($block['alt_text'] ?? '') ?>"
                                loading="lazy"
                                onerror="this.style.display='none'"
                            >

                        <?php else: ?>
                            <div class="field">
                                <label>ข้อความ</label>
                                <textarea
                                    class="block-content"
                                    placeholder="พิมพ์เนื้อหาที่ต้องการแสดง"
                                    required
                                ><?= h($block['content'] ?? '') ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="empty-state" class="empty" <?= !empty($blocks) ? 'hidden' : '' ?>>
                ยังไม่มีเนื้อหา กดปุ่มด้านบนเพื่อเพิ่มหัวข้อ ข้อความ หรือรูปภาพ
            </div>

            <div class="save-bar">
                <span class="save-note">
                    การบันทึกจะแทนที่ชุดบล็อกเดิมของเกร็ดความรู้นี้
                </span>

                <button type="submit" class="btn primary" id="save-button">
                    บันทึกข้อมูล
                </button>
            </div>
        </form>
    </section>

</main>

<script>
(function () {
    const blocksContainer = document.getElementById('blocks');
    const emptyState = document.getElementById('empty-state');
    const form = document.getElementById('editor-form');
    const saveButton = document.getElementById('save-button');

    function updateEmptyState() {
        emptyState.hidden = blocksContainer.children.length > 0;
    }

    function createField(labelText, className, tagName, placeholder) {
        const field = document.createElement('div');
        field.className = 'field';

        const label = document.createElement('label');
        label.textContent = labelText;

        const input = document.createElement(tagName);
        input.className = className;
        input.placeholder = placeholder;

        field.appendChild(label);
        field.appendChild(input);

        return field;
    }

    function createBlock(type) {
        const block = document.createElement('div');
        block.className = 'block';
        block.dataset.type = type;

        const header = document.createElement('div');
        header.className = 'block-header';

        const title = document.createElement('div');
        title.className = 'block-title';

        if (type === 'heading') {
            title.textContent = 'หัวข้อย่อย';
        } else if (type === 'image') {
            title.textContent = 'รูปภาพ';
        } else {
            title.textContent = 'ข้อความ';
        }

        const actions = document.createElement('div');
        actions.className = 'block-actions';

        const up = document.createElement('button');
        up.type = 'button';
        up.className = 'btn secondary move-up';
        up.textContent = '↑';
        up.title = 'เลื่อนขึ้น';
        up.setAttribute('aria-label', 'เลื่อนขึ้น');

        const down = document.createElement('button');
        down.type = 'button';
        down.className = 'btn secondary move-down';
        down.textContent = '↓';
        down.title = 'เลื่อนลง';
        down.setAttribute('aria-label', 'เลื่อนลง');

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn danger remove-block';
        remove.textContent = 'ลบ';

        actions.append(up, down, remove);
        header.append(title, actions);
        block.appendChild(header);

        if (type === 'heading') {
            const input = createField(
                'หัวข้อย่อย',
                'block-content',
                'input',
                'เช่น อาการที่ควรสังเกต'
            );
            input.querySelector('input').type = 'text';
            input.querySelector('input').maxLength = 500;
            input.querySelector('input').required = true;
            block.appendChild(input);

        } else if (type === 'text') {
            const textarea = createField(
                'ข้อความ',
                'block-content',
                'textarea',
                'พิมพ์เนื้อหาที่ต้องการแสดง'
            );
            textarea.querySelector('textarea').required = true;
            block.appendChild(textarea);

        } else if (type === 'image') {
            const urlField = createField(
                'URL รูปภาพ',
                'block-image-url',
                'input',
                'https://example.com/image.jpg'
            );
            const urlInput = urlField.querySelector('input');
            urlInput.type = 'text';
            urlInput.required = true;

            const altField = createField(
                'คำอธิบายรูปภาพ (Alt text)',
                'block-alt-text',
                'input',
                'อธิบายรูปภาพสั้น ๆ'
            );
            altField.querySelector('input').type = 'text';

            const captionField = createField(
                'คำบรรยายใต้ภาพ (ถ้ามี)',
                'block-content',
                'textarea',
                'กรอกคำบรรยายใต้ภาพ'
            );

            const preview = document.createElement('img');
            preview.className = 'image-preview';
            preview.alt = '';
            preview.style.display = 'none';

            urlInput.addEventListener('input', function () {
                const value = urlInput.value.trim();

                if (value) {
                    preview.src = value;
                    preview.style.display = 'block';
                } else {
                    preview.removeAttribute('src');
                    preview.style.display = 'none';
                }
            });

            preview.addEventListener('error', function () {
                preview.style.display = 'none';
            });

            preview.addEventListener('load', function () {
                preview.style.display = 'block';
            });

            block.append(urlField, altField, captionField, preview);
        }

        blocksContainer.appendChild(block);
        updateEmptyState();

        block.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }

    document.getElementById('add-heading').addEventListener('click', function () {
        createBlock('heading');
    });

    document.getElementById('add-text').addEventListener('click', function () {
        createBlock('text');
    });

    document.getElementById('add-image').addEventListener('click', function () {
        createBlock('image');
    });

    blocksContainer.addEventListener('click', function (event) {
        const button = event.target.closest('button');

        if (!button) {
            return;
        }

        const block = button.closest('.block');

        if (!block) {
            return;
        }

        if (button.classList.contains('remove-block')) {
            if (confirm('ต้องการลบบล็อกนี้ใช่หรือไม่?')) {
                block.remove();
                updateEmptyState();
            }
        }

        if (button.classList.contains('move-up')) {
            const previous = block.previousElementSibling;

            if (previous) {
                blocksContainer.insertBefore(block, previous);
            }
        }

        if (button.classList.contains('move-down')) {
            const next = block.nextElementSibling;

            if (next) {
                blocksContainer.insertBefore(next, block);
            }
        }
    });

    form.addEventListener('submit', function (event) {
        const title = document.getElementById('tip-title').value.trim();

        if (!title) {
            event.preventDefault();
            alert('กรุณากรอกชื่อเกร็ดความรู้');
            document.getElementById('tip-title').focus();
            return;
        }

        const blocks = blocksContainer.querySelectorAll('.block');

        for (const block of blocks) {
            const type = block.dataset.type;

            if (type === 'heading' || type === 'text') {
                const content = block.querySelector('.block-content');

                if (!content || !content.value.trim()) {
                    event.preventDefault();
                    alert(
                        type === 'heading'
                            ? 'กรุณากรอกหัวข้อย่อยให้ครบ'
                            : 'กรุณากรอกข้อความให้ครบ'
                    );

                    if (content) {
                        content.focus();
                    }
                    return;
                }
            }

            if (type === 'image') {
                const urlInput = block.querySelector('.block-image-url');

                if (!urlInput || !urlInput.value.trim()) {
                    event.preventDefault();
                    alert('กรุณากรอก URL รูปภาพ');
                    if (urlInput) {
                        urlInput.focus();
                    }
                    return;
                }
            }
        }

        if (!confirm('ยืนยันบันทึกชื่อและเนื้อหาเกร็ดความรู้ใช่หรือไม่?')) {
            event.preventDefault();
            return;
        }

        saveButton.disabled = true;
        saveButton.textContent = 'กำลังบันทึก...';

        /*
         * สร้าง hidden inputs จากบล็อกบนหน้าจอ
         * เพื่อส่งข้อมูลตามลำดับปัจจุบันไปยัง PHP
         */
        blocksContainer.querySelectorAll('.generated-input').forEach(function (input) {
            input.remove();
        });

        blocks.forEach(function (block, index) {
            const type = block.dataset.type;

            function addHidden(name, value) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.className = 'generated-input';
                input.name = 'blocks[' + index + '][' + name + ']';
                input.value = value;
                form.appendChild(input);
            }

            addHidden('block_type', type);

            if (type === 'heading' || type === 'text') {
                const content = block.querySelector('.block-content');
                addHidden('content', content ? content.value : '');
                addHidden('image_url', '');
                addHidden('alt_text', '');

            } else if (type === 'image') {
                const url = block.querySelector('.block-image-url');
                const alt = block.querySelector('.block-alt-text');
                const caption = block.querySelector('.block-content');

                addHidden('content', caption ? caption.value : '');
                addHidden('image_url', url ? url.value : '');
                addHidden('alt_text', alt ? alt.value : '');
            }
        });
    });

    updateEmptyState();
})();
</script>

</body>
</html>
