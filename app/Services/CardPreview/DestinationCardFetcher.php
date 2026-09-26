<?php

declare(strict_types=1);

namespace App\Services\CardPreview;

use DOMDocument;
use DOMElement;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ValueError;

/**
 * 発行フォームの「X に貼ったときの見え方」用に、転送先ページのカード情報（OGP / Twitter カード）を取得する。
 *
 * 利用者が入力した URL へサーバーからアクセスするため、内部ネットワークへの到達（SSRF）を防ぐ:
 * - http / https の 80・443 番ポートのみ。認証情報つきの URL は扱わない
 * - 名前解決した全アドレスが公開アドレスのときだけ接続し、接続先をそのアドレスに固定する（DNS の差し替え対策）
 * - リダイレクトは自前でたどり、移動先も同じ確認をする
 * - 読み込むのは先頭の一定量だけ。時間も短く区切る
 */
class DestinationCardFetcher
{
    private const TIMEOUT_SECONDS = 4;

    private const CONNECT_TIMEOUT_SECONDS = 3;

    private const MAX_BYTES = 512 * 1024;

    private const MAX_REDIRECTS = 3;

    private const CACHE_SECONDS = 600;

    // 取得できなかった結果も短時間は覚えておき、同じ URL へ何度もアクセスしない
    private const FAILURE_CACHE_SECONDS = 60;

    private const ALLOWED_PORTS = [80, 443];

    private const TITLE_MAX_LENGTH = 200;

    private const DESCRIPTION_MAX_LENGTH = 300;

    public function __construct(
        private readonly HostResolver $resolver,
    ) {}

    public function fetch(string $url): ?DestinationCard
    {
        $cacheKey = 'card-preview:'.hash('sha256', $url);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached === [] ? null : DestinationCard::fromArray($cached);
        }

        $card = $this->download($url);
        Cache::put($cacheKey, $card?->toArray() ?? [], $card !== null ? self::CACHE_SECONDS : self::FAILURE_CACHE_SECONDS);

