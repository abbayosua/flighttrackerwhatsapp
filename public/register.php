<?php
/**
 * Register page.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/views.php';

$error = '';
$ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = Auth::register($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($result['ok']) {
        Auth::login($_POST['username'], $_POST['password']);
        header('Location: dashboard.php');
        exit;
    }
    $error = $result['error'];
}

page_header('Daftar');
?>
<div class="auth-wrap">
    <h1>✈️ <?= APP_NAME ?></h1>
    <div class="card">
        <h2>Daftar Akun Baru</h2>
        <?php if ($error): ?><div class="msg msg-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <label>Username</label>
            <input type="text" name="username" required autofocus>
            <label>Password</label>
            <input type="password" name="password" required>
            <p class="muted">Minimal 6 karakter. Setelah daftar, WhatsApp kirimnya pakai nomor WhatsApp shared — bisa diganti di Settings.</p>
            <p style="height:14px"></p>
            <button class="btn" style="width:100%">Daftar</button>
        </form>
        <hr>
        <p class="muted">Sudah punya akun? <a href="index.php">Login di sini</a></p>
    </div>
</div>
<?php page_footer();
