<?php

declare(strict_types=1);

use App\Http\Controllers\Preview\PreviewController;
use Illuminate\Support\Facades\Route;

/*
| 【local 環境専用】routes/web.php から APP_ENV=local の場合のみ読み込む。
| 認証・発行処理の実装後に削除する想定。
*/

Route::domain(config('shortener.domains.main'))
    ->name('preview.')
    ->group(static function (): void {
        Route::get('/_preview', [PreviewController::class, 'home'])->name('home');
    });

Route::domain(config('shortener.domains.dashboard'))
    ->name('preview.')
    ->group(static function (): void {
        Route::get('/_preview', [PreviewController::class, 'dashboard'])->name('dashboard');
        Route::get('/_preview/admin', [PreviewController::class, 'adminDashboard'])->name('dashboard.admin');
    });
