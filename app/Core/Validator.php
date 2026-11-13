<?php
declare(strict_types=1);

namespace App\Core;

/** Small input helpers so controllers never touch raw $_POST values blindly. */
final class Validator
{
    /** Return $_POST[$key] only when it is a plain string (arrays like a[]=x become ''). */
    public static function post(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    /** "12" -> 12; anything else (abc, 0, -3, 1.5, 99999999999) -> null. */
    public static function positiveInt(string $value): ?int
    {
        if (!ctype_digit($value) || strlen($value) > 10) {
            return null;
        }
        $n = (int) $value;
        return ($n >= 1 && $n <= 4294967295) ? $n : null;
    }

    public static function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }
}
