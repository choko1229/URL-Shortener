<?php

declare(strict_types=1);

namespace App\Services\Update;

/** 新しいリリースのコードを配置する方法 */
interface UpdateStrategy
{
    public function name(): string;

    /** 現在のコードの識別子（Git ならコミット）。ロールバックに使う */
    public function currentRevision(): ?string;

    /** @throws UpdateException */
    public function apply(ReleaseInfo $release, string $token): void;

    /** @throws UpdateException */
    public function rollback(Backup $backup): void;
}
