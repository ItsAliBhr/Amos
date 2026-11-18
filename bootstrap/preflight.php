<?php

/**
 * AMOS preflight, executed by the front controller before Laravel boots.
 * - Verifies the PHP version (clear message instead of a fatal error on old hosts).
 * - On first run (no .env), creates .env from .env.example with a fresh random APP_KEY,
 *   so the web installer can run on hosts without SSH.
 */
(function (string $base) {
    if (version_compare(PHP_VERSION, '8.3.0', '<')) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><div dir="rtl" style="font-family:tahoma;padding:2rem">'
            .'<h2>نسخه PHP پشتیبانی نمی‌شود</h2><p>AMOS به PHP نسخه 8.3 یا بالاتر نیاز دارد. نسخه فعلی: '
            .htmlspecialchars(PHP_VERSION).'</p><p>از بخش «Select PHP Version» یا «MultiPHP Manager» در cPanel نسخه را تغییر دهید.</p></div>';
        exit;
    }

    $env = $base.'/.env';
    $example = $base.'/.env.example';
    if (! is_file($env) && is_file($example) && is_writable($base)) {
        $content = file_get_contents($example);
        $key = 'base64:'.base64_encode(random_bytes(32));
        $content = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $content);
        // Until SSL is configured the installer must work over plain HTTP; the installer later
        // sets these flags from the URL the operator enters.
        $https = (! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
        if (! $https) {
            $content = preg_replace('/^FORCE_HTTPS=.*$/m', 'FORCE_HTTPS=false', $content);
            $content = preg_replace('/^SESSION_SECURE_COOKIE=.*$/m', 'SESSION_SECURE_COOKIE=false', $content);
        }
        file_put_contents($env, $content, LOCK_EX);
        @chmod($env, 0600);
    } elseif (is_file($env) && is_writable($env)) {
        $content = file_get_contents($env);
        if (preg_match('/^APP_KEY=\s*$/m', $content)) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            file_put_contents($env, preg_replace('/^APP_KEY=\s*$/m', 'APP_KEY='.$key, $content), LOCK_EX);
        }
    }
})(dirname(__DIR__));
