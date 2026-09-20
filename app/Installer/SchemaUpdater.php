<?php

declare(strict_types=1);

namespace App\Installer;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 未適用のマイグレーションがあれば適用する。
 * 配布物を上書きしただけでもテーブルが更新されるようにして、更新後の画面が壊れないようにする。
 */
final class SchemaUpdater
{
    private const MAX_EXECUTION_SECONDS = 300;

    /** 失敗した場合、この秒数が経つまでは再試行しない */
    private const RETRY_AFTER_SECONDS = 600;

    public function __construct(
        private readonly SchemaState $state,
        private readonly Kernel $artisan,
    ) {}

    /** 必要なときだけ `migrate --force` を実行する。失敗しても例外は投げず、記録して次の機会に再試行する */
    public function updateIfNeeded(): void
    {
        if ($this->state->isCurrent() || $this->state->failedRecently(self::RETRY_AFTER_SECONDS)) {
            return;
        }

        try {
            $this->state->withLock(function (): void {
                // ロック待ちの間に別のプロセスが更新し終えている場合がある
                if ($this->state->isCurrent()) {
                    return;
                }

                $this->migrate();
            });
        } catch (Throwable $e) {
            $this->markFailed($e);
        }
    }

    private function migrate(): void
    {
        @set_time_limit(self::MAX_EXECUTION_SECONDS);

        try {
            $exitCode = $this->artisan->call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            $this->markFailed($e);

            return;
        }

        if ($exitCode !== 0) {
            Log::error('データベースの更新に失敗しました。', ['exit_code' => $exitCode, 'output' => $this->artisan->output()]);
            $this->state->markFailed();

            return;
        }

        $this->state->markUpdated();

        Log::notice('データベースを最新の状態に更新しました。', ['output' => trim($this->artisan->output())]);
    }

    private function markFailed(Throwable $e): void
    {
        Log::error('データベースの更新に失敗しました。', ['exception' => $e::class, 'error' => $e->getMessage()]);

        try {
            $this->state->markFailed();
        } catch (Throwable $failure) {
            Log::error('データベース更新の記録を保存できませんでした。', ['error' => $failure->getMessage()]);
        }
    }
}
