<?php
/**
 * Konfigurasi global aplikasi cekposisi.
 *
 * Untuk kredensial lokal (DB password, token Wuzapi), buat file
 * app/config.local.php — TIDAK ikut di-commit (lihat .gitignore).
 * Contoh isi config.local.php:
 *   define('WUZAPI_DEFAULT_TOKEN', 'token-asli-kamu');
 */

declare(strict_types=1);

// Kredensial lokal override dulu (kalau ada)
$__local = __DIR__ . '/config.local.php';
if (is_file($__local)) {
    require $__local;
}

// ---- Database ----
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'cekposisi');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// ---- Wuzapi default (shared) ----
if (!defined('WUZAPI_DEFAULT_URL')) define('WUZAPI_DEFAULT_URL', 'http://45.158.126.130:48499');
if (!defined('WUZAPI_DEFAULT_TOKEN')) define('WUZAPI_DEFAULT_TOKEN', 'CHANGE_ME');

// ---- App ----
if (!defined('APP_NAME')) define('APP_NAME', 'CekPosisi');
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Asia/Jakarta');
if (!defined('CRON_INTERVAL_DEFAULT')) define('CRON_INTERVAL_DEFAULT', 5); // menit

date_default_timezone_set(APP_TIMEZONE);
