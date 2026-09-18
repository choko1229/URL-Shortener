<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

/** ランダムな短縮コード（英数字）を生成する */
class SlugGenerator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    public function generate(int $length): string
    {
        $maxIndex = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, $maxIndex)];
        }

        return $code;
    }
}
