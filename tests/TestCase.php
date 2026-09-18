<?php

declare(strict_types=1);

namespace Tests;

use App\Installer\InstallationState;
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
    }

    protected function mainUrl(string $path = '/'): string
    {
        return 'http://'.config('shortener.domains.main').$path;
    }

    protected function dashboardUrl(string $path = '/'): string
    {
        return 'http://'.config('shortener.domains.dashboard').$path;
    }
}
