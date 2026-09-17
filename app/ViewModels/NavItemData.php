<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Enums\IconName;

final readonly class NavItemData
{
    public function __construct(
        public string $label,
        public IconName $icon,
        // 画面が未作成の場合は null（リンクにせず「準備中」と表示する）
        public ?string $url,
        public bool $isCurrent = false,
    ) {}

    public function isAvailable(): bool
    {
        return $this->url !== null;
    }
}
