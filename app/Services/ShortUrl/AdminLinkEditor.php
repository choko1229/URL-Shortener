<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Models\ShortUrl;
use App\Models\User;
use App\Support\ShortenerSettings;
use App\ViewModels\ShortUrlFormData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/** 管理者による短縮URLの編集（元URL・有効期限・発行者）。短縮コードはそのまま */
final class AdminLinkEditor
{
    public function __construct(private readonly ShortenerSettings $settings) {}

    /**
     * @param  array{original_url: string, expires_at_local: string|null, user_id: int|null}  $changes
     * @return list<string> 変更した項目（画面の表示用）
     */
    public function update(ShortUrl $link, array $changes, User $admin): array
    {
        $previousOwner = $link->user_id;

        $timezone = $this->settings->displayTimezone();
        $link->original_url = $changes['original_url'];

        // 画面の日時は分単位のため、同じ値のまま保存したときは秒を切り捨てずに元の値を残す
        $currentLocal = $link->expires_at?->setTimezone($timezone)->format(ShortUrlFormData::DATETIME_LOCAL_FORMAT);
        if ($changes['expires_at_local'] !== $currentLocal) {
            $link->expires_at = $changes['expires_at_local'] === null
                ? null
                : CarbonImmutable::createFromFormat(ShortUrlFormData::DATETIME_LOCAL_FORMAT, $changes['expires_at_local'], $timezone)?->utc();
        }

        if ($link->user_id !== $changes['user_id']) {
            $link->user_id = $changes['user_id'];

            // ログインユーザーのものにしたら、未ログイン発行の名残（削除用トークン・IP のハッシュ）は消す
            if ($changes['user_id'] !== null) {
                $link->deletion_token_hash = null;
                $link->creator_ip_hash = null;
            }
        }

        $changed = array_keys(array_filter([
            '元URL' => $link->isDirty('original_url'),
            '有効期限' => $link->isDirty('expires_at'),
            '発行者' => $link->isDirty('user_id'),
        ]));

        $link->save();

        // 元URLは記録しない（発行時と同じく、利用者の URL をログに残さない）
        if ($changed !== []) {
            Log::notice('管理者が短縮URLを編集しました。', [
                'short_url_id' => $link->id,
                'changed_by' => $admin->id,
                'changed' => $changed,
                'owner' => [$previousOwner, $link->user_id],
            ]);
        }

        return $changed;
    }
}
