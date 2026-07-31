<?php
/**
 * Dashboard: daftar flight user + tambah flight + kelola penerima.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/views.php';
require_once __DIR__ . '/../app/flight_tracker.php';

Auth::check();
$user = Auth::user();
$userId = Auth::id();
$msg = '';
$msgErr = '';

// Tambah flight
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add_flight') {
        $url = trim($_POST['flightaware_url'] ?? '');
        if (!str_starts_with($url, 'http')) {
            $msgErr = 'URL FlightAware tidak valid';
        } else {
            DB::exec(
                'INSERT INTO flights (user_id, flightaware_url, flight_code, interval_min, send_map, mode) VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $url, FlightTracker::flightCode($url), (int) ($_POST['interval_min'] ?: 5),
                 isset($_POST['send_map']) ? 1 : 0, $_POST['mode'] === 'on_change' ? 'on_change' : 'always']
            );
            $msg = 'Flight ditambahkan!';
        }
    } elseif ($action === 'add_recipient') {
        $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        if (strlen($phone) < 9) {
            $msgErr = 'Nomor WhatsApp tidak valid';
        } else {
            $exists = DB::val('SELECT id FROM recipients WHERE user_id = ? AND phone = ?', [$userId, $phone]);
            if ($exists) {
                $msgErr = 'Nomor sudah terdaftar';
            } else {
                DB::exec('INSERT INTO recipients (user_id, phone) VALUES (?, ?)', [$userId, $phone]);
                $msg = 'Penerima ditambahkan!';
            }
        }
    }
}

// Hapus flight / recipient
if (isset($_GET['del_flight'])) {
    DB::exec('DELETE FROM flights WHERE id = ? AND user_id = ?', [(int) $_GET['del_flight'], $userId]);
    $msg = 'Flight dihapus';
    header('Location: dashboard.php'); exit;
}
if (isset($_GET['del_recipient'])) {
    DB::exec('DELETE FROM recipients WHERE id = ? AND user_id = ?', [(int) $_GET['del_recipient'], $userId]);
    $msg = 'Penerima dihapus';
    header('Location: dashboard.php'); exit;
}
if (isset($_GET['toggle_flight'])) {
    $fid = (int) $_GET['toggle_flight'];
    $f = DB::row('SELECT * FROM flights WHERE id = ? AND user_id = ?', [$fid, $userId]);
    if ($f) {
        DB::exec('UPDATE flights SET active = ? WHERE id = ?', [$f['active'] ? 0 : 1, $fid]);
        header('Location: dashboard.php'); exit;
    }
}

$flights = DB::q(
    'SELECT f.*, (SELECT status FROM flight_updates u WHERE u.flight_id = f.id ORDER BY u.id DESC LIMIT 1) AS last_status,
            (SELECT checked_at FROM flight_updates u WHERE u.flight_id = f.id ORDER BY u.id DESC LIMIT 1) AS last_checked
     FROM flights f WHERE f.user_id = ? ORDER BY f.created_at DESC',
    [$userId]
);
$recipients = DB::q('SELECT * FROM recipients WHERE user_id = ? ORDER BY id', [$userId]);

page_header('Dashboard', true);
if ($msg): ?><div class="msg msg-ok"><?= htmlspecialchars($msg) ?></div><?php endif;
if ($msgErr): ?><div class="msg msg-err"><?= htmlspecialchars($msgErr) ?></div><?php endif;
?>

<div class="card">
    <h2>➕ Tambah Flight</h2>
    <form method="post">
        <input type="hidden" name="action" value="add_flight">
        <label>URL FlightAware</label>
        <input type="url" name="flightaware_url" required placeholder="https://www.flightaware.com/live/flight/SJV855/...">
        <div class="row">
            <div style="flex:1">
                <label>Interval (menit)</label>
                <input type="number" name="interval_min" min="1" value="5">
            </div>
            <div style="flex:1">
                <label>Mode</label>
                <select name="mode">
                    <option value="always">Kirim tiap interval</option>
                    <option value="on_change">Kirim hanya saat status berubah</option>
                </select>
            </div>
        </div>
        <div class="checkbox-line"><input type="checkbox" name="send_map" checked> Kirim gambar peta</div>
        <button class="btn">Tambah Flight</button>
    </form>
</div>

<div class="card">
    <h2>✈️ Flight Kamu</h2>
    <?php if (!$flights): ?><p class="muted">Belum ada flight. Tambah di atas!</p><?php endif; ?>
    <table>
        <tr><th>Flight</th><th>Status</th><th>Interval</th><th>Terakhir Cek</th><th>Aksi</th></tr>
        <?php foreach ($flights as $f): ?>
        <tr>
            <td>
                <strong><?= htmlspecialchars($f['flight_code'] ?: '-') ?></strong><br>
                <span class="muted" style="font-size:11px;word-break:break-all"><?= htmlspecialchars($f['flightaware_url']) ?></span>
            </td>
            <td><?= htmlspecialchars($f['last_status'] ?? '— belum dicek') ?>
                <?php if (!$f['active']): ?><br><span class="badge badge-red">OFF</span><?php endif; ?>
            </td>
            <td><?= (int) $f['interval_min'] ?> mnt</td>
            <td class="muted"><?= $f['last_checked'] ? htmlspecialchars($f['last_checked']) : '—' ?></td>
            <td>
                <a class="btn btn-gray" style="padding:4px 10px;font-size:12px" href="?toggle_flight=<?= $f['id'] ?>"><?= $f['active'] ? 'Pause' : 'Aktifkan' ?></a>
                <a class="btn btn-danger" style="padding:4px 10px;font-size:12px" href="?del_flight=<?= $f['id'] ?>" onclick="return confirm('Hapus flight ini?')">Hapus</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h2>📱 Penerima WhatsApp</h2>
    <form method="post" class="row">
        <input type="hidden" name="action" value="add_recipient">
        <input type="text" name="phone" placeholder="08xxxxxxxxxx" style="flex:1" required>
        <button class="btn">Tambah Nomor</button>
    </form>
    <?php if (!$recipients): ?><p class="muted">Belum ada penerima — tambah nomor biar update terkirim.</p><?php endif; ?>
    <table style="margin-top:10px">
        <tr><th>Nomor</th><th></th></tr>
        <?php foreach ($recipients as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['phone']) ?></td>
            <td><a class="btn btn-danger" style="padding:4px 10px;font-size:12px" href="?del_recipient=<?= $r['id'] ?>" onclick="return confirm('Hapus nomor ini?')">Hapus</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php page_footer();
