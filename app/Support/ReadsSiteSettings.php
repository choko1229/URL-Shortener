<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * app_settings から表示用の値を読む（リクエスト内でキャッシュする）。
 * セットアップ前やデータベースに接続できない状態でも画面を描けるよう、読めない場合は null を返す。
 */
trait ReadsSiteSettings
{
    /** @var array<string, string|null> */
    private array $resolvedSettings = [];

    protected function stored(string $key): ?string
    {
        if (array_key_exists($key, $this->resolvedSettings)) {
            return $this->resolvedSettings[$key];
        }

        try {
            $value = AppSetting::valueFor($key);
        } catch (QueryException $e) {
            Log::debug('サイト設定を読み込めませんでした。', ['key' => $key, 'exception' => $e::class]);
            $value = null;
        }

        return $this->resolvedSettings[$key] = is_string($value) && $value !== '' ? $value : null;
    }

    protected function forgetStoredSettings(): void
    {
        $this->resolvedSettings = [];
    }
}
