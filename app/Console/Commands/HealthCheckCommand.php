<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Update\HealthChecker;
use Illuminate\Console\Command;

/** 更新後のヘルスチェック（requirements.md 7-1）。1 つでも失敗すれば終了コード 1 */
final class HealthCheckCommand extends Command
{
    protected $signature = 'app:health-check';

    protected $description = 'データベース接続と短縮URLの発行が正常に動くか確認します';

    public function handle(HealthChecker $checker): int
    {
        $failed = false;

        foreach ($checker->run() as $name => $error) {
            if ($error === null) {
                $this->components->twoColumnDetail($name, '<fg=green>OK</>');

                continue;
            }

            $failed = true;
            $this->components->twoColumnDetail($name, '<fg=red>NG</>');
            $this->line('  '.$error);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
