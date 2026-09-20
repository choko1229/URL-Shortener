<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tasks\PeriodicTasks;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * cron（schedule:run）から毎分呼ばれ、実行時刻を過ぎた定期処理を行う。
 * cron が動いている間は、アクセスをきっかけにした実行（WebCron）を止める。
 */
final class PeriodicTasksCommand extends Command
{
    protected $signature = 'app:periodic-tasks';

    protected $description = '定期処理（自動アップデートの確認・国判定のデータベースの更新）を、実行時刻を過ぎていれば実行します';

    public function handle(PeriodicTasks $tasks): int
    {
        $now = CarbonImmutable::now();
        $tasks->recordCronHeartbeat($now);

        $report = $tasks->runDue('cron', $now);

        foreach ($report?->messages() ?? [] as $result) {
            $result['failed']
                ? $this->components->error($result['message'])
                : $this->components->info($result['message']);
        }

        return $report?->hasFailure() ? self::FAILURE : self::SUCCESS;
    }
}
