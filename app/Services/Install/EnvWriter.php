<?php

namespace App\Services\Install;

class EnvWriter
{
    public static function set(string $path, array $values): void
    {
        $content = is_file($path) ? file_get_contents($path) : '';
        foreach ($values as $key => $value) {
            $line = $key.'='.self::quote($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            if (preg_match($pattern, $content)) {
                $content = preg_replace_callback($pattern, fn () => $line, $content);
            } else {
                $content = rtrim($content)."\n".$line."\n";
            }
        }
        file_put_contents($path, $content, LOCK_EX);
        @chmod($path, 0600);
    }

    private static function quote(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        $value = (string) $value;
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/\-]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$', "\n"], ['\\\\', '\\"', '\\$', ''], $value).'"';
    }
}
