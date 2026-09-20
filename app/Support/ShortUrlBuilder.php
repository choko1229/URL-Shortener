<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Config\Repository as Config;

/** 短縮コードから公開URL・表示用URLを組み立てる */
final class ShortUrlBuilder
{
    public function __construct(private readonly Config $config) {}

    /** 例: https://example.com/aB3xQ9k */
    public function url(string $slug): string
    {
        return rtrim((string) $this->config->get('shortener.short_url_base'), '/').'/'.rawurlencode($slug);
    }

    /** 例: example.com/aB3xQ9k（スキームを除いた表示用） */
    public function display(string $slug): string
    {
        return $this->host().'/'.$slug;
    }

    public function host(): string
    {
        $base = (string) $this->config->get('shortener.short_url_base');
        $host = parse_url($base, PHP_URL_HOST);
        $port = parse_url($base, PHP_URL_PORT);

        if (! is_string($host) || $host === '') {
            return (string) $this->config->get('shortener.domains.main');
        }

        return is_int($port) ? "{$host}:{$port}" : $host;
    }
}
