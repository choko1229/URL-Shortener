<?php

declare(strict_types=1);

use App\Enums\QrFormat;
use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\LinkController as AdminLinkController;
use App\Http\Controllers\Admin\ReservedWordController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\DiscordAuthController;
use App\Http\Controllers\Auth\DiscordSetupController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\LinkController;
use App\Http\Controllers\Dashboard\LogoutController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Controllers\Main\ContactController;
use App\Http\Controllers\Main\GuestDeletionController;
use App\Http\Controllers\Main\HomeController;
use App\Http\Controllers\Main\LegalController;
use App\Http\Controllers\Main\QrCodeController;
use App\Http\Controllers\Redirect\RedirectController;
use App\Http\Controllers\Redirect\ShortLinkController;
use App\Http\Controllers\ShortUrlController;
use App\Http\Controllers\SiteIconController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
| 4 つのサブドメインを単一アプリで扱う（requirements.md 1-1）。
| api サブドメインのルートは routes/api.php。
*/

// 画面確認用プレビュー（local 環境のみ）。/{code} より先に登録する
if (app()->environment('local')) {
    require __DIR__.'/preview.php';
}

// サービスアイコン（どのサブドメインからも同じ画像を参照する。/{code} より先に登録する）
Route::get('/_icon', SiteIconController::class)->name('site-icon');

// 初期セットアップ（未インストール時のみ有効。ドメイン未確定でも使えるようドメインを限定しない）
Route::prefix('install')
    ->name('install.')
    ->controller(InstallController::class)
    ->group(static function (): void {
        Route::get('/', 'show')->name('show');
        Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
        Route::get('/ping', 'ping')->name('ping');
    });

// メインドメイン: トップページ・発行フォーム・短縮URLへのアクセス受付
Route::domain(config('shortener.domains.main'))
    ->name('main.')
    ->group(static function (): void {
        Route::get('/', HomeController::class)->name('home');
        Route::post('/shorten', [ShortUrlController::class, 'store'])->name('short-urls.store');

        Route::get('/terms', [LegalController::class, 'terms'])->name('terms');
        Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');

        Route::get('/contact', [ContactController::class, 'show'])->name('contact');
        Route::post('/contact', [ContactController::class, 'store'])
            ->middleware('throttle:5,10')
            ->name('contact.store');

        // 削除用トークンによる削除（未ログインで発行したもの）
        Route::get('/delete', [GuestDeletionController::class, 'show'])->name('delete');
        Route::post('/delete', [GuestDeletionController::class, 'destroy'])
            ->middleware('throttle:10,1')
            ->name('delete.destroy');

        // QR コード（SVG / PNG を選んでダウンロード。中身は短縮URLそのもののため誰でも取得できる）
        Route::get('/{code}/qr.{format}', QrCodeController::class)
            ->where('code', ShortLinkController::CODE_PATTERN)
            ->where('format', QrFormat::PATTERN)
            ->middleware('throttle:30,1')
            ->name('short-link.qr');

        // 固定のパスより後に登録する（固定パスと同じ語は予約語で発行できないようにしている）
        Route::get('/{code}', [ShortLinkController::class, 'show'])
            ->where('code', ShortLinkController::CODE_PATTERN)
            ->name('short-link.show');
        Route::post('/{code}/unlock', [ShortLinkController::class, 'unlock'])
            ->where('code', ShortLinkController::CODE_PATTERN)
            ->middleware('throttle:30,1')
            ->name('short-link.unlock');
    });

// ダッシュボードのドメイン: Discord ログイン（ログイン状態を使う画面と同じドメインで処理する）
Route::domain(config('shortener.domains.dashboard'))
    ->name('auth.')
    ->middleware('guest')
    ->group(static function (): void {
        Route::get('/login', [DiscordAuthController::class, 'redirect'])->name('login');
        Route::get('/login/callback', [DiscordAuthController::class, 'callback'])->name('callback');

        // 初回のみ: Discord の Client ID / Secret の設定（管理者が登録されると 404）
        Route::get('/login/setup', [DiscordSetupController::class, 'show'])->name('setup');
        Route::post('/login/setup', [DiscordSetupController::class, 'store'])->middleware('throttle:10,1')->name('setup.store');
    });

