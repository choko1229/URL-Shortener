<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Enums\IconName;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;

/**
 * 装飾用アイコン。意味を持たせる場合は隣にテキストを置くか、親要素に aria-label を付ける。
 *
 * 使い方: <x-icon name="copy" :size="18" class="text-text-secondary" />
 */
final class Icon extends Component
{
    public readonly ?IconName $icon;

    public function __construct(
        string $name,
        public readonly int $size = 20,
        public readonly float $strokeWidth = 2,
    ) {
        $this->icon = IconName::tryFrom($name);

        if ($this->icon === null) {
            Log::warning('未定義のアイコン名が指定されました。', ['name' => $name]);
        }
    }

    public function shouldRender(): bool
    {
        return $this->icon !== null;
    }

    public function render(): View
    {
        return view('components.icon');
    }
}
