<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\User;
use App\Support\ShortenerSettings;
use App\ViewModels\ViewerData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** API キーの発行・無効化（requirements.md 5: 管理者のみ。ダッシュボードから発行） */
final class ApiKeyController extends Controller
{
    public const NEW_TOKEN_SESSION_KEY = 'new_api_token';

    public function index(Request $request, ShortenerSettings $settings): View
    {
        $token = $request->session()->get(self::NEW_TOKEN_SESSION_KEY);

        return view('dashboard.admin.api-keys', [
            'viewer' => ViewerData::fromUser($request->user()),
            'keys' => ApiKey::query()->with('user')->latest('id')->get(),
            'newToken' => is_string($token) ? $token : null,
            'apiBaseUrl' => 'https://'.config('shortener.domains.api').'/v1',
            'timezone' => $settings->displayTimezone(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            ['name' => ['required', 'string', 'max:64']],
            ['name.required' => '用途がわかる名前を入力してください。', 'name.max' => '名前は:max文字以内で入力してください。'],
        );

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        [$key, $token] = ApiKey::issueFor($user, trim($validated['name']));

        Log::notice('API キーを発行しました。', ['api_key_id' => $key->id, 'user_id' => $user->id]);

        // 平文のキーはこの一度だけ表示する
        return back()
            ->with(self::NEW_TOKEN_SESSION_KEY, $token)
            ->with('notice', "API キー「{$key->name}」を発行しました。");
    }

    public function revoke(Request $request, ApiKey $apiKey): RedirectResponse
    {
        if (! $apiKey->isRevoked()) {
            $apiKey->revoked_at = CarbonImmutable::now();
            $apiKey->save();

            Log::notice('API キーを無効化しました。', ['api_key_id' => $apiKey->id, 'user_id' => $request->user()?->getAuthIdentifier()]);
        }

        return back()->with('notice', "API キー「{$apiKey->name}」を無効にしました。");
    }
}
