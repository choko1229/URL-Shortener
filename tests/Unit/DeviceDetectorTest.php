<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DeviceType;
use App\Services\Redirect\DeviceDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeviceDetectorTest extends TestCase
{
    /** @return iterable<string, array{string|null, DeviceType}> */
    public static function userAgents(): iterable
    {
        yield 'Windows Chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36', DeviceType::Desktop];
        yield 'iPhone Safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', DeviceType::Mobile];
        yield 'Android スマホ' => ['Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Mobile Safari/537.36', DeviceType::Mobile];
        yield 'Android タブレット' => ['Mozilla/5.0 (Linux; Android 15; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36', DeviceType::Tablet];
        yield 'iPad' => ['Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', DeviceType::Tablet];
        yield 'Discord のプレビュー' => ['Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)', DeviceType::Bot];
        yield 'curl' => ['curl/8.9.1', DeviceType::Bot];
        yield '空' => ['', DeviceType::Unknown];
        yield 'なし' => [null, DeviceType::Unknown];
    }

    #[DataProvider('userAgents')]
    public function test_detects_device_type(?string $userAgent, DeviceType $expected): void
    {
        $this->assertSame($expected, (new DeviceDetector)->detect($userAgent));
    }
}
