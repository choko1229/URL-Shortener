<?php

declare(strict_types=1);

namespace App\Installer;

use App\Models\AppSetting;
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
        private readonly EnvironmentFile $environment,
    ) {}

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
    }

    /** @throws InstallationException */
    public function complete(SiteSettings $settings): void
    {
        $this->setUpDatabase();

        if ($settings->hasDiscordCredentials()) {
            try {
                AppSetting::store(AppSetting::DISCORD_CLIENT_ID, $settings->discordClientId);
                AppSetting::store(AppSetting::DISCORD_CLIENT_SECRET, $settings->discordClientSecret, encrypt: true);
            } catch (Throwable $e) {
                Log::error('セットアップ: Discord の設定を保存できませんでした。', ['exception' => $e::class, 'error' => $e->getMessage()]);

                throw new InstallationException('Discord の設定を保存できませんでした。', previous: $e);
            }
        }

        try {
            $this->environment->write($settings->environmentValues());
        } catch (Throwable $e) {
            Log::error('セットアップ: .env を更新できませんでした。', ['exception' => $e::class, 'error' => $e->getMessage()]);

            throw new InstallationException('設定ファイル（.env）を更新できませんでした。書き込み権限を確認してください。', previous: $e);
        }

        // 設定がキャッシュされていると .env の変更が反映されないため消去する
        if ($this->app->configurationIsCached()) {
            $this->runArtisan('config:clear', [], '設定キャッシュを削除できませんでした。');
        }

        $this->markInstalled();

        Log::info('初期セットアップが完了しました。', ['main_domain' => $settings->mainDomain]);
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
