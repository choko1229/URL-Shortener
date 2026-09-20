<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceSettingsRequest;
use App\Services\GeoIp\GeoIpDatabase;
use App\Services\GeoIp\GeoIpDatabaseUpdater;
use App\Services\Settings\ExternalServiceSettings;
use App\Support\ExternalServiceKeys;
use App\Support\ShortenerSettings;
use App\ViewModels\ViewerData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 外部サービスの設定（Discord ログイン・悪意URLチェック・reCAPTCHA・国判定）。
 * セットアップ画面では DB 接続情報だけを入力し、それ以外はここで設定する。
 */
final class ServiceController extends Controller
{
    public function index(
        Request $request,
        ExternalServiceKeys $keys,
        GeoIpDatabase $geoIp,
        GeoIpDatabaseUpdater $geoIpUpdater,
        ShortenerSettings $settings,
    ): View {
        return view('dashboard.admin.services', [
            'viewer' => ViewerData::fromUser($request->user()),
            'callbackUrl' => route('auth.callback'),
            'discordClientId' => $keys->discordClientId(),
            'hasDiscordSecret' => $keys->discordClientSecret() !== null,
            'hasSafeBrowsingKey' => $keys->safeBrowsingApiKey() !== null,
            'recaptchaSiteKey' => $keys->recaptchaSiteKey(),
            'recaptchaProjectId' => $keys->recaptchaProjectId(),
            'hasRecaptchaApiKey' => $keys->recaptchaApiKey() !== null,
            'mainDomain' => (string) config('shortener.domains.main'),
            'geoIpSource' => $geoIp->source(),
            'geoIpRelease' => $geoIpUpdater->installedRelease(),
            'geoIpUpdatedAt' => $geoIpUpdater->updatedAt()?->setTimezone($settings->displayTimezone()),
            'geoIpAutoUpdate' => $geoIpUpdater->isEnabled(),
            'geoIpManualPath' => self::relativePath($geoIp->manualPath),
        ]);
    }

    public function update(ServiceSettingsRequest $request, ExternalServiceSettings $settings): RedirectResponse
    {
        $settings->save($request->changes());

        Log::notice('外部サービスの設定を変更しました。', ['user_id' => $request->user()?->getAuthIdentifier()]);

        return back()->with('notice', '外部サービスの設定を保存しました。');
    }

    /** 国判定のデータベースを今すぐ取得・更新する（通常は定期処理で自動的に行う） */
    public function updateGeoIp(GeoIpDatabaseUpdater $updater): RedirectResponse
    {
        @set_time_limit(300);

        $result = $updater->update(CarbonImmutable::now());

        return back()->with($result->successful ? 'notice' : 'error', $result->message);
    }

    private static function relativePath(string $path): string
    {
        return ltrim(str_replace([base_path(), '\\'], ['', '/'], $path), '/');
    }
}
