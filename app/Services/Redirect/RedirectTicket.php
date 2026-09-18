<?php

declare(strict_types=1);

namespace App\Services\Redirect;

/**
 * 中間ページ（chok.ooo）から redirect サブドメインへ渡す情報。
 * 暗号化して受け渡すため改ざんできない。サブドメイン間でセッションを共有しなくても動く。
 */
final readonly class RedirectTicket
{
    public function __construct(
        public int $shortUrlId,
        public int $issuedAt,
        // アクセス元（chok.ooo/{code} を開いたときのリファラ）のホスト名
        public ?string $referrerHost,
        public bool $passwordVerified,
        // アクセス記録を 1 チケットにつき 1 回にするための識別子
        public string $nonce,
    ) {}
}
