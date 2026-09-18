<?php

declare(strict_types=1);

namespace App\Services\Update;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * 更新後のコードで artisan コマンドを実行する。
 * 読み込み済みの古いコードを使わないよう別プロセスで実行し、PHP（CLI）が使えないサーバーでは同じプロセスで実行する。
 */
class ArtisanProcess
{
    private const TIMEOUT_SECONDS = 600;

    public function __construct(
        private readonly string $basePath,
        private readonly PhpBinaryResolver $php,
        private readonly Kernel $artisan,
    ) {}

    /**
     * @param  list<string>  $arguments  例: ['migrate', '--force']
     *
     * @throws UpdateException
     */
    public function run(array $arguments): string
    {
        $binary = $this->php->resolve();

        return $binary === null ? $this->runInProcess($arguments) : $this->runInSubprocess($binary, $arguments);
    }

    /** @param  list<string>  $arguments */
    private function runInSubprocess(string $binary, array $arguments): string
    {
        $result = Process::path($this->basePath)
            ->timeout(self::TIMEOUT_SECONDS)
            ->run([$binary, 'artisan', ...$arguments, '--no-interaction']);

        if (! $result->successful()) {
            $this->logFailure($arguments, $result->exitCode(), $result->output()."\n".$result->errorOutput());

            throw new UpdateException('php artisan '.($arguments[0] ?? '').' が失敗しました。'.self::summary($result->output()));
        }

        return $result->output();
    }

    /** @param  list<string>  $arguments */
    private function runInProcess(array $arguments): string
    {
        $command = (string) array_shift($arguments);
        $parameters = ['--no-interaction' => true];
        foreach ($arguments as $argument) {
            $parameters[$argument] = true;
        }

        try {
            $exitCode = $this->artisan->call($command, $parameters);
        } catch (Throwable $e) {
            $this->logFailure([$command], null, $e::class.': '.$e->getMessage());

            throw new UpdateException("php artisan {$command} が失敗しました。（{$e->getMessage()}）");
        }

        $output = $this->artisan->output();

        if ($exitCode !== 0) {
            $this->logFailure([$command], $exitCode, $output);

            throw new UpdateException("php artisan {$command} が失敗しました。".self::summary($output));
        }

        return $output;
    }

    /** @param  list<string>  $arguments */
    private function logFailure(array $arguments, ?int $exitCode, string $output): void
    {
        Log::error('artisan コマンドが失敗しました。', [
            'command' => $arguments[0] ?? '',
            'exit_code' => $exitCode,
            'output' => mb_substr($output, 0, 4000),
        ]);
    }

    private static function summary(string $output): string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));

        return $lines === [] ? '' : '（'.mb_substr(implode(' / ', array_slice($lines, -3)), 0, 300).'）';
    }
}
