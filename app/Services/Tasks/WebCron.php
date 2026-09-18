<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * アクセスをきっかけに定期処理を起動する（WordPress の WP-Cron と同じ方式）。
 * - PHP-FPM / LiteSpeed: 応答を返し終えた後、同じプロセスで実行する（訪問者を待たせない）
 * - それ以外: 自分自身へ非同期の HTTP リクエストを送り、そちらで実行する
 */
class WebCron
{
    private const LOOPBACK_TIMEOUT_SECONDS = 1;

    public function __construct(
        private readonly PeriodicTasks $tasks,
        private readonly Config $config,
    ) {}

    public function trigger(CarbonImmutable $now): void
    {
        if ($this->responseAlreadySent()) {
            ignore_user_abort(true);
            @set_time_limit(0);
            $this->tasks->runDue('web', $now);

            return;
        }

        $this->spawnLoopback();
    }

    /** 自分自身へのリクエストで使う合言葉（APP_KEY から作るため外部からは分からない） */
    public function token(): string
    {
        return hash_hmac('sha256', 'chok-ooo-web-cron', (string) $this->config->get('app.key'));
    }

    public function isValidToken(mixed $token): bool
    {
        return is_string($token) && hash_equals($this->token(), $token);
    }

    protected function responseAlreadySent(): bool
    {
        // Laravel はこれらが使える環境では、応答の送信後に終了処理（terminate）を呼ぶ
        return function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request');
    }

    private function spawnLoopback(): void
    {
        try {
            Http::timeout(self::LOOPBACK_TIMEOUT_SECONDS)
                ->connectTimeout(self::LOOPBACK_TIMEOUT_SECONDS)
                ->asForm()
                ->post(route('cron.run'), ['token' => $this->token()]);
        } catch (ConnectionException) {
            // 応答を待たずに切断するため、タイムアウトは想定どおり（相手側は ignore_user_abort で処理を続ける）
        } catch (\Throwable $e) {
            Log::warning('定期処理の起動に失敗しました。', ['exception' => $e::class]);
        }
    }
}
