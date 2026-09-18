<?php

declare(strict_types=1);

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Http\Requests\Install\InstallRequest;
use App\Installer\DatabaseConnectionTester;
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
use Illuminate\Http\Response;

/**
 * 初期セットアップ（requirements.md 4-2）。
 * 動作環境の確認と DB 接続情報の入力を 1 画面で行う。ドメインはアクセス中のホスト名から自動で決める。
 * 未インストール時のみ到達できる（EnsureApplicationInstalled）。
 */
final class InstallController extends Controller
{
    public function show(Request $request, RequirementChecker $checker): View
    {
        $secure = Preflight::isSecureRequest($request->server->all());
        $suggested = SiteSettings::suggestDomains($request->getHost());
        $results = $checker->check($request->getSchemeAndHttpHost(), $secure, $this->subdomainUrls($suggested, $secure, $request));

        return view('install.index', [
            'results' => $results,
            'hasFailure' => self::hasStatus($results, RequirementStatus::Failed),
            'needsConfirmation' => self::hasStatus($results, RequirementStatus::Unknown),
            'suggested' => $suggested,
            'secure' => $secure,
            'databaseDefaults' => [
                'db_host' => (string) config('database.connections.mysql.host', 'localhost'),
                'db_port' => (string) config('database.connections.mysql.port', '3306'),
                'db_database' => (string) config('database.connections.mysql.database', ''),
                'db_username' => (string) config('database.connections.mysql.username', ''),
            ],
        ]);
    }

    public function store(InstallRequest $request, RequirementChecker $checker, DatabaseConnectionTester $tester, Installer $installer): View|RedirectResponse
    {
        $secure = Preflight::isSecureRequest($request->server->all());
        // サブドメインの向き先は「注意」止まりのため、完了時には確認し直さない
        $results = $checker->check($request->getSchemeAndHttpHost(), $secure);

        if (self::hasStatus($results, RequirementStatus::Failed)) {
            return $this->back($request, '動作条件を満たしていない項目があります。画面の「NG」の項目を解決してください。');
        }

        if (self::hasStatus($results, RequirementStatus::Unknown) && ! $request->boolean('confirmed')) {
            return redirect()->route('install.show')
                ->withInput($request->safe()->except('db_password'))
                ->withErrors(['confirmed' => 'リンク先にファイルの中身が表示されないことを確認し、チェックを入れてください。']);
        }

        $credentials = $request->credentials();
        $connection = $tester->test($credentials);

        if (! $connection->successful) {
            return $this->back($request, (string) $connection->errorMessage);
        }

        $site = $request->siteSettings($secure);

        try {
            $installer->install($credentials, $site);
        } catch (InstallationException $e) {
            return $this->back($request, $e->getMessage());
        }

        return view('install.complete', [
            'settings' => $site,
            'databaseWarning' => $connection->warning,
        ]);
    }

    /** サブドメインがこのフォルダを向いているかの確認用（RequirementChecker が各サブドメインから呼び出す） */
    public function ping(RequirementChecker $checker): Response
    {
        return response($checker->pingToken(), Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * 各サブドメインの確認先 URL（標準以外のポートでアクセスしている場合はポートも付ける）
     *
     * @param  array{main: string, dashboard: string, api: string, redirect: string}  $suggested
     * @return array<string, string>
     */
    private function subdomainUrls(array $suggested, bool $secure, Request $request): array
    {
        $scheme = $secure ? 'https' : 'http';
        $port = $request->getPort();
        $portSuffix = in_array($port, [80, 443, null], true) ? '' : ':'.$port;

        return [
            'ダッシュボード' => "{$scheme}://{$suggested['dashboard']}{$portSuffix}",
            'API' => "{$scheme}://{$suggested['api']}{$portSuffix}",
            'リダイレクト確認' => "{$scheme}://{$suggested['redirect']}{$portSuffix}",
        ];
    }

    private function back(InstallRequest $request, string $error): RedirectResponse
    {
        return redirect()->route('install.show')
            ->withInput($request->safe()->except('db_password'))
            ->with('error', $error);
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
