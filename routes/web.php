<?php

declare(strict_types=1);

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\LogoutController;
use App\Http\Controllers\Main\DiscordLoginController;
use App\Http\Controllers\Main\HomeController;
use App\Http\Controllers\ShortUrlController;
use Illuminate\Support\Facades\Route;

/*
| 4 つのサブドメインを単一アプリで扱う（requirements.md 1-1）。
| api / redirect サブドメインと、短縮コード（chok.ooo/{code}）のルートは別途実装する。
*/

// chok.ooo: トップページ・発行フォーム
Route::domain(config('shortener.domains.main'))
    ->name('main.')
    ->group(static function (): void {
        Route::get('/', HomeController::class)->name('home');
        Route::post('/shorten', [ShortUrlController::class, 'store'])->name('short-urls.store');
        Route::get('/login', DiscordLoginController::class)->middleware('guest')->name('login');
    });

// dash.chok.ooo: ダッシュボード（ログイン必須）
Route::domain(config('shortener.domains.dashboard'))
    ->name('dashboard.')
    ->middleware('auth')
    ->group(static function (): void {
        Route::get('/', DashboardController::class)->name('home');
        Route::post('/links', [ShortUrlController::class, 'store'])->name('links.store');
        Route::delete('/links/{shortUrl}', [ShortUrlController::class, 'destroy'])
            ->whereNumber('shortUrl')
            ->name('links.destroy');
        Route::post('/logout', LogoutController::class)->name('logout');
    });
