<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// The installer runs before Laravel so a fresh deployment does not need a
// manually prepared .env file (or APP_KEY) to render the setup wizard.
$installationLock = __DIR__.'/../storage/app/installed.lock';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$basePath = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');
$installPath = $basePath.'/install';

if (is_file($installationLock) && ($requestPath === $installPath || str_starts_with($requestPath, $installPath.'/'))) {
    http_response_code(410);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Already installed</title><body><h1>Application already installed</h1><p>The installer is locked. Sign in to manage the application.</p></body></html>';
    exit;
}

if (! is_file($installationLock)) {
    if ($requestPath !== $installPath && ! str_starts_with($requestPath, $installPath.'/')) {
        header('Location: '.$installPath, true, 302);
        exit;
    }

    require __DIR__.'/../installer/installer.php';
    exit;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
