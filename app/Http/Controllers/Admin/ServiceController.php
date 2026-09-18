<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceSettingsRequest;
use App\Services\Settings\ExternalServiceSettings;
use App\Support\ExternalServiceKeys;
use App\ViewModels\ViewerData;
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
    public function index(Request $request, ExternalServiceKeys $keys): View
    {
        $geoipDatabase = (string) config('shortener.geoip_database');

        return view('dashboard.admin.services', [
            'viewer' => ViewerData::fromUser($request->user()),
            'callbackUrl' => route('auth.callback'),
            'discordClientId' => $keys->discordClientId(),
            'hasDiscordSecret' => $keys->discordClientSecret() !== null,
            'hasSafeBrowsingKey' => $keys->safeBrowsingApiKey() !== null,
            'recaptchaSiteKey' => $keys->recaptchaSiteKey(),
            'hasRecaptchaSecret' => $keys->recaptchaSecretKey() !== null,
            'geoipPath' => ltrim(str_replace([base_path(), '\\'], ['', '/'], $geoipDatabase), '/'),
            'hasGeoipDatabase' => is_file($geoipDatabase),
        ]);
    }

    public function update(ServiceSettingsRequest $request, ExternalServiceSettings $settings): RedirectResponse
    {
        $settings->save($request->changes());

        Log::notice('外部サービスの設定を変更しました。', ['user_id' => $request->user()?->getAuthIdentifier()]);

        return back()->with('notice', '外部サービスの設定を保存しました。');
    }
}
