<?php
/**
 * CRON MONITOR — jalan tiap 5 menit (atau per config).
 *
 * Loop semua flight active, fetch FlightAware, bandingin dengan
 * status terakhir di DB, kirim WA (text + map) ke semua penerima user.
 *
 * Usage: php cron/monitor.php [--once]
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/flight_tracker.php';
require_once __DIR__ . '/../app/wa_sender.php';

function log_line(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

$flights = DB::q(
    'SELECT f.*, u.username, u.wuzapi_url, u.wuzapi_token
     FROM flights f JOIN users u ON u.id = f.user_id
     WHERE f.active = 1'
);

if (!$flights) {
    log_line('Tidak ada flight aktif.');
    exit(0);
}

$report = ['checked' => 0, 'sent' => 0, 'failed' => 0];

foreach ($flights as $flight) {
    $flightId = (int) $flight['id'];
    $userId = (int) $flight['user_id'];
    $report['checked']++;

    log_line("Flight #{$flightId} ({$flight['flight_code']}): {$flight['flightaware_url']}");

    $data = FlightTracker::check($flight['flightaware_url']);

    if ($data['error'] !== null) {
        $report['failed']++;
        log_line("  ❌ {$data['error']}");
        DB::exec(
            'INSERT INTO flight_updates (flight_id, status, title, position, sent) VALUES (?, ?, ?, ?, 0)',
            [$flightId, 'error', $data['error'], '', '']
        );
        continue;
    }

    $currentStatus = $data['status'];
    $lastStatus = (string) DB::val(
        'SELECT status FROM flight_updates WHERE flight_id = ? AND status <> "error" ORDER BY id DESC LIMIT 1',
        [$flightId]
    );

    $mode = $flight['mode'];
    $changed = ($lastStatus === '' || $currentStatus !== $lastStatus);
    $shouldSend = ($mode === 'always') || $changed;

    log_line("  Status: {$currentStatus} (sebelumnya: " . ($lastStatus ?: '—') . ") → " . ($shouldSend ? 'KIRIM' : 'skip'));

    // Penerima user ini
    $recipients = DB::q('SELECT phone FROM recipients WHERE user_id = ? AND active = 1', [$userId]);

    $sent = 0;
    if ($shouldSend && $recipients) {
        // 1) Text
        $title = $data['title'] !== '' ? $data['title'] : '';
        $lines = [
            '✈️ *' . ($flight['flight_code'] ?: 'Flight') . '*',
            'Status: ' . $currentStatus,
        ];
        if ($title !== '') $lines[] = 'Info: ' . mb_substr($title, 0, 120);
        if ($data['position'] !== '') $lines[] = 'Detail: ' . $data['position'];
        $lines[] = 'Check: ' . date('Y-m-d H:i:s') . ' WIB';
        $textMsg = implode("\n", $lines);

        $mapBytes = null;
        if ((int) $flight['send_map'] === 1) {
            $mapUrl = $data['map_url'] ?? '';
            if ($mapUrl === '') {
                $mapUrl = FlightTracker::mapUrl($flight['flightaware_url']);
            }
            if ($mapUrl !== '') {
                $mapBytes = FlightTracker::fetchMap($mapUrl);
                log_line('  Map: ' . ($mapBytes !== null ? strlen($mapBytes) . ' bytes' : 'gagal download'));
            }
        }

        foreach ($recipients as $r) {
            $res = WaSender::text($flight['wuzapi_url'], $flight['wuzapi_token'], $r['phone'], $textMsg);
            if ($res['ok']) {
                $sent++;
                log_line("  ✅ Text → {$r['phone']}");
            } else {
                $report['failed']++;
                log_line("  ❌ Text → {$r['phone']}: {$res['error']}");
            }

            if ($mapBytes !== null) {
                $caption = '🗺 ' . ($flight['flight_code'] ?: '') . ' - ' . $currentStatus . ' - ' . date('Y-m-d H:i:s') . ' WIB';
                $resImg = WaSender::image($flight['wuzapi_url'], $flight['wuzapi_token'], $r['phone'], $caption, $mapBytes);
                if ($resImg['ok']) {
                    $sent++;
                    log_line("  ✅ Map → {$r['phone']}");
                } else {
                    $report['failed']++;
                    log_line("  ❌ Map → {$r['phone']}: {$resImg['error']}");
                }
            }
        }
    }

    $report['sent'] += $sent;

    DB::exec(
        'INSERT INTO flight_updates (flight_id, status, title, position, sent, sent_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$flightId, $currentStatus, $data['title'], $data['position'], $sent > 0 ? 1 : 0, $sent > 0 ? date('Y-m-d H:i:s') : null]
    );
}

log_line("Selesai: {$report['checked']} flight dicek, {$report['sent']} pesan terkirim, {$report['failed']} gagal.");
