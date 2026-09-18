<?php

declare(strict_types=1);

namespace App\Services\Update;

final readonly class ReleaseInfo
{
    public function __construct(
        public string $tag,
        public Version $version,
        public string $htmlUrl,
        // 配布用 zip（GitHub API のアセット URL）。添付されていなければ null
        public ?string $packageAssetUrl,
    ) {}
}
