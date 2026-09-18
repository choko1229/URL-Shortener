<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureApplicationInstalled;
use App\Http\Middleware\SetSecurityHeaders;
use App\Installer\InstallationState;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ドメイン設定が未確定の状態でもセットアップ画面へ誘導できるよう、ルーティングより前に判定する
        $middleware->prepend(EnsureApplicationInstalled::class);

        $middleware->web(append: [
            SetSecurityHeaders::class,
        ]);

        $middleware->redirectGuestsTo(static fn (): string => route('main.login'));
        $middleware->redirectUsersTo(static fn (): string => route('dashboard.home'));

        // Host ヘッダー偽装対策: 設定済みのサブドメインのみ受け付ける（local / テスト時は無効）。
        // セットアップ前はドメインが未確定のため制限しない
        $middleware->trustHosts(at: static function (): array {
            if (! app(InstallationState::class)->isInstalled()) {
                return [];
            }

            return array_map(
                static fn (string $domain): string => '^'.preg_quote($domain, '/').'$',
                array_values(array_filter(config('shortener.domains'), 'is_string')),
            );
        }, subdomains: false);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'password',
        ]);
    })->create();
