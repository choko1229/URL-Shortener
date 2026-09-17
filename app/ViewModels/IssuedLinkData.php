<?php

declare(strict_types=1);

namespace App\ViewModels;

use Carbon\CarbonImmutable;

/**
 * 発行直後に表示する結果。発行処理（未実装）がセッションへ一度だけ flash する想定。
 */
final readonly class IssuedLinkData
{
    public const SESSION_KEY = 'issued_link';

    public function __construct(
        public string $shortUrl,
        public string $displayUrl,
        public string $originalUrl,
        public ?CarbonImmutable $expiresAt,
        public bool $isPasswordProtected,
        // 未ログイン発行時のみ。平文を表示できるのはこの画面の一度きり
        public ?string $deletionToken,
        // QRコード（endroid/qr-code で生成した SVG の data URI）。未生成なら null
        public ?string $qrCodeDataUri,
    ) {}
}
