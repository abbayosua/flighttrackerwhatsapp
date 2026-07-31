<?php
/**
 * WaSender: kirim text & image via Wuzapi API.
 */

declare(strict_types=1);

class WaSender
{
    /**
     * Kirim text message.
     * $baseUrl contoh: http://45.158.126.130:48499
     */
    public static function text(string $baseUrl, string $token, string $phone, string $message): array
    {
        $phone = self::formatPhone($phone);
        $payload = json_encode(['Phone' => $phone, 'Body' => $message]);
        return self::request($baseUrl, '/chat/send/text', $token, $payload);
    }

    /** Kirim image (PNG/JPEG bytes). */
    public static function image(string $baseUrl, string $token, string $phone, string $caption, string $imageData, string $mime = 'image/png'): array
    {
        $phone = self::formatPhone($phone);
        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($imageData);
        $payload = json_encode(['Phone' => $phone, 'Caption' => $caption, 'Image' => $dataUri]);
        return self::request($baseUrl, '/chat/send/image', $token, $payload);
    }

    /** Format 0811... → 62811..., 62811... tetap. */
    public static function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        return $phone;
    }

    private static function request(string $baseUrl, string $endpoint, string $token, string $jsonPayload, int $timeout = 30): array
    {
        $ch = curl_init(rtrim($baseUrl, '/') . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Token: ' . $token,
            ],
            CURLOPT_POSTFIELDS => $jsonPayload,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'error' => 'cURL: ' . $err];
        }
        $json = json_decode((string) $body, true);
        if (is_array($json) && ($json['success'] ?? false)) {
            return ['ok' => true, 'response' => $json, 'http' => $httpCode];
        }
        $msg = is_array($json) ? ($json['error'] ?? json_encode($json)) : $body;
        return ['ok' => false, 'error' => "HTTP {$httpCode}: {$msg}", 'http' => $httpCode];
    }
}
