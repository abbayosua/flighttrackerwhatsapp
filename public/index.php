<?php
/**
 * Halaman publik: track flight TANPA login.
 * Update dikirim via WhatsApp shared (default).
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/views.php';
require_once __DIR__ . '/../app/flight_tracker.php';

// Kalau udah login, arahin ke dashboard
if (Auth::id() !== null) {
    header('Location: dashboard.php');
    exit;
}

$msg = '';
$msgErr = '';
$publicUserId = (int) DB::val("SELECT id FROM users WHERE username = '__public__'");
if (!$publicUserId) {
    $msgErr = 'Konfigurasi user shared belum ada — jalankan: php cron/seed.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $publicUserId) {
    $url = trim($_POST['flightaware_url'] ?? '');
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');

    if (!str_starts_with($url, 'http')) {
        $msgErr = 'URL FlightAware tidak valid';
    } elseif (strlen($phone) < 9) {
        $msgErr = 'Nomor WhatsApp tidak valid (contoh: 08123456789)';
    } else {
        $flightId = (int) DB::val('SELECT id FROM flights WHERE user_id = ? AND flightaware_url = ?', [$publicUserId, $url]);
        if (!$flightId) {
            DB::exec(
                'INSERT INTO flights (user_id, flightaware_url, flight_code, interval_min, send_map, mode) VALUES (?, ?, ?, 5, 1, "always")',
                [$publicUserId, $url, FlightTracker::flightCode($url)]
            );
            $flightId = DB::lastId();
        }
        $exists = DB::val('SELECT id FROM recipients WHERE user_id = ? AND phone = ?', [$publicUserId, $phone]);
        if (!$exists) {
            DB::exec('INSERT INTO recipients (user_id, phone) VALUES (?, ?)', [$publicUserId, $phone]);
        }
        $msg = '✅ Flight terdaftar! Update akan dikirim ke ' . $phone . ' tiap 5 menit via WhatsApp kami. Daftar akun untuk kirim dari WhatsApp kamu sendiri.';
    }
}

page_header('Track Flight');
?>
<div class="auth-wrap">
    <h1>✈️ <?= APP_NAME ?></h1>
    <div class="card">
        <h2>Pantau Penerbangan</h2>
        <p class="muted" style="margin-bottom:10px">Masukkan URL FlightAware + nomor WhatsApp kamu. Update status + peta posisi dikirim otomatis tiap 5 menit.</p>
        <?php if ($msg): ?><div class="msg msg-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($msgErr): ?><div class="msg msg-err"><?= htmlspecialchars($msgErr) ?></div><?php endif; ?>
        <form method="post">
            <label>URL FlightAware</label>
            <input type="url" name="flightaware_url" required placeholder="https://www.flightaware.com/live/flight/SJV855/...">
            <label>Nomor WhatsApp Kamu</label>
            <input type="text" name="phone" required placeholder="08xxxxxxxxxx">
            <p style="height:14px"></p>
            <button class="btn" style="width:100%">Mulai Pantau</button>
        </form>
        <hr>
        <p class="muted">Punya akun? <a href="login.php">Login</a> · <a href="register.php">Daftar</a> buat kirim dari WhatsApp kamu sendiri + kelola semua flight.</p>
    </div>
</div>
<?php page_footer();
