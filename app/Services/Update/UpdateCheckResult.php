<?php

declare(strict_types=1);

namespace App\Services\Update;

/** 最新リリースの確認結果（管理画面の「今すぐ確認」） */
final readonly class UpdateCheckResult
{
    public function __construct(
        public ?Version $current,
        public ?ReleaseInfo $latest,
        public ?string $error,
    ) {}

    public function hasUpdate(): bool
    {
        return $this->current !== null && $this->latest !== null && $this->latest->version->isNewerThan($this->current);
    }

    public function message(): string
    {
        return match (true) {
            $this->error !== null => $this->error,
            $this->latest === null => '最新リリースを確認できませんでした。',
            $this->current === null => "最新リリースは {$this->latest->tag} です（現在のバージョンを判定できないため自動更新は行いません）。",
            $this->hasUpdate() => "新しいリリース {$this->latest->tag} があります。次回の自動アップデートで更新されます。",
            default => "最新の状態です（{$this->current->toString()}）。",
        };
    }
}
