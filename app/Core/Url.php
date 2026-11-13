<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Builds links that still work when the app lives in a sub-folder
 * (e.g. http://localhost/forum-refactor/public/ under XAMPP/Apache).
 */
final class Url
{
    public static function base(): string
    {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        return rtrim($dir, '/');
    }

    public static function to(string $path): string
    {
        return self::base() . $path;
    }

    public static function asset(string $file): string
    {
        return self::base() . '/assets/' . $file;
    }

    /** 303 See Other: browser follows with GET (Post/Redirect/Get). */
    public static function redirect(string $path, int $status = 303): never
    {
        header('Location: ' . self::to($path), true, $status);
        exit;
    }
}
