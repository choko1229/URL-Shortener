<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Update\Version;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function test_parses_release_tags(): void
    {
        $this->assertSame('v26.9.0', Version::tryParse('v26.9.0')?->toString());
        $this->assertSame('v26.10.12', Version::tryParse("26.10.12\n")?->toString());
        $this->assertNull(Version::tryParse('v26.9'));
        $this->assertNull(Version::tryParse('main'));
        $this->assertNull(Version::tryParse(null));
    }

    public function test_compares_versions_numerically(): void
    {
        $this->assertTrue(Version::tryParse('v26.10.0')?->isNewerThan(Version::tryParse('v26.9.9')));
        $this->assertTrue(Version::tryParse('v26.9.10')?->isNewerThan(Version::tryParse('v26.9.2')));
        $this->assertTrue(Version::tryParse('v27.1.0')?->isNewerThan(Version::tryParse('v26.12.5')));
        $this->assertFalse(Version::tryParse('v26.9.0')?->isNewerThan(Version::tryParse('v26.9.0')));
        $this->assertFalse(Version::tryParse('v26.8.9')?->isNewerThan(Version::tryParse('v26.9.0')));
    }
}
