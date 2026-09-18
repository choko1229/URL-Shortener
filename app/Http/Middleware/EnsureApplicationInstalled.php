<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Installer\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 未インストールなら全リクエストをセットアップ画面へ、インストール済みならセットアップ画面を 404 にする。
 * ドメイン設定が未確定でも動くよう、ルーティングより前（グローバルミドルウェア）で判定する。
 */
final class EnsureApplicationInstalled
{
    public function __construct(private readonly InstallationState $state) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $isInstallerRequest = $request->is('install', 'install/*');

        if ($this->state->isInstalled()) {
            abort_if($isInstallerRequest, Response::HTTP_NOT_FOUND);

            return $next($request);
        }

        if ($isInstallerRequest || $request->is('up')) {
            return $next($request);
        }

        return redirect()->route('install.show');
    }
}
