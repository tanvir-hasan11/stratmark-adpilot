<?php
declare(strict_types=1);

namespace App\Core;

final class Security
{
    private const SESSION_NAME = 'stratmark_adpilot';
    private const IDLE_TIMEOUT = 1800;
    private const ABSOLUTE_TIMEOUT = 28800;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_WINDOW = 900;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::enforceLifetime();
            return;
        }
        $https = self::isHttps();
        session_name(self::SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $https ? '1' : '0');
        session_start();
        self::enforceLifetime();
    }

    public static function csrf(): string
    {
        self::startSession();
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::startSession();
        return is_string($token) && isset($_SESSION['_csrf']) && hash_equals((string)$_SESSION['_csrf'], $token);
    }

    public static function login(string $username, ?int $userId = null, string $role = 'ADMIN'): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION = [
            '_csrf' => bin2hex(random_bytes(32)),
            'admin_user' => $username,
            'user_id' => $userId,
            'role' => $role,
            'login_at' => time(),
            'last_activity' => time(),
        ];
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function user(): ?string
    {
        self::startSession();
        return isset($_SESSION['admin_user']) ? (string)$_SESSION['admin_user'] : null;
    }

    public static function userId(): ?int
    {
        self::startSession();
        return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function role(): string
    {
        self::startSession();
        return (string)($_SESSION['role'] ?? 'ADMIN');
    }

    public static function requireAuth(): void
    {
        if (self::user() === null) {
            header('Location: /login.php', true, 302);
            exit;
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireAuth();
        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            exit('Forbidden');
        }
    }

    public static function loginAllowed(string $key): bool
    {
        self::startSession();
        $now = time();
        $attempts = array_values(array_filter((array)($_SESSION['_login_attempts'][$key] ?? []), static fn($time): bool => is_int($time) && $time > $now - self::LOGIN_WINDOW));
        $_SESSION['_login_attempts'][$key] = $attempts;
        return count($attempts) < self::MAX_LOGIN_ATTEMPTS;
    }

    public static function recordLoginFailure(string $key): void
    {
        self::startSession();
        $_SESSION['_login_attempts'][$key][] = time();
    }

    private static function enforceLifetime(): void
    {
        if (!isset($_SESSION['admin_user'])) {
            return;
        }
        $now = time();
        $last = (int)($_SESSION['last_activity'] ?? $now);
        $login = (int)($_SESSION['login_at'] ?? $now);
        if ($now - $last > self::IDLE_TIMEOUT || $now - $login > self::ABSOLUTE_TIMEOUT) {
            self::logout();
            header('Location: /login.php?expired=1', true, 302);
            exit;
        }
        $_SESSION['last_activity'] = $now;
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}
