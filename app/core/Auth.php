<?php
/**
 * SHIFFIN - Authentication Manager
 */
class Auth {
    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(string $username, string $password): ?array {
        self::init();
        $db = Database::getInstance();
        $user = $db->fetch("SELECT * FROM users WHERE username = ? AND is_active = 1", [$username]);
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        return [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ];
    }

    public static function logout(): void {
        self::init();
        session_destroy();
    }

    public static function check(): bool {
        self::init();
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array {
        self::init();
        if (!self::check()) return null;
        return [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role']
        ];
    }

    public static function id(): ?int {
        self::init();
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string {
        self::init();
        return $_SESSION['role'] ?? null;
    }

    public static function requireAuth(): void {
        if (!self::check()) {
            Response::error('Unauthorized', 401);
            exit;
        }
    }

    public static function requireRole(array $roles): void {
        self::requireAuth();
        if (!in_array(self::role(), $roles)) {
            Response::error('Forbidden: insufficient permissions', 403);
            exit;
        }
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
