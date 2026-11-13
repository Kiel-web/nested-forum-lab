<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    /** @return array{id:int, username:string, email:string}|null */
    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['user_id'] ?? null;
            if (is_int($id)) {
                self::$user = (new User())->findById($id);
                if (self::$user === null) {          // account vanished: drop the session login
                    unset($_SESSION['user_id']);
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u === null ? null : (int) $u['id'];
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);     // new session ID after login (prevents session fixation)
        Csrf::rotate();
        $_SESSION['user_id'] = $userId;
        self::$loaded = false;
        self::$user = null;
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
        Csrf::rotate();
        self::$loaded = false;
        self::$user = null;
    }
}
