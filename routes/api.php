<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\LinkController;
use App\Http\Controllers\Redirect\ShortLinkController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

/*
| api.chok.ooo: 管理者専用 API（requirements.md 5）。
| API キーで認証し、レート制限は設けない。セッション・CSRF は使わない（api ミドルウェアグループ）。
*/

Route::domain(config('shortener.domains.api'))
    ->prefix('v1')
    ->name('api.v1.')
    ->middleware(AuthenticateApiKey::class)
    ->group(static function (): void {
        Route::get('/links', [LinkController::class, 'index'])->name('links.index');
        Route::post('/links', [LinkController::class, 'store'])->name('links.store');
        Route::get('/links/{code}', [LinkController::class, 'show'])
            ->where('code', ShortLinkController::CODE_PATTERN)
            ->name('links.show');
        Route::delete('/links/{code}', [LinkController::class, 'destroy'])
            ->where('code', ShortLinkController::CODE_PATTERN)
            ->name('links.destroy');
    });
