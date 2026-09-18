<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Update\Updater;
use Illuminate\Console\Command;

/** 自動アップデート（requirements.md 7 章）。cron から 1 日 1 回実行する（routes/console.php） */
final class UpdateCommand extends Command
{
    protected $signature = 'app:update
        {--check : 最新リリースの確認のみ行う}
        {--manual : 自動アップデートが無効に設定されていても実行する}';

    protected $description = 'GitHub Releases を確認し、新しいリリースがあればバックアップを取ってから更新します';

    public function handle(Updater $updater): int
    {
        if ($this->option('check')) {
            $result = $updater->check();
            $this->line($result->message());

            return $result->error === null ? self::SUCCESS : self::FAILURE;
        }

        $outcome = $updater->run((bool) $this->option('manual'));

        $outcome->isFailure()
            ? $this->components->error($outcome->message)
            : $this->components->info($outcome->message);

        return $outcome->isFailure() ? self::FAILURE : self::SUCCESS;
    }
}
