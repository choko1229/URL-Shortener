<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AccessMode;
use App\Enums\OutsiderAction;
use App\Models\AppSetting;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 限定モード: 管理者と、管理者が許可したユーザーだけがサイトを使えるようにする。
 * 発行済みの短縮URLは誰でも開ける（転送・QRコード・固定ページ・お問い合わせは対象外）。
 */
final class AccessPolicy
{
    use ReadsSiteSettings;

    public function mode(): AccessMode
    {
        return AccessMode::fromValue($this->stored(AppSetting::ACCESS_MODE));
    }

    public function isRestricted(): bool
    {
        return $this->mode() === AccessMode::Restricted;
    }

    /** トップページ・ダッシュボードを使えるか */
    public function allows(?User $user): bool
    {
        return ! $this->isRestricted() || ($user?->canUseRestrictedSite() ?? false);
    }

    /**
     * 転送前の安全確認（Google Safe Browsing）を省くか。
     * 限定モードで、利用を許可された人（管理者・許可したユーザー）が発行した短縮URLは、発行者を信頼して確認しない。
     * 限定モードにする前に未ログインで発行されたものや、退会した人のものは、これまでどおり確認する
     */
    public function skipsSafetyCheck(ShortUrl $link): bool
    {
        if (! $this->isRestricted() || $link->user_id === null) {
            return false;
        }

        $link->loadMissing('user');

        return $link->user?->canUseRestrictedSite() ?? false;
    }

    public function outsiderAction(): OutsiderAction
    {
        // 移動先が無ければ説明ページを出す（設定の途中で空になった場合など）
        return $this->redirectUrl() === null ? OutsiderAction::Page : OutsiderAction::fromValue($this->stored(AppSetting::ACCESS_OUTSIDER_ACTION));
    }

    public function redirectUrl(): ?string
    {
        return $this->stored(AppSetting::ACCESS_REDIRECT_URL);
    }

    /**
     * @param  array{mode: string, outsider_action: string, redirect_url: string|null}  $values
     */
    public function save(array $values): void
    {
        DB::transaction(static function () use ($values): void {
            AppSetting::store(AppSetting::ACCESS_MODE, AccessMode::fromValue($values['mode'])->value);
            AppSetting::store(AppSetting::ACCESS_OUTSIDER_ACTION, OutsiderAction::fromValue($values['outsider_action'])->value);
            AppSetting::store(AppSetting::ACCESS_REDIRECT_URL, $values['redirect_url']);
        });

        $this->forgetStoredSettings();
    }
}
