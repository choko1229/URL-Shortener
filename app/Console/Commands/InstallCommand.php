<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Installer\InstallationException;
use App\Installer\InstallationState;
use App\Installer\Installer;
use Illuminate\Console\Command;

/** SSH が使える環境・ローカル開発用のセットアップ（ドメイン等は .env を直接編集する） */
final class InstallCommand extends Command
{
    protected $signature = 'app:install {--force : インストール済みでも再実行する}';

    protected $description = 'テーブル作成と予約語の投入を行い、インストール済みとして記録します';

    public function handle(Installer $installer, InstallationState $state): int
    {
        if ($state->isInstalled() && ! $this->option('force')) {
            $this->components->info('すでにインストール済みです。再実行する場合は --force を指定してください。');

            return self::SUCCESS;
        }

        try {
            $installer->setUpDatabase();
            $installer->markInstalled();
        } catch (InstallationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('インストールが完了しました。ドメインなどの設定は .env の SHORTENER_* を確認してください。');

        return self::SUCCESS;
    }
}
