<?php

declare(strict_types=1);

namespace App\Services\Update;

use App\Support\ExternalServiceKeys;
use App\Support\SiteIdentity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** 管理者への通知（requirements.md 7-1: Discord Webhook）。失敗しても処理は止めない */
final class DiscordWebhookNotifier
{
    public const URL_PATTERN = '#\Ahttps://(?:discord\.com|discordapp\.com|canary\.discord\.com|ptb\.discord\.com)/api/webhooks/\d+/[A-Za-z0-9_\-]+\z#';

    private const MAX_LENGTH = 1900;

    public function __construct(
        private readonly ExternalServiceKeys $keys,
        private readonly SiteIdentity $site,
    ) {}

    /** 通知の先頭に付けるサイト名 */
    public function prefix(): string
    {
        return '['.$this->site->name().'] ';
    }

    public function send(string $message): bool
    {
        $url = $this->keys->discordWebhookUrl();

        if ($url === null || preg_match(self::URL_PATTERN, $url) !== 1) {
            Log::info('Discord Webhook が未設定のため通知しませんでした。', ['message' => $message]);

            return false;
        }

        try {
            $response = Http::timeout(10)->asJson()->post($url, [
                'content' => mb_substr($message, 0, self::MAX_LENGTH),
                // メンションを発生させない
                'allowed_mentions' => ['parse' => []],
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Discord Webhook に接続できませんでした。', ['exception' => $e::class]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Discord Webhook への通知に失敗しました。', ['status' => $response->status()]);

            return false;
        }

        return true;
    }
}
