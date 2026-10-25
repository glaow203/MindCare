
<?php
/**
 * MindCare Assessment Library
 * ไฟล์กลางสำหรับเชื่อมต่อ Supabase REST API
 */

require_once __DIR__ . '/supabase_config.php';

/**
 * แปลงข้อความให้ปลอดภัยก่อนแสดงใน HTML
 */
if (!function_exists('mc_h')) {
    function mc_h($value): string
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/**
 * แปลงชื่อเรียกตารางให้ตรงกับตารางจริงใน Supabase
 */
if (!function_exists('mc_table_name')) {
    function mc_table_name(string $table): string
    {
        $tables = [
            'mc_assessments' =>
                'assessments',

            'mc_assessment_questions' =>
                'assessment_questions',

            'mc_assessment_options' =>
                'assessment_options',

            'mc_assessment_bands' =>
                'assessment_scoring_rules',
        ];

        // อนุญาตเฉพาะชื่อตารางที่กำหนดไว้
        if (!isset($tables[$table])) {
            throw new InvalidArgumentException(
                'ไม่อนุญาตให้เข้าถึงตาราง: ' . $table
            );
        }

        return $tables[$table];
    }
}

/**
 * ส่งคำขอไปยัง Supabase REST API
 *
 * รูปแบบ:
 * mc_query('mc_assessments', 'select=*&limit=10');
 * mc_query('mc_assessments', 'id=eq.1', 'PATCH', ['title' => '...']);
 *
 * ผลลัพธ์:
 * [
 *   'ok'     => true/false,
 *   'data'   => [],
 *   'status' => HTTP status code,
 *   'error'  => ข้อความผิดพลาด
 * ]
 */
if (!function_exists('mc_query')) {
    function mc_query(
        string $table,
        string $query = '',
        string $method = 'GET',
        ?array $payload = null
    ): array {
        $baseUrl = rtrim(
            (string)(defined('SUPABASE_URL')
                ? SUPABASE_URL
                : ''),
            '/'
        );

        // ใช้ Secret Key ฝั่งเซิร์ฟเวอร์ก่อน
        $apiKey = defined('SUPABASE_SECRET_KEY')
            ? trim((string)SUPABASE_SECRET_KEY)
            : '';

        // ใช้ Publishable Key สำรองสำหรับการเชื่อมต่อ
        if ($apiKey === '') {
            $apiKey = defined('SUPABASE_PUBLISHABLE_KEY')
                ? trim((string)SUPABASE_PUBLISHABLE_KEY)
                : '';
        }

        if (
            $baseUrl === '' ||
            $apiKey === ''
        ) {
            return [
                'ok' => false,
                'data' => [],
                'status' => 0,
                'error' =>
                    'กรุณาตรวจสอบ SUPABASE_URL และ API Key ใน supabase_config.php',
            ];
        }

        try {
            $actualTable = mc_table_name($table);
        } catch (InvalidArgumentException $e) {
            return [
                'ok' => false,
                'data' => [],
                'status' => 0,
                'error' => $e->getMessage(),
            ];
        }

        $method = strtoupper($method);

        $allowedMethods = [
            'GET',
            'POST',
            'PATCH',
            'DELETE',
        ];

        if (!in_array($method, $allowedMethods, true)) {
            return [
                'ok' => false,
                'data' => [],
                'status' => 0,
                'error' => 'ไม่รองรับ HTTP method นี้',
            ];
        }

        $url = $baseUrl
            . '/rest/v1/'
            . rawurlencode($actualTable);

        if ($query !== '') {
            $url .= '?' . $query;
        }

        $headers = [
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        if (in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
            $headers[] = 'Prefer: return=representation';
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        if (
            in_array($method, ['POST', 'PATCH'], true) &&
            $payload !== null
        ) {
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

            if ($json === false) {
                curl_close($ch);

                return [
                    'ok' => false,
                    'data' => [],
                    'status' => 0,
                    'error' => 'ไม่สามารถแปลงข้อมูลเป็น JSON ได้',
                ];
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int)curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        if ($response === false) {
            return [
                'ok' => false,
                'data' => [],
                'status' => $status,
                'error' => 'เชื่อมต่อ Supabase ไม่สำเร็จ: ' . $curlError,
            ];
        }

        $decoded = json_decode($response, true);

        $success = $status >= 200 && $status < 300;

        $data = is_array($decoded) ? $decoded : [];

        $error = '';

        if (!$success) {
            if (is_array($decoded)) {
                $error = (string)(
                    $decoded['message']
                    ?? $decoded['msg']
                    ?? $decoded['error_description']
                    ?? $decoded['error']
                    ?? ''
                );
            }

            if ($error === '') {
                $error = 'Supabase ตอบกลับ HTTP ' . $status;
            }
        }

        return [
            'ok' => $success,
            'data' => $data,
            'status' => $status,
            'error' => $error,
        ];
    }
}
