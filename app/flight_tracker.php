<?php
/**
 * FlightTracker: fetch halaman FlightAware, parse status/posisi, ambil peta.
 */

declare(strict_types=1);

class FlightTracker
{
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

    /**
     * Fetch + parse status dari URL FlightAware.
     * Returns: ['status' => string, 'title' => string, 'position' => string, 'error' => ?string]
     */
    public static function check(string $url, int $timeout = 30): array
    {
        $html = self::httpGet($url, $timeout);
        if ($html === null) {
            return ['status' => '', 'title' => '', 'position' => '', 'error' => 'Gagal fetch FlightAware (timeout / network)'];
        }

        // Title: <title>...</title>
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = trim(strip_tags($m[1]));
        }

        $status = self::extractStatus($html, $title);
        $position = self::extractPosition($html);

        return ['status' => $status, 'title' => $title, 'position' => $position, 'error' => null];
    }

    /** Build URL peta statis dari URL flight. */
    public static function mapUrl(string $flightUrl): string
    {
        if (preg_match('#https?://[^/]+/live/flight/(.+?)(?:/history)?(/.*?)/?$#', $flightUrl, $m)) {
            $base = $m[1];
            $rest = $m[2] ?? '';
            return "https://www.flightaware.com/ajax/flight/map/{$base}{$rest}/?width=800&height=418&dpi=2";
        }
        return '';
    }

    /** Download peta → bytes PNG, atau null gagal. */
    public static function fetchMap(string $mapUrl, int $timeout = 20): ?string
    {
        return self::httpGet($mapUrl, $timeout, true);
    }

    /** Derive kode flight dari URL (/live/flight/XXXX/...). */
    public static function flightCode(string $url): string
    {
        if (preg_match('#/live/flight/([^/]+)#', $url, $m)) {
            return strtoupper($m[1]);
        }
        return '';
    }

    private static function extractStatus(string $html, string $title): string
    {
        $t = strtolower($title);
        $h = strtolower($html);

        if (str_contains($t, 'landed')) return 'Landed ✅';
        if (str_contains($t, 'en route') || str_contains($t, 'in flight') || str_contains($t, 'airborne')) return 'In Flight ✈️';
        if (str_contains($t, 'scheduled') || str_contains($t, 'on time')) return 'Scheduled / On Time 🕐';
        if (str_contains($t, 'delayed')) return 'Delayed ⏰';
        if (str_contains($t, 'cancelled') || str_contains($t, 'canceled')) return 'Cancelled ❌';
        if (str_contains($t, 'diverted')) return 'Diverted 🔄';
        if (str_contains($t, 'departed')) return 'Departed 🛫';
        if (str_contains($t, 'arrived')) return 'Arrived ✅';
        if (str_contains($t, 'unknown') || str_contains($t, 'not found')) return 'Unknown / Not Found ❓';

        $fallback = [
            'flight landed' => 'Landed ✅', 'en route' => 'In Flight ✈️', 'in flight' => 'In Flight ✈️',
            'airborne' => 'In Flight ✈️', 'scheduled' => 'Scheduled 🕐', 'departed' => 'Departed 🛫',
            'arrived' => 'Arrived ✅', 'delayed' => 'Delayed ⏰', 'cancelled' => 'Cancelled ❌',
            'diverted' => 'Diverted 🔄',
        ];
        foreach ($fallback as $kw => $label) {
            if (str_contains($h, $kw)) return $label;
        }
        return $title !== '' ? $title : 'Unknown ❓';
    }

    private static function extractPosition(string $html): string
    {
        $patterns = [
            '/(\d+)\s*knots/i',
            '/(\d[\d,]*)\s*(?:feet|ft)/i',
            '/(\d+)\s*miles?\s+(?:to|from)/i',
        ];
        $info = [];
        foreach ($patterns as $pat) {
            if (preg_match($pat, $html, $m)) {
                $info[] = trim($m[0]);
            }
        }
        return implode(', ', $info);
    }

    private static function httpGet(string $url, int $timeout, bool $raw = false): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => self::UA,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
            ],
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $httpCode >= 400) {
            return null;
        }
        return $raw ? $body : (string) $body;
    }
}
