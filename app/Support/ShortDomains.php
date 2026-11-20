<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ShortDomainMode;
use App\Models\ShortDomain;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * 短縮URLに使えるドメインの一覧。
 * 短縮URL（short_urls.domain）では、メインドメインを空文字、追加ドメインをホスト名で表す。
 */
final class ShortDomains
{
    /** メインドメインを表す値 */
    public const PRIMARY = '';

    public function __construct(private readonly Config $config) {}

    /** メインドメインのホスト名（ポートを含む表示用。例: example.com） */
    public function primaryHost(): string
    {
        $base = (string) $this->config->get('shortener.short_url_base');
        $host = parse_url($base, PHP_URL_HOST);
        $port = parse_url($base, PHP_URL_PORT);

        if (! is_string($host) || $host === '') {
            return (string) $this->config->get('shortener.domains.main');
        }

        return is_int($port) ? "{$host}:{$port}" : $host;
    }

    /** 短縮URLの組み立てに使うスキーム（メインドメインと同じにする） */
    public function scheme(): string
    {
        $scheme = parse_url((string) $this->config->get('shortener.short_url_base'), PHP_URL_SCHEME);

        return is_string($scheme) && $scheme !== '' ? $scheme : 'https';
    }

    /** 表示用のホスト名（メインドメインは primaryHost） */
    public function hostFor(string $domain): string
    {
        return $domain === self::PRIMARY ? $this->primaryHost() : $domain;
    }

    /** @return list<ShortDomain> 追加ドメイン（ホスト名の順） */
    public function extra(): array
    {
        try {
            return ShortDomain::query()->orderBy('host')->get()->all();
        } catch (QueryException $e) {
            // アップデート直後でテーブルがまだ無い場合など。メインドメインだけで動かし続ける
            Log::warning('short_domains を読み込めないため、メインドメインだけを使います。', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function find(string $host): ?ShortDomain
    {
        $host = self::normalize($host);

        foreach ($this->extra() as $domain) {
            if ($domain->host === $host) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * 発行フォームで選べるドメイン（値 => 表示名）。先頭はメインドメイン
     *
     * @return array<string, string>
     */
    public function forPage(): array
    {
        $options = [self::PRIMARY => $this->primaryHost()];

        foreach ($this->extra() as $domain) {
            if ($domain->mode === ShortDomainMode::Page) {
                $options[$domain->host] = $domain->host;
            }
        }

        return $options;
    }

    /**
     * API で選べるドメイン（値 => 表示名）。API のみのドメインも含む
     *
     * @return array<string, string>
     */
    public function forApi(): array
    {
        $options = [self::PRIMARY => $this->primaryHost()];

        foreach ($this->extra() as $domain) {
            $options[$domain->host] = $domain->host;
        }

        return $options;
    }

    /**
     * 入力されたホスト名を domain 列の値にする。メインドメインのホスト名・空欄はメインドメイン。
     * 選べないドメインなら null
     *
     * @param  array<string, string>  $allowed  forPage() か forApi()
     */
    public function resolveInput(?string $input, array $allowed): ?string
    {
        $host = self::normalize((string) $input);

        if ($host === '' || $host === self::normalize($this->primaryHost())) {
            return self::PRIMARY;
        }

        return array_key_exists($host, $allowed) ? $host : null;
    }

    public static function normalize(string $host): string
    {
        return strtolower(trim($host));
    }
}
