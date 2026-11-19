<?php

declare(strict_types=1);

namespace App\Services\ShortUrl\Transfer;

use RuntimeException;

/** 1 行を取り込めなかった理由。メッセージはそのまま利用者に表示する */
final class LinkTransferException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $column = null)
    {
        parent::__construct($message);
    }
}
