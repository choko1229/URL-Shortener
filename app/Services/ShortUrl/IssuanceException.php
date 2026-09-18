<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use RuntimeException;

/** 短縮URLを発行できなかった理由。メッセージはそのまま利用者に表示する */
final class IssuanceException extends RuntimeException
{
    /**
     * @param  string|null  $field  入力項目に起因する場合の項目名（フォームのエラー表示に使う）
     */
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
