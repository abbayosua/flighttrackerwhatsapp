<?php
/**
 * Settings: kredensial Wuzapi + cek status pairing WhatsApp sendiri.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/views.php';

Auth::check();
$userId = Auth::id();
$user = Auth::user();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = trim($_POST['wuzapi_url'] ?? '');
    $token = trim($_POST['wuzapi_token'] ?? '');
    if ($url === '' || $token === '') {
        $url = WUZAPI_DEFAULT_URL;
        $token = WUZAPI_DEFAULT_TOKEN;
        $msg = 'Kembali ke WhatsApp shared (default).';
    } else {
        $msg = 'Pengaturan Wuzapi disimpan.';
    }
    DB::exec('UPDATE users SET wuzapi_url = ?, wuzapi_token = ? WHERE id = ?', [$url, $token, $userId]);
    $user = Auth::user();
}

if (isset($_GET['test'])) {
    $msg = $_GET['test'] === 'ok'
        ? '✅ Test terkirim! Cek WhatsApp penerima.'
        : '❌ Test gagal — cek base URL / token Wuzapi kamu.';
}
if (isset($_GET['err']) && $_GET['err'] === 'norecipient') {
    $msg = 'Tambah dulu penerima di Dashboard sebelum test.';
}

// Cek status session Wuzapi
$sessionStatus = null;
$sessionError = null;
if (isset($_GET['check'])) {
    $ch = curl_init(rtrim($user['wuzapi_url'], '/') . '/session/status');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Token: ' . $user['wuzapi_token']],
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        $sessionError = 'cURL: ' . $err;
    } else {
        $json = json_decode((string) $body, true);
        if ($http === 200 && is_array($json)) {
            $sessionStatus = $json['data'] ?? $json;
        } else {
            $sessionError = 'HTTP ' . $http . ': ' . (is_array($json) ? ($json['error'] ?? $body) : $body);
        }
    }
}

page_header('Settings', true);
?>
<div class="card">
    <h2>⚙️ Pairing WhatsApp Sendiri</h2>
    <?php if ($msg): ?><div class="msg msg-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post">
        <label>Wuzapi Base URL</label>
        <input type="text" name="wuzapi_url" value="<?= htmlspecialchars($user['wuzapi_url']) ?>">
        <label>Wuzapi Token</label>
        <input type="text" name="wuzapi_token" value="<?= htmlspecialchars($user['wuzapi_token']) ?>">
        <p class="muted" style="margin-top:8px">
            Isi <b>URL + Token Wuzapi kamu sendiri</b> biar update terkirim dari nomor WhatsApp kamu
            (butuh server Wuzapi sendiri — pairing/scan QR dilakukan di dashboard Wuzapi kamu).
            Kosongkan keduanya untuk kembali ke WhatsApp shared.
        </p>
        <p style="height:10px"></p>
        <button class="btn">Simpan</button>
        <a class="btn btn-gray" href="settings.php?check=1">Cek Status Session</a>
    </form>

    <?php if ($sessionStatus !== null): ?>
        <div class="msg msg-ok" style="margin-top:14px">
            Status session <b><?= htmlspecialchars($user['wuzapi_url']) ?></b>:<br>
            Connected: <b><?= (!empty($sessionStatus['connected']) || !empty($sessionStatus['Connected'])) ? '✅ ya' : '❌ tidak' ?></b><br>
            Logged In: <b><?= (!empty($sessionStatus['loggedIn']) || !empty($sessionStatus['LoggedIn'])) ? '✅ ya' : '❌ tidak (scan QR di dashboard Wuzapi kamu)' ?></b>
        </div>
    <?php elseif ($sessionError !== null): ?>
        <div class="msg msg-err" style="margin-top:14px">❌ Gagal cek session: <?= htmlspecialchars($sessionError) ?></div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>🔍 Test Kirim</h2>
    <p class="muted">Kirim pesan tes ke nomor pertama di daftar penerima buat mastiin Wuzapi connect.</p>
    <form method="post" action="test_send.php">
        <button class="btn">Kirim Test</button>
    </form>
</div>
<?php page_footer();
