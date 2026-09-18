<?php

declare(strict_types=1);

namespace App\Services\Update;

use App\Enums\ExpiryOption;
use App\Services\ShortUrl\ShortUrlDraft;
use App\Services\ShortUrl\ShortUrlIssuer;
use App\Services\ShortUrl\ShortUrlResolver;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 更新後のヘルスチェック（requirements.md 7-1: DB 接続確認と、仮の短縮URLを発行する動作確認）。
 * 発行した仮の短縮URLはトランザクションを巻き戻して残さない。
 */
final class HealthChecker
{
    public function __construct(
        private readonly ShortUrlIssuer $issuer,
        private readonly ShortUrlResolver $resolver,
    ) {}

    /** @return array<string, string|null> 確認項目 => 失敗理由（成功なら null） */
    public function run(): array
    {
        return [
            'データベース接続' => $this->attempt(static function (): void {
                DB::select('select 1');
            }),
            '短縮URLの発行' => $this->attempt(function (): void {
                DB::beginTransaction();

                try {
                    $issued = $this->issuer->issue(
                        new ShortUrlDraft('https://example.com/chok-ooo-health-check', null, ExpiryOption::OneDay, null, null),
                        null,
                        '127.0.0.1',
                        enforceLimits: false,
                    );

                    if ($this->resolver->find($issued->shortUrl->slug)?->id !== $issued->shortUrl->id) {
                        throw new UpdateException('発行した短縮URLを検索できませんでした。');
                    }
                } finally {
                    DB::rollBack();
                }
            }),
            'ビルド済みアセット' => $this->attempt(static function (): void {
                if (! is_file(public_path('build/manifest.json'))) {
                    throw new UpdateException('public/build/manifest.json が見つかりません。');
                }
            }),
        ];
    }

    private function attempt(callable $check): ?string
    {
        try {
            $check();

            return null;
        } catch (Throwable $e) {
            return $e::class.': '.$e->getMessage();
        }
    }
}
