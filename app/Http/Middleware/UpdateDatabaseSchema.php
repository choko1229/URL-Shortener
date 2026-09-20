<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Installer\InstallationState;
use App\Installer\SchemaUpdater;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 更新後の最初のアクセスで、未適用のマイグレーションを適用する。
 * 画面を描く前に済ませる必要があるため、応答後ではなくリクエストの入口で確認する。
 */
final class UpdateDatabaseSchema
{
    public function __construct(
        private readonly InstallationState $installation,
        private readonly Container $container,
    ) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        // セットアップ中はインストーラ自身がテーブルを作成する
        if ($this->installation->isInstalled()) {
            $this->container->make(SchemaUpdater::class)->updateIfNeeded();
        }

        return $next($request);
    }
}
