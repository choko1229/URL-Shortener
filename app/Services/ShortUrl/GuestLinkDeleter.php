<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use Illuminate\Support\Facades\Log;

/**
 * 未ログインで発行した短縮URLの、削除用シークレットトークンによる削除（requirements.md 2-4）。
 * 論理削除のためコードは欠番として残る。
 */
final class GuestLinkDeleter
{
    public function __construct(private readonly ShortUrlResolver $resolver) {}

    /** 短縮URL（またはコード）とトークンが一致すれば削除して true */
    public function delete(string $shortUrlOrCode, string $token): bool
    {
        $code = self::extractCode($shortUrlOrCode);
        $link = $code !== null ? $this->resolver->find($code) : null;

        if ($link === null || $link->trashed() || $link->deletion_token_hash === null
            || ! hash_equals($link->deletion_token_hash, hash('sha256', $token))) {
            Log::notice('削除用トークンによる削除に失敗しました。');

            return false;
        }

        $link->delete();

        Log::info('削除用トークンにより短縮URLを削除しました。', ['short_url_id' => $link->id]);

        return true;
    }

    /** 「https://example.com/abc1234」「example.com/abc1234」「abc1234」のいずれからもコードを取り出す */
    public static function extractCode(string $input): ?string
    {
        $input = trim($input);
        $path = str_contains($input, '/') ? (string) parse_url(str_contains($input, '://') ? $input : 'https://'.$input, PHP_URL_PATH) : $input;
        $code = trim($path, '/');

        return preg_match('/\A[A-Za-z0-9_\-]{3,20}\z/', $code) === 1 ? $code : null;
    }
}
