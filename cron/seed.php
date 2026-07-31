<?php
/**
 * Seed: buat user + flight SJV855 + 2 penerima (setara konfigurasi awal Python).
 * Usage: php cron/seed.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/flight_tracker.php';

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? 'admin123';
$flightUrl = $argv[3] ?? 'https://www.flightaware.com/live/flight/SJV855/history/20260728/0755Z/WIDD/WIII';
$phones = array_slice($argv, 4);
if (!$phones) {
    $phones = ['08117774884', '081170004884'];
}

$existing = DB::val('SELECT id FROM users WHERE username = ?', [$username]);
if ($existing) {
    echo "User '{$username}' sudah ada (id={$existing}).\n";
    $userId = (int) $existing;
} else {
    DB::exec(
        'INSERT INTO users (username, password_hash, wuzapi_url, wuzapi_token) VALUES (?, ?, ?, ?)',
        [$username, password_hash($password, PASSWORD_DEFAULT), WUZAPI_DEFAULT_URL, WUZAPI_DEFAULT_TOKEN]
    );
    $userId = DB::lastId();
    echo "User '{$username}' dibuat (id={$userId}).\n";
}

// Flight
$flightId = (int) DB::val(
    'SELECT id FROM flights WHERE user_id = ? AND flightaware_url = ?', [$userId, $flightUrl]
);
if ($flightId) {
    echo "Flight sudah ada (id={$flightId}).\n";
} else {
    DB::exec(
        'INSERT INTO flights (user_id, flightaware_url, flight_code, interval_min, send_map, mode) VALUES (?, ?, ?, 5, 1, "always")',
        [$userId, $flightUrl, FlightTracker::flightCode($flightUrl)]
    );
    $flightId = DB::lastId();
    echo "Flight SJV855 dibuat (id={$flightId}).\n";
}

// Recipients
foreach ($phones as $phone) {
    $phone = preg_replace('/\D/', '', $phone);
    $exists = DB::val('SELECT id FROM recipients WHERE user_id = ? AND phone = ?', [$userId, $phone]);
    if ($exists) {
        echo "Penerima {$phone} sudah ada.\n";
    } else {
        DB::exec('INSERT INTO recipients (user_id, phone) VALUES (?, ?)', [$userId, $phone]);
        echo "Penerima {$phone} ditambahkan.\n";
    }
}

echo "Selesai. Login: {$username} / {$password}\n";

// --- User shared untuk mode publik (tanpa login) ---
$pub = DB::val("SELECT id FROM users WHERE username = '__public__'");
if (!$pub) {
    DB::exec(
        'INSERT INTO users (username, password_hash, wuzapi_url, wuzapi_token) VALUES ("__public__", "*", ?, ?)',
        [WUZAPI_DEFAULT_URL, WUZAPI_DEFAULT_TOKEN]
    );
    echo "User shared '__public__' dibuat (mode publik siap).\n";
} else {
    echo "User shared '__public__' sudah ada.\n";
}
