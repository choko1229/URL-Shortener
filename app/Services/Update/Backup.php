<?php

declare(strict_types=1);

namespace App\Services\Update;

/** 更新前に取得したバックアップ 1 世代分 */
final readonly class Backup
{
    public const DATABASE_FILE = 'database.sql';

    public const CODE_FILE = 'code.zip';

    public const META_FILE = 'meta.json';

    public function __construct(
        public string $path,
        public string $version,
        // Git で設置した環境の、更新前のコミット（ロールバック先）
        public ?string $gitCommit,
    ) {}

    public function databasePath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.self::DATABASE_FILE;
    }

    public function codePath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.self::CODE_FILE;
    }
}
