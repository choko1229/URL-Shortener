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

    protected $description = '1日1回の定期処理（自動アップデートの確認）を、実行時刻を過ぎていれば実行します';

    public function handle(PeriodicTasks $tasks): int
    {
        $now = CarbonImmutable::now();
        $tasks->recordCronHeartbeat($now);

        $outcome = $tasks->runDue('cron', $now);

        if ($outcome !== null) {
            $outcome->isFailure()
                ? $this->components->error($outcome->message)
                : $this->components->info($outcome->message);
        }

        return $outcome?->isFailure() ? self::FAILURE : self::SUCCESS;
    }
}
