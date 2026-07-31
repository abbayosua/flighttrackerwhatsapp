<?php
/**
 * Login page.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/views.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = Auth::login($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($result['ok']) {
        header('Location: dashboard.php');
        exit;
    }
    $error = $result['error'];
}

page_header('Login');
?>
<div class="auth-wrap">
    <h1>✈️ <?= APP_NAME ?></h1>
    <div class="card">
        <?php if ($error): ?><div class="msg msg-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <label>Username</label>
            <input type="text" name="username" required autofocus>
            <label>Password</label>
            <input type="password" name="password" required>
            <p style="height:14px"></p>
            <button class="btn" style="width:100%">Login</button>
        </form>
        <hr>
        <p class="muted">Belum punya akun? <a href="register.php">Daftar di sini</a> · <a href="index.php">← Track tanpa login</a></p>
    </div>
</div>
<?php page_footer();
