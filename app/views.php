<?php
/**
 * Layout helper (header/footer) untuk halaman web.
 */

declare(strict_types=1);

function page_header(string $title, bool $nav = false): void
{
    $app = APP_NAME;
    $user = Auth::user();
    echo <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title} - {$app}</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; color: #1e293b; }
header { background: #0f172a; color: #fff; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; }
header h1 { font-size: 18px; }
header nav a { color: #cbd5e1; text-decoration: none; margin-left: 16px; }
header nav a:hover { color: #fff; }
main { max-width: 900px; margin: 24px auto; padding: 0 16px; }
.card { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.card h2 { font-size: 16px; margin-bottom: 14px; }
label { display: block; font-size: 13px; font-weight: 600; margin: 10px 0 4px; }
input[type=text], input[type=password], input[type=number], input[type=url], select {
  width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;
}
.btn { display: inline-block; background: #2563eb; color: #fff; border: 0; border-radius: 6px; padding: 9px 18px; font-size: 14px; cursor: pointer; text-decoration: none; }
.btn:hover { background: #1d4ed8; }
.btn-danger { background: #dc2626; }
.btn-danger:hover { background: #b91c1c; }
.btn-gray { background: #64748b; }
.btn-gray:hover { background: #475569; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
th { color: #64748b; font-size: 12px; text-transform: uppercase; }
.msg { padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 14px; }
.msg-ok { background: #dcfce7; color: #166534; }
.msg-err { background: #fee2e2; color: #991b1b; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.badge-green { background: #dcfce7; color: #166534; }
.badge-red { background: #fee2e2; color: #991b1b; }
.muted { color: #64748b; font-size: 13px; }
.auth-wrap { max-width: 380px; margin: 60px auto; }
.auth-wrap h1 { text-align: center; margin-bottom: 20px; }
.row { display: flex; gap: 10px; align-items: center; }
.checkbox-line { display: flex; align-items: center; gap: 8px; margin: 10px 0; font-size: 14px; }
.checkbox-line input { width: auto; }
hr { border: 0; border-top: 1px solid #e2e8f0; margin: 16px 0; }
</style>
</head>
<body>
HTML;
    if ($nav) {
        echo '<header><h1>✈️ ' . APP_NAME . '</h1><nav>'
            . '<a href="dashboard.php">Dashboard</a>'
            . '<a href="settings.php">Settings</a>'
            . '<a href="logout.php">Logout (' . htmlspecialchars($user['username'] ?? '') . ')</a>'
            . '</nav></header>';
    }
    echo '<main>';
}

function page_footer(): void
{
    echo '</main></body></html>';
}
