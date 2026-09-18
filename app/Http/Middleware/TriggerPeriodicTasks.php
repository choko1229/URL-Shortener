<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Installer\InstallationState;
use App\Services\Tasks\PeriodicTasks;
use App\Services\Tasks\WebCron;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * 応答を返した後に、定期処理の実行時刻を過ぎていないか確認する（cron の登録を不要にするため）。
 * 確認は数分に 1 回だけ行い、通常のアクセスには負荷をかけない。
 */
final class TriggerPeriodicTasks
{
    private const CHECK_INTERVAL_SECONDS = 300;

    /** 定期処理のクラスは依存が多いため、確認が必要になったときだけ生成する */
    public function __construct(
        private readonly InstallationState $installation,
        private readonly Container $container,
        private readonly Config $config,
    ) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->config->get('shortener.web_cron')
            || ! $this->installation->isInstalled()
            || $request->is('_cron', 'install', 'install/*', 'up')) {
            return;
        }

        try {
            if (! Cache::add('chok-ooo:periodic-tasks-checked', true, self::CHECK_INTERVAL_SECONDS)) {
                return;
            }

            $tasks = $this->container->make(PeriodicTasks::class);
            $now = CarbonImmutable::now();

            if ($tasks->cronIsActive($now) || ! $tasks->isDue($now)) {
                return;
            }

            $this->container->make(WebCron::class)->trigger($now);
        } catch (Throwable $e) {
            Log::warning('定期処理の確認に失敗しました。', ['exception' => $e::class, 'error' => $e->getMessage()]);
        }
    }
}
