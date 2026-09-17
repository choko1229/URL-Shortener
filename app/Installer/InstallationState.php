<?php

declare(strict_types=1);

namespace App\Installer;

use JsonException;
use RuntimeException;

/**
 * インストール済みかどうかの記録。
 * DB に障害が起きてもセットアップ画面が再び開かないよう、DB ではなくファイルで管理する。
 */
final class InstallationState
{
    /** storage/ からの相対パス（storage/app/private は .gitignore 済みで Web 非公開） */
    public const LOCK_FILE = 'app/private/installed.json';

    public function __construct(private readonly string $lockFilePath) {}

    public function isInstalled(): bool
    {
        return is_file($this->lockFilePath);
    }

    /** @throws RuntimeException 記録ファイルを書き込めない場合 */
    public function markInstalled(): void
    {
        $directory = dirname($this->lockFilePath);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("{$directory} を作成できません。");
        }

        try {
            $json = json_encode([
                'installed_at' => gmdate(DATE_ATOM),
                'php_version' => PHP_VERSION,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            throw new RuntimeException('インストール記録を作成できません。', previous: $e);
        }

        if (@file_put_contents($this->lockFilePath, $json, LOCK_EX) === false) {
            throw new RuntimeException("{$this->lockFilePath} に書き込めません。");
        }
    }
}
