<?php

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function requireLogin(): int
    {
        self::startSession();

        if (empty($_SESSION['user_id'])) {
            header('Location: paginalogar.php');
            exit;
        }

        return (int) $_SESSION['user_id'];
    }

    public static function csrfToken(): string
    {
        self::startSession();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string
    {
        $token = htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    public static function validateCsrf(?string $token): bool
    {
        self::startSession();

        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
