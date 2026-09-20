<?php

declare(strict_types=1);

namespace App\Installer;

use Database\Seeders\ReservedWordSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/** セットアップの実処理（Web インストーラと app:install コマンドで共用） */
final class Installer
{
    private const MAX_EXECUTION_SECONDS = 300;

    public function __construct(
        private readonly Application $app,
        private readonly Kernel $artisan,
        private readonly InstallationState $state,
        private readonly SchemaState $schema,
        private readonly EnvironmentFile $environment,
        private readonly DatabaseConnectionSwitcher $connections,
    ) {}

    /**
     * Web インストーラ: 入力された DB にテーブルを作成し、接続情報とドメインを .env に保存して完了させる。
     * テーブル作成に失敗した場合は .env を書き換えない（セットアップ画面を開き直せる状態を保つ）。
     *
     * @throws InstallationException
     */
    public function install(DatabaseCredentials $database, SiteSettings $site): void
    {
        // .env の変更はこのリクエストには反映されないため、入力された接続先を直接使う
        $this->connections->use($database);

        $this->setUpDatabase();

        try {
            $this->environment->write($database->environmentValues() + $site->environmentValues());
        } catch (Throwable $e) {
            Log::error('セットアップ: .env を更新できませんでした。', ['exception' => $e::class, 'error' => $e->getMessage()]);

            throw new InstallationException('設定ファイル（.env）に書き込めませんでした。書き込み権限を確認してください。', previous: $e);
        }

        // 設定がキャッシュされていると .env の変更が反映されないため消去する
        if ($this->app->configurationIsCached()) {
            $this->runArtisan('config:clear', [], '設定キャッシュを削除できませんでした。');
        }

        $this->markInstalled();

        Log::info('初期セットアップが完了しました。', ['main_domain' => $site->mainDomain]);
    }

    /**
     * テーブル作成と初期データ投入。何度実行しても安全（マイグレーションは差分のみ、予約語は upsert）。
     *
     * @throws InstallationException
     */
    public function setUpDatabase(): void
    {
        @set_time_limit(self::MAX_EXECUTION_SECONDS);

        $this->runArtisan('migrate', ['--force' => true], 'テーブルの作成に失敗しました。データベースの権限を確認してください。');
        $this->runArtisan('db:seed', ['--class' => ReservedWordSeeder::class, '--force' => true], '予約語の登録に失敗しました。');

        // 適用済みとして記録し、次のアクセスで同じ確認を繰り返さないようにする
        $this->schema->markUpdated();
    }

    /** @throws InstallationException */
    public function markInstalled(): void
    {
        try {
            $this->state->markInstalled();
        } catch (RuntimeException $e) {
            Log::error('セットアップ: インストール済みの記録を作成できませんでした。', ['error' => $e->getMessage()]);

            throw new InstallationException('インストール済みの記録を作成できませんでした。storage フォルダの書き込み権限を確認してください。', previous: $e);
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws InstallationException
     */
    private function runArtisan(string $command, array $parameters, string $failureMessage): void
    {
        try {
            $exitCode = $this->artisan->call($command, $parameters);
        } catch (Throwable $e) {
            Log::error('セットアップ: コマンドの実行に失敗しました。', [
                'command' => $command,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            throw new InstallationException($failureMessage, previous: $e);
        }

        if ($exitCode !== 0) {
            Log::error('セットアップ: コマンドが異常終了しました。', [
                'command' => $command,
                'exit_code' => $exitCode,
                'output' => $this->artisan->output(),
            ]);

            throw new InstallationException($failureMessage);
        }
    }
}
