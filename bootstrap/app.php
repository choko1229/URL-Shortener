<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureApplicationInstalled;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\TriggerPeriodicTasks;
use App\Http\Middleware\UpdateDatabaseSchema;
use App\Installer\InstallationState;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api.chok.ooo/v1/...（ドメインで分けるためパスの接頭辞は付けない）
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ドメイン設定が未確定の状態でもセットアップ画面へ誘導できるよう、ルーティングより前に判定する。
        // 更新後の最初のアクセスでは、画面を描く前にテーブルを最新にする
        $middleware->prepend([
            EnsureApplicationInstalled::class,
            UpdateDatabaseSchema::class,
        ]);
        // 応答を返した後に、1 日 1 回の定期処理を起動する（cron の登録を不要にする）
        $middleware->append(TriggerPeriodicTasks::class);

        $middleware->web(append: [
            SetSecurityHeaders::class,
        ]);
        $middleware->api(append: [
            SetSecurityHeaders::class,
        ]);

        $middleware->redirectGuestsTo(static fn (): string => route('auth.login'));
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

        // API（api.chok.ooo）のエラーは常に JSON で返す
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request): bool => $request->getHost() === config('shortener.domains.api') || $request->expectsJson(),
        );
    })->create();