// ダッシュボードのドメイン: ダッシュボード（ログイン必須）
Route::domain(config('shortener.domains.dashboard'))
    ->name('dashboard.')
    ->middleware('auth')
    ->group(static function (): void {
        Route::get('/', DashboardController::class)->name('home');
        Route::post('/links', [ShortUrlController::class, 'store'])->name('links.store');
        Route::get('/links/{shortUrl}', [LinkController::class, 'show'])
            ->whereNumber('shortUrl')
            ->withTrashed()
            ->name('links.show');
        Route::patch('/links/{shortUrl}/slug', [LinkController::class, 'updateSlug'])
            ->whereNumber('shortUrl')
            ->name('links.slug');
        Route::patch('/links/{shortUrl}/preview', [LinkController::class, 'updatePreview'])
            ->whereNumber('shortUrl')
            ->name('links.preview');
        Route::delete('/links/{shortUrl}', [ShortUrlController::class, 'destroy'])
            ->whereNumber('shortUrl')
            ->name('links.destroy');

        Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
        Route::delete('/account', [SettingsController::class, 'destroyAccount'])->name('account.destroy');
        Route::post('/logout', LogoutController::class)->name('logout');

        // 管理者のみ（requirements.md 4-2）
        Route::prefix('admin')
            ->name('admin.')
            ->middleware('can:admin')
            ->group(static function (): void {
                Route::get('/links', [AdminLinkController::class, 'index'])->name('links');

                Route::get('/users', [UserController::class, 'index'])->name('users');
                Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->whereNumber('user')->name('users.role');

                Route::get('/reserved-words', [ReservedWordController::class, 'index'])->name('reserved-words');
                Route::post('/reserved-words', [ReservedWordController::class, 'store'])->name('reserved-words.store');
                Route::delete('/reserved-words/{reservedWord}', [ReservedWordController::class, 'destroy'])
                    ->whereNumber('reservedWord')
                    ->name('reserved-words.destroy');

                Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api-keys');
                Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
                Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'revoke'])->whereNumber('apiKey')->name('api-keys.revoke');

                Route::get('/site', [SiteController::class, 'index'])->name('site');
                Route::put('/site', [SiteController::class, 'updateIdentity'])->name('site.update');
                Route::put('/site/theme', [SiteController::class, 'updateTheme'])->name('site.theme');
                Route::post('/site/icon', [SiteController::class, 'updateIcon'])->name('site.icon');
                Route::put('/site/pages/{slug}', [SiteController::class, 'updatePage'])->name('site.pages.update');
                Route::post('/site/pages/{slug}/template', [SiteController::class, 'loadTemplate'])->name('site.pages.template');
                Route::delete('/site/pages/{slug}', [SiteController::class, 'destroyPage'])->name('site.pages.destroy');

                Route::get('/inquiries', [InquiryController::class, 'index'])->name('inquiries');
                Route::patch('/inquiries/{inquiry}', [InquiryController::class, 'updateStatus'])->whereNumber('inquiry')->name('inquiries.status');
                Route::put('/inquiries/contact', [InquiryController::class, 'updateContact'])->name('inquiries.contact');

                Route::get('/services', [ServiceController::class, 'index'])->name('services');
                Route::put('/services', [ServiceController::class, 'update'])->name('services.update');
                Route::post('/services/geoip', [ServiceController::class, 'updateGeoIp'])->middleware('throttle:5,1')->name('services.geoip');

                Route::get('/updates', [UpdateController::class, 'index'])->name('updates');
                Route::put('/updates/settings', [UpdateController::class, 'updateSettings'])->name('updates.settings');
                Route::post('/updates/check', [UpdateController::class, 'check'])->middleware('throttle:10,1')->name('updates.check');
                Route::post('/updates/run', [UpdateController::class, 'run'])->middleware('throttle:5,10')->name('updates.run');
                Route::post('/updates/test-notification', [UpdateController::class, 'testNotification'])
                    ->middleware('throttle:5,1')
                    ->name('updates.test-notification');
            });
    });

// リダイレクト確認のドメイン: 転送先の表示と悪意URLチェック（中間ページからの暗号化チケットで認証する）
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
