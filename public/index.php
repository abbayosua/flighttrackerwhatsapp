<?php
/**
 * Halaman publik: track flight TANPA login.
 * URL di-test dulu (client + server), update dikirim via WhatsApp shared.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/views.php';
require_once __DIR__ . '/../app/flight_tracker.php';

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
        // Server-side test: pastikan URL beneran bisa diakses
        $test = FlightTracker::check($url, 15);
        if ($test['error'] !== null || stripos($test['status'], 'unknown') !== false || stripos($test['status'], 'not found') !== false) {
            $msgErr = 'URL gagal di-test: flight tidak ditemukan / tidak bisa diakses. Coba cek lagi URL-nya.';
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
}

page_header('Track Flight');
?>
<style>
#testResult { margin-top: 10px; padding: 10px 12px; border-radius: 6px; font-size: 13px; display: none; }
#testResult.ok { background: #dcfce7; color: #166534; }
#testResult.fail { background: #fee2e2; color: #991b1b; }
#testResult.loading { background: #e0f2fe; color: #075985; }
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.6); z-index: 100; align-items: center; justify-content: center; }
.modal-overlay.show { display: flex; }
.modal-box { background: #fff; border-radius: 10px; padding: 24px; max-width: 420px; width: 90%; box-shadow: 0 10px 30px rgba(0,0,0,.25); }
.modal-box h3 { font-size: 16px; margin-bottom: 10px; color: #991b1b; }
.modal-box p { font-size: 14px; color: #1e293b; margin-bottom: 16px; }
.row2 { display: flex; gap: 8px; }
.row2 input { flex: 1; }
</style>

<div class="auth-wrap">
    <h1>✈️ <?= APP_NAME ?></h1>
    <div class="card">
        <h2>Pantau Penerbangan</h2>
        <p class="muted" style="margin-bottom:10px">Masukkan URL FlightAware + nomor WhatsApp kamu. Update status + peta posisi dikirim otomatis tiap 5 menit.</p>
        <?php if ($msg): ?><div class="msg msg-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($msgErr): ?><div class="msg msg-err"><?= htmlspecialchars($msgErr) ?></div><?php endif; ?>
        <form method="post" id="trackForm">
            <label>URL FlightAware</label>
            <div class="row2">
                <input type="url" name="flightaware_url" id="fa_url" required placeholder="https://www.flightaware.com/live/flight/SJV855/..." style="flex:1">
                <button type="button" class="btn btn-gray" id="testBtn" style="white-space:nowrap">Test URL</button>
            </div>
            <div id="testResult"></div>
            <label>Nomor WhatsApp Kamu</label>
            <input type="text" name="phone" required placeholder="08xxxxxxxxxx">
            <p style="height:14px"></p>
            <button class="btn" style="width:100%" id="submitBtn">Mulai Pantau</button>
        </form>
        <hr>
        <p class="muted">Punya akun? <a href="login.php">Login</a> · <a href="register.php">Daftar</a> buat kirim dari WhatsApp kamu sendiri + kelola semua flight.</p>
    </div>
</div>

<!-- Modal error -->
<div class="modal-overlay" id="modal">
    <div class="modal-box">
        <h3>❌ URL Tidak Valid</h3>
        <p id="modalMsg"></p>
        <button class="btn btn-danger" onclick="closeModal()">OK, Perbaiki URL</button>
    </div>
</div>

<script>
const resultEl = document.getElementById('testResult');
const modal = document.getElementById('modal');
const modalMsg = document.getElementById('modalMsg');

function showModal(msg) {
    modalMsg.textContent = msg;
    modal.classList.add('show');
}
function closeModal() { modal.classList.remove('show'); }
modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

async function testUrl() {
    const url = document.getElementById('fa_url').value.trim();
    resultEl.className = 'loading';
    resultEl.style.display = 'block';
    resultEl.textContent = '⏳ Mengecek URL...';
    try {
        const res = await fetch('check_url.php?url=' + encodeURIComponent(url));
        const data = await res.json();
        if (data.ok) {
            resultEl.className = 'ok';
            resultEl.textContent = '✅ URL valid: ' + data.flight_code + ' — ' + data.status + ' (' + (data.title || '') + ')';
            return true;
        } else {
            resultEl.className = 'fail';
            resultEl.textContent = '❌ ' + data.error;
            showModal(data.error);
            return false;
        }
    } catch (e) {
        resultEl.className = 'fail';
        resultEl.textContent = '❌ Gagal menghubungi server.';
        showModal('Gagal menghubungi server. Coba lagi.');
        return false;
    }
}

// Tombol Test URL
document.getElementById('testBtn').addEventListener('click', testUrl);

// Submit: auto-test dulu
document.getElementById('trackForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const ok = await testUrl();
    if (ok) {
        e.target.submit();
    }
});
</script>
<?php page_footer();
