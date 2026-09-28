<?php
// AA TRADERS - Authentication & Role-Based Access Control

if (session_status() === PHP_SESSION_NONE) {
    if (getenv('VERCEL') || isset($_ENV['VERCEL'])) {
        $tmpDir = sys_get_temp_dir();
        if (is_dir($tmpDir) && is_writable($tmpDir)) {
            @session_save_path($tmpDir);
        }
    }
    @session_start();
}

require_once __DIR__ . '/../config/database.php';

class Auth {
    public static function check(): bool {
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function user(): ?array {
        if (!self::check()) return null;
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'name' => $_SESSION['full_name'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role'],
        ];
    }

    public static function hasRole(array|string $roles): bool {
        if (!self::check()) return false;
        $userRole = $_SESSION['role'] ?? '';
        if ($userRole === 'super_admin') return true; // Super admin bypass

        if (is_array($roles)) {
            return in_array($userRole, $roles);
        }
        return $userRole === $roles;
    }

    public static function requireRole(array|string $roles): void {
        self::requireLogin();
        if (!self::hasRole($roles)) {
            http_response_code(403);
            die("Access Denied: You do not have sufficient permissions to view this module.");
        }
    }

    public static function login(string $username, string $password): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            Database::logAudit('LOGIN', 'Auth', (string)$user['id'], "User {$user['username']} logged in successfully.");
            return true;
        }
        return false;
    }

    public static function switchDemoUser(string $username): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            Database::logAudit('SWITCH_ROLE', 'Auth', (string)$user['id'], "Switched to demo role: {$user['role']} ({$user['username']})");
            return true;
        }
        return false;
    }

    public static function logout(): void {
        if (self::check()) {
            Database::logAudit('LOGOUT', 'Auth', (string)$_SESSION['user_id'], "User logged out.");
        }
        $_SESSION = [];
        session_destroy();
    }
}
