<?php
/**
 * Test send: kirim pesan tes ke penerima pertama user.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/wa_sender.php';

Auth::check();
$userId = Auth::id();
$user = Auth::user();

$recipient = DB::row('SELECT * FROM recipients WHERE user_id = ? ORDER BY id LIMIT 1', [$userId]);
if (!$recipient) {
    header('Location: settings.php?err=norecipient');
    exit;
}

$result = WaSender::text($user['wuzapi_url'], $user['wuzapi_token'], $recipient['phone'], '🧪 Test dari ' . APP_NAME . ' — koneksi OK!');

// Simpan hasil test di query param biar kebaca
$status = $result['ok'] ? 'ok' : 'fail';
header('Location: settings.php?test=' . $status);
exit;
