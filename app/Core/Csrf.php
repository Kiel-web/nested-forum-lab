<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session-backed CSRF token. Every state-changing form (register, login,
 * logout, reply) includes it as a hidden "_csrf" field; every POST handler
 * calls Csrf::verify() before doing anything.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verify(mixed $submitted): bool
    {
        return is_string($submitted)
            && $submitted !== ''
            && hash_equals(self::token(), $submitted);   // constant-time compare
    }

    public static function rotate(): void
    {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}
