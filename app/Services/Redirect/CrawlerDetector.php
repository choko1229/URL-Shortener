<?php

declare(strict_types=1);

namespace App\Services\Redirect;

/**
 * SNS などがカード（OGP）を作るために取得しにきたかを判定する。
 * 判定は User-Agent のみで行い、人が開いた場合との違いはカードの内容だけ（転送先は同じ）。
 */
final class CrawlerDetector
{
    /** @var list<string> 代表的なカード生成・クロール用の User-Agent（小文字で部分一致） */
    private const SIGNATURES = [
        'discordbot',
        'twitterbot',
        'facebookexternalhit',
        'facebookcatalog',
        'slackbot',
        'telegrambot',
        'whatsapp',
        'line-podcast',
        'linespider',
        'skypeuripreview',
        'pinterest',
        'redditbot',
        'embedly',
        'iframely',
        'vkshare',
        'nuzzel',
        'qwantify',
        'bitlybot',
        'mastodon',
        'misskey',
        'bluesky',
        'googlebot',
        'bingbot',
        'applebot',
        'yahoo! slurp',
        'duckduckbot',
        'baiduspider',
        'yandexbot',
        'ia_archiver',
        'developers.google.com/+/web/snippet',
    ];

    /** 上のリストに無いものを拾うための一般的な語 */
    private const GENERIC_SIGNATURES = [
        'bot/',
        'bot ',
        'crawler',
        'spider',
        'preview',
        'link-checker',
        'linkpreview',
    ];

    public function isCrawler(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            // User-Agent を送らないクライアントはブラウザではない可能性が高い
            return true;
        }

        $agent = mb_strtolower($userAgent);

        foreach (self::SIGNATURES as $signature) {
            if (str_contains($agent, $signature)) {
                return true;
            }
        }

        foreach (self::GENERIC_SIGNATURES as $signature) {
            if (str_contains($agent, $signature)) {
                return true;
            }
        }

        return false;
    }
}
