<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ShortUrl;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * 短縮コードから公開URL・表示用URLを組み立てる。
 * $domain は short_urls.domain の値（空文字 = メインドメイン、それ以外は追加ドメインのホスト名）
 */
final class ShortUrlBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly ShortDomains $domains,
    ) {}

    /** 例: https://example.com/aB3xQ9k、https://go.example.com/sale */
    public function url(string $slug, string $domain = ShortDomains::PRIMARY): string
    {
        $base = $domain === ShortDomains::PRIMARY
            ? rtrim((string) $this->config->get('shortener.short_url_base'), '/')
            : $this->domains->scheme().'://'.$domain;

        return $base.'/'.rawurlencode($slug);
    }

    /** 例: example.com/aB3xQ9k（スキームを除いた表示用） */
    public function display(string $slug, string $domain = ShortDomains::PRIMARY): string
    {
        return $this->host($domain).'/'.$slug;
    }

    public function host(string $domain = ShortDomains::PRIMARY): string
    {
        return $this->domains->hostFor($domain);
    }

    public function urlFor(ShortUrl $link): string
    {
        return $this->url($link->slug, (string) $link->domain);
    }

    public function displayFor(ShortUrl $link): string
    {
        return $this->display($link->slug, (string) $link->domain);
    }

    /**
     * QR コードのダウンロード URL（メインドメインで配信し、追加ドメインのリンクは domain を付ける）
     *
     * @param  'svg'|'png'  $format
     */
    public function qrUrl(string $slug, string $domain, string $format): string
    {
        $parameters = ['code' => $slug, 'format' => $format];
        if ($domain !== ShortDomains::PRIMARY) {
            $parameters['domain'] = $domain;
        }

        return route('main.short-link.qr', $parameters);
    }
}
