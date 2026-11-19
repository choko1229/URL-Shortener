<?php

declare(strict_types=1);

namespace App\Services\ShortUrl\Transfer;

/**
 * 取り込もうとした短縮URLが、移行先ですでに使われている（取り込む前に利用者に扱いを選んでもらう）。
 * - own: 自分の有効な短縮URLが同じコードを使っている → 上書き・スキップ・新しいコードで発行
 * - taken: ほかの人の短縮URL・削除済み（欠番）・予約語・サイト内のページと重なる → スキップ・新しいコードで発行
 */
final readonly class LinkTransferConflict
{
    public const OWN = 'own';

    public const TAKEN = 'taken';

    public function __construct(
        public int $line,
        public string $slug,
        public string $kind,
        public string $reason,
        // 取り込もうとした転送先
        public string $importUrl,
        // 自分の短縮URLと重なった場合の、今の転送先
        public ?string $currentUrl = null,
    ) {}

    public function canOverwrite(): bool
    {
        return $this->kind === self::OWN;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'line' => $this->line,
            'slug' => $this->slug,
            'kind' => $this->kind,
            'reason' => $this->reason,
            'import_url' => $this->importUrl,
            'current_url' => $this->currentUrl,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['line'],
            (string) $data['slug'],
            (string) $data['kind'],
            (string) $data['reason'],
            (string) $data['import_url'],
            isset($data['current_url']) ? (string) $data['current_url'] : null,
        );
    }
}
