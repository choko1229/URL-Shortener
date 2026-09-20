<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * サイトの表示名・キャッチコピー・運営者名。
 * 設置した人が管理画面から変えられるよう、コードに直書きせずここから読み出す。
 * 未設定なら .env の値、それも無ければメインドメインを使う（セットアップ直後やDBに接続できない状態でも表示できる）。
 */
final class SiteIdentity
{
    /** @var array<string, string|null> リクエスト内キャッシュ */
    private array $resolved = [];

    public function __construct(private readonly Config $config) {}

    public function name(): string
    {
        return $this->stored(AppSetting::SITE_NAME)
            ?? $this->configured('name')
            ?? (string) $this->config->get('shortener.domains.main');
    }

    public function tagline(): string
    {
        return $this->stored(AppSetting::SITE_TAGLINE) ?? $this->configured('tagline') ?? 'シンプルなURL短縮サービス';
    }

    /** 利用規約・プライバシーポリシーに表示する運営者名 */
    public function operator(): string
    {
        return $this->stored(AppSetting::SITE_OPERATOR) ?? $this->configured('operator') ?? $this->name();
    }

    /** ページのタイトルなどに使う「{名前} - {キャッチコピー}」 */
    public function titleWithTagline(): string
    {
        return $this->name().' - '.$this->tagline();
    }

    /**
     * @param  array{name: string, tagline: string, operator: string}  $values
     */
    public function save(array $values): void
    {
        DB::transaction(static function () use ($values): void {
            AppSetting::store(AppSetting::SITE_NAME, $values['name']);
            AppSetting::store(AppSetting::SITE_TAGLINE, $values['tagline']);
            AppSetting::store(AppSetting::SITE_OPERATOR, $values['operator']);
        });

        $this->resolved = [];
    }

    private function configured(string $key): ?string
    {
        $value = $this->config->get("shortener.site.{$key}");

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stored(string $key): ?string
    {
        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        try {
            $value = AppSetting::valueFor($key);
        } catch (QueryException $e) {
            // セットアップ前などデータベースを読めない場合は既定値で表示する
            Log::debug('サイト設定を読み込めませんでした。', ['key' => $key, 'exception' => $e::class]);
            $value = null;
        }

        return $this->resolved[$key] = is_string($value) && $value !== '' ? $value : null;
    }
}
