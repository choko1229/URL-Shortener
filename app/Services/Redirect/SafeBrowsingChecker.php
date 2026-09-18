<?php

declare(strict_types=1);

namespace App\Services\Redirect;

use App\Support\ExternalServiceKeys;
use App\Support\ShortenerSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Safe Browsing（Lookup API v4）による悪意URLチェック（requirements.md 2-7）。
 * 同じ URL の判定結果は一定期間キャッシュする。確認できなかった結果はキャッシュしない。
 */
final class SafeBrowsingChecker
{
    private const ENDPOINT = 'https://safebrowsing.googleapis.com/v4/threatMatches:find';

    private const THREAT_TYPES = ['MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE', 'POTENTIALLY_HARMFUL_APPLICATION'];

    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly ExternalServiceKeys $keys,
        private readonly ShortenerSettings $settings,
    ) {}

    public function check(string $url): SafetyVerdict
    {
        $apiKey = $this->keys->safeBrowsingApiKey();

        if ($apiKey === null) {
            return SafetyVerdict::unknown('安全性チェック（Google Safe Browsing）が設定されていません。');
        }

        $cacheKey = 'safe-browsing:'.hash('sha256', $url);
        $cached = SafetyVerdict::fromCache(Cache::get($cacheKey));

        if ($cached !== null) {
            return $cached;
        }

        try {
            // API キーを URL に含めない（ログやエラーメッセージに残さないため）
            $response = Http::withHeaders(['X-Goog-Api-Key' => $apiKey])
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::ENDPOINT, [
                    'client' => ['clientId' => 'chok-ooo', 'clientVersion' => '1.0'],
                    'threatInfo' => [
                        'threatTypes' => self::THREAT_TYPES,
                        'platformTypes' => ['ANY_PLATFORM'],
                        'threatEntryTypes' => ['URL'],
                        'threatEntries' => [['url' => $url]],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Safe Browsing API に接続できませんでした。', ['exception' => $e::class]);

            return SafetyVerdict::unknown('安全性チェックのサーバーに接続できませんでした。');
        }

        if (! $response->successful()) {
            Log::warning('Safe Browsing API がエラーを返しました。', ['status' => $response->status()]);

            return SafetyVerdict::unknown('安全性チェックのサーバーがエラーを返しました。');
        }

        $threats = [];
        foreach ((array) $response->json('matches', []) as $match) {
            if (is_array($match) && is_string($match['threatType'] ?? null)) {
                $threats[] = $match['threatType'];
            }
        }

        $verdict = $threats === [] ? SafetyVerdict::safe() : SafetyVerdict::unsafe(array_values(array_unique($threats)));

        if ($verdict->status === SafetyStatus::Unsafe) {
            Log::notice('Safe Browsing により危険なリンクと判定されました。', ['threats' => $verdict->threats]);
        }

        Cache::put($cacheKey, $verdict->toCache(), now()->addDays($this->settings->safeBrowsingCacheDays()));

        return $verdict;
    }
}