        return $card;
    }

    private function download(string $url): ?DestinationCard
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = $this->publicTarget($url);

            if ($target === null) {
                return null;
            }

            try {
                $response = $this->request($url, $target);
            } catch (ConnectionException) {
                return null;
            }

            if ($response->redirect()) {
                $next = self::absoluteUrl($response->header('Location'), $url);
                $response->close();

                if ($next === null) {
                    return null;
                }

                $url = $next;

                continue;
            }

            if (! $response->successful()) {
                $response->close();

                return null;
            }

            $contentType = strtolower($response->header('Content-Type'));

            if ($contentType !== '' && ! str_contains($contentType, 'html')) {
                $response->close();

                return null;
            }

            return $this->parse($this->readHead($response), $url, $contentType);
        }

        return null;
    }

    /** @param  array{host: string, port: int, address: string|null}  $target */
    private function request(string $url, array $target): Response
    {
        $options = ['stream' => true];

        // 名前解決で確認したアドレスへ接続を固定する（確認後に DNS が差し替えられても内部へ行かない）
        if ($target['address'] !== null && defined('CURLOPT_RESOLVE')) {
            $address = str_contains($target['address'], ':') ? '['.$target['address'].']' : $target['address'];
            $options['curl'] = [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$address}"]];
        }

        return Http::withOptions($options)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; URLShortenerCardPreview/1.0)',
                'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.1',
                'Accept-Language' => 'ja,en;q=0.8',
            ])
            ->withoutRedirecting()
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::TIMEOUT_SECONDS)
            ->get($url);
    }

    /**
     * 接続してよい URL なら接続先を返す
     *
     * @return array{host: string, port: int, address: string|null}|null
     */
    private function publicTarget(string $url): ?array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (! in_array($port, self::ALLOWED_PORTS, true) || $this->isOwnHost($host)) {
            return null;
        }

        $isLiteral = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $addresses = $isLiteral ? [$host] : $this->resolver->resolve($host);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! self::isPublicAddress($address)) {
                Log::info('カードのプレビュー: 公開されていないアドレスのため取得しませんでした。', ['host' => $host]);

                return null;
            }
        }

        return ['host' => $host, 'port' => $port, 'address' => $isLiteral ? null : $addresses[0]];
    }

    private function isOwnHost(string $host): bool
    {
        $ownHosts = array_map('strtolower', array_filter((array) config('shortener.domains'), 'is_string'));

        return in_array($host, $ownHosts, true);
    }

    private static function isPublicAddress(string $address): bool
    {
        // IPv4 射影アドレス（::ffff:127.0.0.1 など）は中の IPv4 で判定する
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $address, $matches) === 1) {
            $address = $matches[1];
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // キャリアグレード NAT（100.64.0.0/10）も内部扱いにする
        $long = ip2long($address);

        return $long === false || ($long & 0xFFC00000) !== (100 << 24 | 64 << 16);
    }

    /** 先頭の一定量だけ読む（カード情報は <head> にある） */
    private function readHead(Response $response): string
    {
        $body = $response->toPsrResponse()->getBody();
        $html = '';

        while (! $body->eof() && strlen($html) < self::MAX_BYTES) {
            $chunk = $body->read(8192);
            if ($chunk === '') {
                break;
            }
            $html .= $chunk;

            if (stripos($html, '</head>') !== false) {
                break;
            }
        }

        $body->close();

        return $html;
    }

    private function parse(string $html, string $url, string $contentType): ?DestinationCard
    {
        $head = stripos($html, '</head>');
        $html = $head !== false ? substr($html, 0, $head) : $html;
        $html = self::toUtf8($html, $contentType);

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return null;
        }

        $meta = [];
        foreach ($document->getElementsByTagName('meta') as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }
            $key = strtolower(trim($element->getAttribute('property') ?: $element->getAttribute('name')));
            $content = trim($element->getAttribute('content'));
            if ($key !== '' && $content !== '' && ! isset($meta[$key])) {
                $meta[$key] = $content;
            }
        }

        $titleElement = $document->getElementsByTagName('title')->item(0);

        $title = self::clean($meta['twitter:title'] ?? $meta['og:title'] ?? $titleElement?->textContent, self::TITLE_MAX_LENGTH);
        $description = self::clean($meta['twitter:description'] ?? $meta['og:description'] ?? $meta['description'] ?? null, self::DESCRIPTION_MAX_LENGTH);
        $image = self::absoluteUrl(
            $meta['twitter:image'] ?? $meta['twitter:image:src'] ?? $meta['og:image:secure_url'] ?? $meta['og:image'] ?? $meta['og:image:url'] ?? '',
            $url,
        );

        if ($title === null && $image === null) {
            return null;
        }

        return new DestinationCard(
            title: $title,
            description: $description,
            imageUrl: $image,
            largeImage: $image !== null && strtolower($meta['twitter:card'] ?? '') === 'summary_large_image',
        );
    }

    private static function toUtf8(string $html, string $contentType): string
    {
        $charset = null;

        if (preg_match('/charset=["\']?([\w-]+)/i', $contentType, $matches) === 1) {
            $charset = $matches[1];
        } elseif (preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', $html, $matches) === 1) {
            $charset = $matches[1];
        }

        if ($charset === null || in_array(strtolower($charset), ['utf-8', 'utf8'], true)) {
            return $html;
        }

        try {
            $converted = mb_convert_encoding($html, 'UTF-8', $charset);
        } catch (ValueError) {
            return $html;
        }

        return is_string($converted) ? $converted : $html;
    }

    private static function clean(?string $text, int $maxLength): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : mb_substr($text, 0, $maxLength);
    }

    /** 相対 URL を絶対 URL にする。http / https 以外は null */
    private static function absoluteUrl(string $reference, string $base): ?string
    {
        $reference = trim($reference);

        if ($reference === '') {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $reference) === 1) {
            return preg_match('#^https?://#i', $reference) === 1 ? $reference : null;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $origin = $scheme.'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = $parts['path'] ?? '/';

        return match (true) {
            str_starts_with($reference, '//') => $scheme.':'.$reference,
            str_starts_with($reference, '/') => $origin.$reference,
            str_starts_with($reference, '?') => $origin.$path.$reference,
            default => $origin.preg_replace('#/[^/]*$#', '/', $path).$reference,
        };
    }
}
