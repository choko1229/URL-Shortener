<?php

declare(strict_types=1);

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\LogoutController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Controllers\Main\DiscordLoginController;
use App\Http\Controllers\Main\HomeController;
use App\Http\Controllers\Redirect\RedirectController;
use App\Http\Controllers\Redirect\ShortLinkController;
use App\Http\Controllers\ShortUrlController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
| 4 つのサブドメインを単一アプリで扱う（requirements.md 1-1）。
| api サブドメインのルートは別途実装する。
*/

// 画面確認用プレビュー（local 環境のみ）。chok.ooo/{code} より先に登録する
if (app()->environment('local')) {
    require __DIR__.'/preview.php';
}

// 初期セットアップ（未インストール時のみ有効。ドメイン未確定でも使えるようドメインを限定しない）
Route::prefix('install')
    ->name('install.')
    ->controller(InstallController::class)
    ->group(static function (): void {
        Route::get('/', 'requirements')->name('requirements');
        Route::post('/', 'confirmRequirements')->name('requirements.confirm');
        Route::get('/database', 'database')->name('database');
        Route::post('/database', 'storeDatabase')->name('database.store');
        Route::get('/site', 'site')->name('site');
        Route::post('/site', 'storeSite')->name('site.store');
    });

// chok.ooo: トップページ・発行フォーム・短縮URLへのアクセス受付
Route::domain(config('shortener.domains.main'))
    ->name('main.')
    ->group(static function (): void {
        Route::get('/', HomeController::class)->name('home');
        Route::post('/shorten', [ShortUrlController::class, 'store'])->name('short-urls.store');
        Route::get('/login', DiscordLoginController::class)->middleware('guest')->name('login');

        // 固定のパスより後に登録する（固定パスと同じ語は予約語で発行できないようにしている）
        Route::get('/{code}', [ShortLinkController::class, 'show'])
            ->where('code', ShortLinkController::CODE_PATTERN)
            ->name('short-link.show');
        Route::post('/{code}/unlock', [ShortLinkController::class, 'unlock'])
            ->where('code', ShortLinkController::CODE_PATTERN)
            ->middleware('throttle:30,1')
            ->name('short-link.unlock');
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

// redirect.chok.ooo: 転送先の表示と悪意URLチェック（中間ページからの暗号化チケットで認証する）
Route::domain(config('shortener.domains.redirect'))
    ->name('redirect.')
    ->group(static function (): void {
        Route::get('/', [RedirectController::class, 'home'])->name('home');
        Route::post('/go', [RedirectController::class, 'go'])
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->middleware('throttle:60,1')
            ->name('go');
        Route::post('/check', [RedirectController::class, 'check'])
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->middleware('throttle:60,1')
            ->name('check');
    });
