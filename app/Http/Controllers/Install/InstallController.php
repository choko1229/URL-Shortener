<?php

declare(strict_types=1);

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Http\Requests\Install\DatabaseSettingsRequest;
use App\Http\Requests\Install\SiteSettingsRequest;
use App\Installer\DatabaseConnectionTester;
use App\Installer\EnvironmentFile;
use App\Installer\InstallationException;
use App\Installer\Installer;
use App\Installer\Preflight;
use App\Installer\RequirementChecker;
use App\Installer\RequirementResult;
use App\Installer\RequirementStatus;
use App\Installer\SiteSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 初期セットアップ（requirements.md 4-2）。
 * 1. 動作環境の確認 → 2. データベース → 3. サイト設定 の順に進む。
 * 未インストール時のみ到達できる（EnsureApplicationInstalled）。
 */
final class InstallController extends Controller
{
    private const SESSION_REQUIREMENTS_CONFIRMED = 'install.requirements_confirmed';

    private const SESSION_DATABASE_CONFIGURED = 'install.database_configured';

    public function requirements(Request $request, RequirementChecker $checker): View
    {
        $results = $this->checkRequirements($request, $checker);

        return view('install.requirements', [
            'results' => $results,
            'hasFailure' => self::hasStatus($results, RequirementStatus::Failed),
            'needsConfirmation' => self::hasStatus($results, RequirementStatus::Unknown),
        ]);
    }

    public function confirmRequirements(Request $request, RequirementChecker $checker): RedirectResponse
    {
        $results = $this->checkRequirements($request, $checker);

        if (self::hasStatus($results, RequirementStatus::Failed)) {
            return redirect()->route('install.requirements')->with('error', '動作条件を満たしていない項目があります。');
        }

        if (self::hasStatus($results, RequirementStatus::Unknown) && ! $request->boolean('confirmed')) {
            return redirect()->route('install.requirements')->withErrors([
                'confirmed' => 'リンク先にファイルの中身が表示されないことを確認し、チェックを入れてください。',
            ]);
        }

        $request->session()->put(self::SESSION_REQUIREMENTS_CONFIRMED, true);

        return redirect()->route('install.database');
    }

    public function database(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get(self::SESSION_REQUIREMENTS_CONFIRMED)) {
            return redirect()->route('install.requirements');
        }

        return view('install.database', [
            'defaults' => [
                'db_host' => (string) config('database.connections.mysql.host', 'localhost'),
                'db_port' => (string) config('database.connections.mysql.port', '3306'),
                'db_database' => (string) config('database.connections.mysql.database', ''),
                'db_username' => (string) config('database.connections.mysql.username', ''),
            ],
        ]);
    }

    public function storeDatabase(DatabaseSettingsRequest $request, DatabaseConnectionTester $tester, EnvironmentFile $environment): RedirectResponse
    {
        if (! $request->session()->get(self::SESSION_REQUIREMENTS_CONFIRMED)) {
            return redirect()->route('install.requirements');
        }

        $credentials = $request->credentials();
        $result = $tester->test($credentials);

        if (! $result->successful) {
            return back()
                ->withInput($request->safe()->except('db_password'))
                ->with('error', $result->errorMessage);
        }

        try {
            $environment->write($credentials->environmentValues());
        } catch (Throwable $e) {
            Log::error('セットアップ: DB 接続情報を .env に保存できませんでした。', ['exception' => $e::class, 'error' => $e->getMessage()]);

            return back()
                ->withInput($request->safe()->except('db_password'))
                ->with('error', '設定ファイル（.env）に書き込めませんでした。書き込み権限を確認してください。');
        }

        $request->session()->put(self::SESSION_DATABASE_CONFIGURED, true);

        return redirect()
            ->route('install.site')
            ->with('notice', $result->warning ?? "データベースに接続できました（{$result->serverVersion}）。");
    }

    public function site(Request $request, DatabaseConnectionTester $tester): View|RedirectResponse
    {
        if ($redirect = $this->requireDatabase($request, $tester)) {
            return $redirect;
        }

        return view('install.site', [
            'suggested' => SiteSettings::suggestDomains($request->getHost()),
            'secure' => Preflight::isSecureRequest($request->server->all()),
        ]);
    }

    public function storeSite(SiteSettingsRequest $request, DatabaseConnectionTester $tester, Installer $installer): View|RedirectResponse
    {
        if ($redirect = $this->requireDatabase($request, $tester)) {
            return $redirect;
        }

        $settings = $request->toSettings(Preflight::isSecureRequest($request->server->all()));

        try {
            $installer->complete($settings);
        } catch (InstallationException $e) {
            return back()
                ->withInput($request->safe()->except('discord_client_secret'))
                ->with('error', $e->getMessage());
        }

        $request->session()->forget([self::SESSION_REQUIREMENTS_CONFIRMED, self::SESSION_DATABASE_CONFIGURED]);

        return view('install.complete', ['settings' => $settings]);
    }

    /** @return list<RequirementResult> */
    private function checkRequirements(Request $request, RequirementChecker $checker): array
    {
        return $checker->check(
            $request->getSchemeAndHttpHost(),
            Preflight::isSecureRequest($request->server->all()),
        );
    }

    /** DB 設定が保存済みで、実際に接続できる場合のみ次へ進める */
    private function requireDatabase(Request $request, DatabaseConnectionTester $tester): ?RedirectResponse
    {
        if (! $request->session()->get(self::SESSION_DATABASE_CONFIGURED)) {
            return redirect()->route('install.database');
        }

        if (! $tester->canConnectWithCurrentConfiguration()) {
            return redirect()
                ->route('install.database')
                ->with('error', '保存したデータベース設定で接続できませんでした。もう一度入力してください。');
        }

        return null;
    }

    /** @param  list<RequirementResult>  $results */
    private static function hasStatus(array $results, RequirementStatus $status): bool
    {
        foreach ($results as $result) {
            if ($result->status === $status) {
                return true;
            }
        }

        return false;
    }
}
