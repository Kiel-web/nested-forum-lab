<?php
declare(strict_types=1);

namespace App\Core;

use Mustache_Engine;
use Mustache_Loader_FilesystemLoader;

/**
 * Renders Mustache templates inside layout.mustache.
 * {{name}} output is HTML-escaped (quotes included). Comment text is only
 * ever printed with {{ }} - never with {{{ }}} or &.
 */
final class View
{
    private static ?Mustache_Engine $engine = null;

    public static function configure(string $viewsDir): void
    {
        $loader = new Mustache_Loader_FilesystemLoader($viewsDir, ['extension' => '.mustache']);
        self::$engine = new Mustache_Engine([
            'loader'          => $loader,
            'partials_loader' => $loader,
            'escape'          => static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ]);
    }

    /** Data every page needs (nav, CSRF token, links). */
    private static function common(): array
    {
        $user = Auth::user();
        return [
            'logged_in'    => $user !== null,
            'current_user' => $user['username'] ?? '',
            'csrf'         => Csrf::token(),
            'home_url'     => Url::to('/'),
            'login_url'    => Url::to('/login'),
            'register_url' => Url::to('/register'),
            'logout_url'   => Url::to('/logout'),
            'css_url'      => Url::asset('style.css'),
            'js_url'       => Url::asset('app.js'),
            'flash'        => Session::pullFlash(),
        ];
    }

    public static function render(string $template, array $data = [], int $status = 200): void
    {
        $data += self::common();
        $content = self::$engine->render($template, $data);   // already escaped HTML
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'");
        echo self::$engine->render('layout', $data + ['content' => $content]);
    }

    public static function error(int $status, string $message, array $extra = []): void
    {
        self::render('error', $extra + [
            'title'   => "Error $status",
            'status'  => $status,
            'message' => $message,
        ], $status);
    }
}
