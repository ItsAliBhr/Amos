<?php

/*
 * AMOS front controller for the recommended cPanel layout:
 *
 *   /home/CPANEL_USER/amos_app/     <- full Laravel app (.env, vendor, storage, source) - NOT web accessible
 *   /home/CPANEL_USER/public_html/  <- only this file, .htaccess and public assets
 *
 * If you placed the application folder somewhere else, change AMOS_APP_PATH below.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
define('AMOS_APP_PATH', realpath(__DIR__.'/../amos_app') ?: __DIR__.'/../amos_app');

if (! is_file(AMOS_APP_PATH.'/bootstrap/app.php')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<div dir="rtl" style="font-family:tahoma;padding:2rem"><h2>پوشه برنامه AMOS پیدا نشد</h2>'
        .'<p>پوشه <code>amos_app</code> باید کنار <code>public_html</code> (در مسیر Home حساب cPanel) قرار گیرد، یا مقدار AMOS_APP_PATH را در public_html/index.php اصلاح کنید.</p></div>';
    exit;
}

require AMOS_APP_PATH.'/bootstrap/preflight.php';

if (file_exists($maintenance = AMOS_APP_PATH.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require AMOS_APP_PATH.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once AMOS_APP_PATH.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
