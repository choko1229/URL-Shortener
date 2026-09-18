<?php

declare(strict_types=1);

namespace App\Services\Update;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * 更新前のバックアップ（requirements.md 7-1, 7-3: DB ダンプとコード一式、直近 3 世代を保持）。
 * 保存先は storage/app/private/backups（Web から見えない場所）。
 */
final class BackupManager
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $backupPath,
        private readonly int $generations,
        private readonly DatabaseBackup $database,
        private readonly CodeTree $code,
    ) {}

    /** @throws UpdateException */
    public function create(string $version, ?string $gitCommit, CarbonImmutable $now): Backup
    {
        $directory = $this->backupPath.DIRECTORY_SEPARATOR.$now->format('Ymd_His').'_'.preg_replace('/[^A-Za-z0-9.\-]/', '', $version);
        File::ensureDirectoryExists($directory, 0700);

        $backup = new Backup($directory, $version, $gitCommit);

        try {
            $this->database->dump($backup->databasePath());
            $this->code->archive($this->basePath, $backup->codePath());
            File::put($directory.DIRECTORY_SEPARATOR.Backup::META_FILE, json_encode([
                'version' => $version,
                'git_commit' => $gitCommit,
                'created_at' => $now->toIso8601String(),
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } catch (UpdateException|JsonException $e) {
            File::deleteDirectory($directory);

            throw $e instanceof UpdateException ? $e : new UpdateException('バックアップの情報を保存できませんでした。');
        }

        Log::info('更新前のバックアップを作成しました。', ['path' => $directory]);

        $this->prune();

        return $backup;
    }

    /** 保持世代数を超えた古いバックアップを削除する */
    public function prune(): void
    {
        $directories = $this->list();

        foreach (array_slice($directories, $this->generations) as $old) {
            File::deleteDirectory($old);
            Log::info('古いバックアップを削除しました。', ['path' => $old]);
        }
    }

    /** @return list<string> 新しい順 */
    public function list(): array
    {
        if (! is_dir($this->backupPath)) {
            return [];
        }

        $directories = File::directories($this->backupPath);
        rsort($directories);

        return array_values($directories);
    }
}
