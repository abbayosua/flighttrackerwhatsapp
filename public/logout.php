<?php
/**
 * Logout.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

Auth::logout();
header('Location: index.php');
exit;
