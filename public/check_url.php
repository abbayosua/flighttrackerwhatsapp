<?php
/**
 * AJAX endpoint publik: test validitas URL FlightAware.
 * Output JSON: {ok: true, flight_code, status, title} atau {ok: false, error}
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/flight_tracker.php';

header('Content-Type: application/json');

$url = trim((string) ($_GET['url'] ?? $_POST['url'] ?? ''));

if ($url === '') {
    echo json_encode(['ok' => false, 'error' => 'URL kosong.']);
    exit;
}

// SSRF protection: hanya izinkan URL dari flightaware.com
if (!preg_match('#^https?://(www\.)?flightaware\.com/#i', $url)) {
    echo json_encode(['ok' => false, 'error' => 'URL harus dari flightaware.com']);
    exit;
}

$data = FlightTracker::check($url, 15);

if ($data['error'] !== null) {
    echo json_encode(['ok' => false, 'error' => 'Gagal diakses: ' . $data['error'] . '. Pastikan URL benar dan lengkap.']);
    exit;
}

// FlightAware balikin halaman "Unknown Flight" dengan HTTP 200 → anggap invalid
if (stripos($data['status'], 'unknown') !== false || stripos($data['status'], 'not found') !== false) {
    echo json_encode(['ok' => false, 'error' => 'Flight tidak ditemukan di FlightAware. Cek kode flight / tanggal / rutenya.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'flight_code' => FlightTracker::flightCode($url),
    'status' => $data['status'],
    'title' => mb_substr($data['title'], 0, 150),
]);
