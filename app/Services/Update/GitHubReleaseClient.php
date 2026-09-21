<?php

declare(strict_types=1);

namespace App\Services\Update;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GitHub Releases から最新リリースを取得する（requirements.md 7-2）。
 * 公開リポジトリならトークンなしで動く。非公開リポジトリや API の回数制限を避けたい場合はトークンを設定する。
 */
final class GitHubReleaseClient
{
    private const API_BASE = 'https://api.github.com';

    // 配布用 zip の名前（scripts/build-release.sh が作る url-shortener-{タグ}.zip）
    private const PACKAGE_ASSET_PATTERN = '/\Aurl-shortener-.+\.zip\z/';

    private const DOWNLOAD_TIMEOUT_SECONDS = 300;

    /** @throws UpdateException */
    /** @param  string|null  $token  非公開リポジトリの場合に必要 */
    public function latest(string $repository, ?string $token): ReleaseInfo
    {
        try {
            $response = $this->request($token)->timeout(15)->get(self::API_BASE."/repos/{$repository}/releases/latest");
        } catch (ConnectionException $e) {
            Log::warning('GitHub に接続できませんでした。', ['exception' => $e::class]);

            throw new UpdateException('GitHub に接続できませんでした。');
        }

        if ($response->status() === 404) {
            throw new UpdateException($token === null
                ? "リリースが見つかりません（リポジトリ {$repository} を確認してください。非公開リポジトリの場合はトークンが必要です）。"
                : "リリースが見つかりません（リポジトリ {$repository} とトークンの権限を確認してください）。");
        }

        if (! $response->successful()) {
            Log::warning('GitHub の最新リリースを取得できませんでした。', ['status' => $response->status()]);

            throw new UpdateException("GitHub の最新リリースを取得できませんでした（HTTP {$response->status()}）。");
        }

        $tag = $response->json('tag_name');
        $version = is_string($tag) ? Version::tryParse($tag) : null;

        if ($version === null) {
            throw new UpdateException('最新リリースのタグが vYY.MM.patch 形式ではありません。');
        }

        $assetUrl = null;
        foreach ((array) $response->json('assets', []) as $asset) {
            if (is_array($asset) && is_string($asset['name'] ?? null) && preg_match(self::PACKAGE_ASSET_PATTERN, $asset['name']) === 1 && is_string($asset['url'] ?? null)) {
                $assetUrl = $asset['url'];
                break;
            }
        }

        return new ReleaseInfo((string) $tag, $version, (string) $response->json('html_url', ''), $assetUrl);
    }

    /** @throws UpdateException */
    public function downloadAsset(string $assetUrl, ?string $token, string $destination): void
    {
        try {
            // リダイレクト先（ストレージ）には Authorization ヘッダーを送らない（Guzzle が別ホストでは除去する）
            $response = $this->request($token)
                // withHeaders は同名のヘッダーを結合するため置き換える。
                // 「application/vnd.github+json, application/octet-stream」になると、GitHub は zip ではなく添付ファイルの説明（JSON）を返す
                ->replaceHeaders(['Accept' => 'application/octet-stream'])
                ->timeout(self::DOWNLOAD_TIMEOUT_SECONDS)
                ->sink($destination)
                ->get($assetUrl);
        } catch (ConnectionException $e) {
            Log::warning('リリースのダウンロードで通信エラーが発生しました。', ['exception' => $e::class]);

            throw new UpdateException('リリースの zip をダウンロードできませんでした。');
        }

        if (! $response->successful() || ! is_file($destination) || filesize($destination) === 0) {
            throw new UpdateException("リリースの zip をダウンロードできませんでした（HTTP {$response->status()}）。");
        }

        if (! self::looksLikeZip($destination)) {
            Log::error('リリースのダウンロードで zip 以外が返りました。', [
                'content_type' => $response->header('Content-Type'),
                'size' => filesize($destination),
            ]);

            throw new UpdateException('リリースの zip をダウンロードできませんでした（zip 以外のデータが返りました: '.($response->header('Content-Type') ?: '種類不明').'）。');
        }
    }

    /** zip の先頭 4 バイト（PK\x03\x04）で判定する */
    private static function looksLikeZip(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $signature = fread($handle, 4);
        fclose($handle);

        return $signature === "PK\x03\x04";
    }

    private function request(?string $token): PendingRequest
    {
        $request = Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'url-shortener-updater',
        ]);

        return $token === null ? $request : $request->withToken($token);
    }
}
