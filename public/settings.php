<?php
/**
 * Settings: kredensial Wuzapi user (default shared).
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/views.php';

Auth::check();
$userId = Auth::id();
$user = Auth::user();
$msg = '';
if (isset($_GET['test'])) {
    $msg = $_GET['test'] === 'ok'
        ? '✅ Test terkirim! Cek WhatsApp penerima.'
        : '❌ Test gagal — cek base URL / token Wuzapi kamu.';
}
if (isset($_GET['err']) && $_GET['err'] === 'norecipient') {
    $msg = 'Tambah dulu penerima di Dashboard sebelum test.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = trim($_POST['wuzapi_url'] ?? '');
    $token = trim($_POST['wuzapi_token'] ?? '');
    if ($url === '' || $token === '') {
        // Reset ke default shared
        $url = WUZAPI_DEFAULT_URL;
        $token = WUZAPI_DEFAULT_TOKEN;
        $msg = 'Kembali ke WhatsApp shared (default).';
    } else {
        $msg = 'Pengaturan Wuzapi disimpan.';
    }
    DB::exec('UPDATE users SET wuzapi_url = ?, wuzapi_token = ? WHERE id = ?', [$url, $token, $userId]);
}

page_header('Settings', true);
?>
<div class="card">
    <h2>⚙️ Pengaturan WhatsApp</h2>
    <?php if ($msg): ?><div class="msg msg-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post">
        <label>Wuzapi Base URL</label>
        <input type="text" name="wuzapi_url" value="<?= htmlspecialchars($user['wuzapi_url']) ?>">
        <label>Wuzapi Token</label>
        <input type="text" name="wuzapi_token" value="<?= htmlspecialchars($user['wuzapi_token']) ?>">
        <p class="muted" style="margin-top:8px">
            Kosongkan keduanya untuk pakai WhatsApp shared (token dari config lokal).
            Isi punya sendiri kalau mau kirim dari nomor WhatsApp kamu (butuh server Wuzapi sendiri).
        </p>
        <p style="height:10px"></p>
        <button class="btn">Simpan</button>
        <a class="btn btn-gray" href="dashboard.php">Kembali</a>
    </form>
</div>

<div class="card">
    <h2>🔍 Test Kirim</h2>
    <p class="muted">Kirim pesan tes ke nomor pertama di daftar penerima buat mastiin Wuzapi connect.</p>
    <form method="post" action="test_send.php">
        <button class="btn">Kirim Test</button>
    </form>
</div>
<?php page_footer();
