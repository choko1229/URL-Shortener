<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Models\SitePage;
use App\Support\SiteIdentity;
use RuntimeException;

/**
 * 利用規約・プライバシーポリシーのひな形。
 * 実装している処理に合わせた文面を用意し、サイト名などを差し込んで管理画面の編集欄に読み込む。
 * そのまま使えることを保証するものではないため、内容は設置した人が確認・修正して使う。
 */
final class LegalTemplates
{
    private const DIRECTORY = 'templates/legal';

    public function __construct(private readonly SiteIdentity $site) {}

    public static function exists(string $slug): bool
    {
        return array_key_exists($slug, SitePage::AVAILABLE);
    }

    /** @return array{title: string, body: string} */
    public function for(string $slug): array
    {
        if (! self::exists($slug)) {
            throw new RuntimeException("ひな形のないページです: {$slug}");
        }

        $path = resource_path(self::DIRECTORY."/{$slug}.md");
        $body = is_file($path) ? (string) file_get_contents($path) : '';

        return [
            'title' => SitePage::AVAILABLE[$slug],
            'body' => strtr($body, [
                ':site' => $this->site->name(),
                ':operator' => $this->site->operator(),
                ':privacy_url' => route('main.privacy'),
                ':terms_url' => route('main.terms'),
                ':contact_url' => route('main.contact'),
            ]),
        ];
    }
}
