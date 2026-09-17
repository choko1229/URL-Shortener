<?php

declare(strict_types=1);

namespace App\Installer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;
use Throwable;

/**
 * セットアップ画面で入力された DB 接続情報の確認。
 * テストで差し替えられるよう final にしていない。
 */
class DatabaseConnectionTester
{
    private const TIMEOUT_SECONDS = 5;

    private const RECOMMENDED_MYSQL_VERSION = '8.0.0';

    public function test(DatabaseCredentials $credentials): DatabaseTestResult
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $credentials->host, $credentials->port, $credentials->database);

        try {
            $pdo = new PDO($dsn, $credentials->username, $credentials->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => self::TIMEOUT_SECONDS,
            ]);
            $statement = $pdo->query('SELECT VERSION()');
            $version = $statement === false ? '' : (string) $statement->fetchColumn();
        } catch (PDOException $e) {
            $code = self::mysqlErrorCode($e);

            // パスワードを含む可能性があるため、例外メッセージ全文はログに残さない
            Log::warning('セットアップ: データベースに接続できませんでした。', [
                'host' => $credentials->host,
                'port' => $credentials->port,
                'mysql_error_code' => $code,
            ]);

            return DatabaseTestResult::failed(self::messageFor($code));
        }

        return DatabaseTestResult::succeeded($version, self::versionWarning($version));
    }

    /** 保存済みの設定（.env）で既定の DB に接続できるか */
    public function canConnectWithCurrentConfiguration(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable $e) {
            Log::warning('セットアップ: 保存済みの設定でデータベースに接続できませんでした。', ['exception' => $e::class]);

            return false;
        }
    }

    private static function mysqlErrorCode(PDOException $e): ?int
    {
        return preg_match('/\[(\d{4})\]/', $e->getMessage(), $matches) === 1 ? (int) $matches[1] : null;
    }

    private static function messageFor(?int $code): string
    {
        return match ($code) {
            1044 => 'このユーザーにはデータベースへのアクセス権限がありません。',
            1045 => 'ユーザー名またはパスワードが正しくありません。',
            1049 => 'データベースが見つかりません。先にサーバーの管理画面で作成してください。',
            2002, 2003, 2005 => 'データベースサーバーに接続できません。ホスト名とポートを確認してください。',
            null => 'データベースに接続できませんでした。入力内容と pdo_mysql 拡張が有効か確認してください。',
            default => "データベースに接続できませんでした（エラーコード: {$code}）。",
        };
    }

    private static function versionWarning(string $version): ?string
    {
        if (stripos($version, 'mariadb') !== false) {
            return "MariaDB（{$version}）です。MySQL 8.0 以外での動作は確認していません。";
        }

        $numeric = preg_match('/\A\d+\.\d+\.\d+/', $version, $matches) === 1 ? $matches[0] : '0.0.0';

        return version_compare($numeric, self::RECOMMENDED_MYSQL_VERSION, '<')
            ? "MySQL {$version} です。MySQL 8.0 以上を推奨します。"
            : null;
    }
}
