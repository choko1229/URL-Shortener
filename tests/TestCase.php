<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ビルド成果物（public/build）が無くても Blade を描画できるようにする
        $this->withoutVite();
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
