<?php

declare(strict_types=1);

namespace App\Services\Update;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * 更新後のコードで artisan コマンドを実行する（別プロセスにすることで、読み込み済みの古いコードを使わない）
 */
class ArtisanProcess
{
    private const TIMEOUT_SECONDS = 600;

    public function __construct(
        private readonly string $basePath,
        private readonly string $phpBinary,
    ) {}

    /**
     * @param  list<string>  $arguments
     *
     * @throws UpdateException
     */
    public function run(array $arguments): string
    {
        $result = Process::path($this->basePath)
            ->timeout(self::TIMEOUT_SECONDS)
            ->run([$this->phpBinary, 'artisan', ...$arguments, '--no-interaction']);

        if (! $result->successful()) {
            Log::error('artisan コマンドが失敗しました。', [
                'command' => $arguments[0] ?? '',
                'exit_code' => $result->exitCode(),
                'output' => mb_substr($result->output()."\n".$result->errorOutput(), 0, 4000),
            ]);

            throw new UpdateException('php artisan '.($arguments[0] ?? '').' が失敗しました。'.self::summary($result->output()));
        }

        return $result->output();
    }

    private static function summary(string $output): string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));

        return $lines === [] ? '' : '（'.mb_substr(implode(' / ', array_slice($lines, -3)), 0, 300).'）';
    }
}
