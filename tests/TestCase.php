<?php

declare(strict_types=1);

namespace Tests;

use App\Installer\InstallationState;
use App\Installer\SchemaState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ビルド成果物（public/build）が無くても Blade を描画できるようにする
        $this->withoutVite();

        // 既定ではインストール済みとして扱う（セットアップ画面のテストでは差し替える）
        $installed = new InstallationState(storage_path('framework/testing/installed.json'));
        if (! $installed->isInstalled()) {
            $installed->markInstalled();
        }
        $this->app->instance(InstallationState::class, $installed);

        // 既定ではテーブルは最新として扱う（テスト中に migrate が走らないようにする）
        $schema = new SchemaState(storage_path('framework/testing/schema.json'), database_path('migrations'));
        if (! $schema->isCurrent()) {
            $schema->markUpdated();
        }
        $this->app->instance(SchemaState::class, $schema);
    }

    protected function mainUrl(string $path = '/'): string
    {
        return 'http://'.config('shortener.domains.main').$path;
    }

    protected function dashboardUrl(string $path = '/'): string
    {
        return 'http://'.config('shortener.domains.dashboard').$path;
    }

    protected function redirectUrl(string $path = '/'): string
    {
        return 'http://'.config('shortener.domains.redirect').$path;
    }
}
