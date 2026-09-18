<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UpdateRun;
use App\Services\Update\AppVersion;
use App\Services\Update\BackupManager;
use App\Services\Update\DiscordWebhookNotifier;
use App\Services\Update\Updater;
use App\Services\Update\UpdateSettings;
use App\Services\Update\UpdateStrategy;
use App\Support\ShortenerSettings;
use App\ViewModels\ViewerData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** アップデート管理（requirements.md 4-2 管理者の特権、7 章 自動アップデート） */
final class UpdateController extends Controller
{
    private const RECENT_RUNS = 10;

    public function index(
        Request $request,
        UpdateSettings $settings,
        AppVersion $version,
        UpdateStrategy $strategy,
        BackupManager $backups,
        ShortenerSettings $shortener,
    ): View {
        return view('dashboard.admin.updates', [
            'viewer' => ViewerData::fromUser($request->user()),
            'currentVersion' => $version->current()?->toString(),
            'strategy' => $strategy->name(),
            'enabled' => $settings->enabled(),
            'repository' => $settings->repository(),
            'hasToken' => $settings->githubToken() !== null,
            'hasWebhook' => $settings->discordWebhookUrl() !== null,
            'runs' => UpdateRun::query()->latest('id')->limit(self::RECENT_RUNS)->get(),
            'backups' => array_map('basename', $backups->list()),
            'timezone' => $shortener->displayTimezone(),
        ]);
    }

    public function updateSettings(Request $request, UpdateSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'repository' => ['required', 'string', 'max:140', 'regex:'.UpdateSettings::REPOSITORY_PATTERN],
            'github_token' => ['nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_\-]+\z/'],
            'clear_github_token' => ['nullable', 'boolean'],
            'discord_webhook_url' => ['nullable', 'string', 'max:255', 'regex:'.DiscordWebhookNotifier::URL_PATTERN],
            'clear_discord_webhook_url' => ['nullable', 'boolean'],
        ], [
            'repository.regex' => 'リポジトリは「所有者/リポジトリ名」の形式で入力してください。',
            'github_token.regex' => 'GitHub のトークンの形式が正しくありません。',
            'discord_webhook_url.regex' => 'Discord の Webhook URL（https://discord.com/api/webhooks/...）を入力してください。',
        ]);

        $settings->save(
            enabled: $request->boolean('enabled'),
            repository: $validated['repository'],
            githubToken: $request->boolean('clear_github_token') ? '' : ($validated['github_token'] ?? null),
            webhookUrl: $request->boolean('clear_discord_webhook_url') ? '' : ($validated['discord_webhook_url'] ?? null),
        );

        Log::notice('自動アップデートの設定を変更しました。', ['user_id' => $request->user()?->getAuthIdentifier()]);

        return back()->with('notice', '自動アップデートの設定を保存しました。');
    }

    public function check(Updater $updater): RedirectResponse
    {
        $result = $updater->check();

        return back()->with($result->error === null ? 'notice' : 'error', $result->message());
    }

    public function testNotification(DiscordWebhookNotifier $notifier): RedirectResponse
    {
        return $notifier->send('[chok.ooo] 通知のテストです。')
            ? back()->with('notice', 'Discord にテスト通知を送りました。')
            : back()->with('error', 'Discord に通知できませんでした。Webhook URL を確認してください。');
    }
}
