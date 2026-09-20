<?php

declare(strict_types=1);

namespace App\Services\Update;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Git で設置した環境の更新（requirements.md 7-1: git fetch → 新タグへ checkout → composer install --no-dev）。
 * ロールバック時は更新前のコミットへ checkout する。
 */
final class GitStrategy implements UpdateStrategy
{
    private const TIMEOUT_SECONDS = 600;

    public function __construct(
        private readonly string $basePath,
        private readonly string $gitBinary,
        private readonly string $composerBinary,
    ) {}

    public function name(): string
    {
        return 'git';
    }

    public function currentRevision(): ?string
    {
        $result = Process::path($this->basePath)->timeout(30)->run([$this->gitBinary, 'rev-parse', 'HEAD']);

        return $result->successful() ? trim($result->output()) : null;
    }

    public function apply(ReleaseInfo $release, ?string $token): void
    {
        // 非公開リポジトリの場合、HTTPS のリモートにはトークンをヘッダーで渡す（URL やログに残さない）
        $fetch = [$this->gitBinary];
        if ($token !== null) {
            $fetch[] = '-c';
            $fetch[] = 'http.extraHeader=Authorization: Basic '.base64_encode('x-access-token:'.$token);
        }

        $this->run([...$fetch, 'fetch', '--tags', '--force', 'origin'], 'リポジトリを取得できませんでした。');
        $this->run([$this->gitBinary, 'checkout', '--force', 'tags/'.$release->tag], "{$release->tag} に切り替えられませんでした。");
        $this->composerInstall();
    }

    public function rollback(Backup $backup): void
    {
        if ($backup->gitCommit === null) {
            throw new UpdateException('更新前のコミットが記録されていないため、コードを戻せません。');
        }

        $this->run([$this->gitBinary, 'checkout', '--force', $backup->gitCommit], '更新前のコミットに戻せませんでした。');
        $this->composerInstall();
    }

    private function composerInstall(): void
    {
        $this->run(
            [...preg_split('/\s+/', trim($this->composerBinary)) ?: ['composer'], 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'],
            'composer install に失敗しました。',
        );
    }

    /** @param  list<string>  $command */
    private function run(array $command, string $failureMessage): ProcessResult
    {
        $result = Process::path($this->basePath)->timeout(self::TIMEOUT_SECONDS)->run($command);

        if (! $result->successful()) {
            Log::error('更新コマンドが失敗しました。', [
                'command' => $command[0].' '.($command[array_key_last($command)] ?? ''),
                'exit_code' => $result->exitCode(),
                'error' => mb_substr($result->errorOutput(), 0, 2000),
            ]);

            throw new UpdateException($failureMessage);
        }

        return $result;
    }
}
