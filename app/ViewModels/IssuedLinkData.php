<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Models\ShortUrl;
use App\Support\ShortUrlBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;

/**
 * 発行直後に表示する結果。発行処理がセッションへ一度だけ flash する。
 */
final readonly class IssuedLinkData
{
    public const SESSION_KEY = 'issued_link';

    public function __construct(
        public string $shortUrl,
        public string $displayUrl,
        // QR コードのダウンロード URL の組み立てに使う短縮コード
        public string $code,
        public string $originalUrl,
        public ?CarbonImmutable $expiresAt,
        public bool $isPasswordProtected,
        // 未ログイン発行時のみ。平文を表示できるのはこの画面の一度きり
        public ?string $deletionToken,
        // QRコード（endroid/qr-code で生成した SVG の data URI）。生成できなければ null
        public ?string $qrCodeDataUri,
    ) {}

    public static function fromModel(ShortUrl $link, ?string $deletionToken, ShortUrlBuilder $urls, ?string $qrCodeDataUri): self
    {
        return new self(
            shortUrl: $urls->url($link->slug),
            displayUrl: $urls->display($link->slug),
            code: $link->slug,
            originalUrl: $link->original_url,
            expiresAt: $link->expires_at,
            isPasswordProtected: $link->isPasswordProtected(),
            deletionToken: $deletionToken,
            qrCodeDataUri: $qrCodeDataUri,
        );
    }

    /** flash された発行結果を取り出す。形式が不正なら破棄してログに残す */
    public static function fromSession(Session $session): ?self
    {
        $issued = $session->get(self::SESSION_KEY);

        if ($issued === null || $issued instanceof self) {
            return $issued;
        }

        Log::warning('セッション内の発行結果の形式が不正なため表示しませんでした。', [
            'type' => get_debug_type($issued),
        ]);

        return null;
    }
}
