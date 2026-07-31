<?php
/**
 * Auth: session login/register + helper.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth
{
    public static function register(string $username, string $password): array
    {
        $username = trim($username);
        if (strlen($username) < 3) {
            return ['ok' => false, 'error' => 'Username minimal 3 karakter'];
        }
        if (strlen($password) < 6) {
            return ['ok' => false, 'error' => 'Password minimal 6 karakter'];
        }
        $exists = DB::val('SELECT id FROM users WHERE username = ?', [$username]);
        if ($exists) {
            return ['ok' => false, 'error' => 'Username sudah dipakai'];
        }
        DB::exec(
            'INSERT INTO users (username, password_hash, wuzapi_url, wuzapi_token) VALUES (?, ?, ?, ?)',
            [$username, password_hash($password, PASSWORD_DEFAULT), WUZAPI_DEFAULT_URL, WUZAPI_DEFAULT_TOKEN]
        );
        return ['ok' => true, 'id' => DB::lastId()];
    }

    public static function login(string $username, string $password): array
    {
        $user = DB::row('SELECT * FROM users WHERE username = ?', [trim($username)]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Username atau password salah'];
        }
        self::setUser($user);
        return ['ok' => true, 'user' => $user];
    }

    public static function setUser(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function user(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }
        return DB::row('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function check(): void
    {
        if (self::id() === null) {
            header('Location: index.php');
            exit;
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
