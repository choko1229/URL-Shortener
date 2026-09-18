<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Models\ShortUrl;

final readonly class IssuedShortUrl
{
    public function __construct(
        public ShortUrl $shortUrl,
        // 未ログイン発行時のみ。平文はここでしか得られない（DB にはハッシュのみ保存）
        public ?string $deletionToken,
    ) {}
}
