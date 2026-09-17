<?php

use App\Installer\Preflight;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// 依存パッケージが無い = リポジトリのソースをそのまま設置した場合
if (! file_exists(__DIR__.'/../vendor/autoload.php')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "vendor フォルダが見つかりません。配布用 zip（リリース版）を設置するか、composer install を実行してください。\n";
    exit;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// 未インストール時は Laravel の起動前に .env の生成と書き込み権限を確認する（Web インストーラ）
$preflight = new Preflight(dirname(__DIR__));

if (! $preflight->isInstalled()) {
    $problems = $preflight->prepare(Preflight::isSecureRequest($_SERVER));

    if ($problems !== []) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        echo Preflight::renderFailurePage($problems);
        exit;
    }
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
