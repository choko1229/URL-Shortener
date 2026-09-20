<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ShortUrl;
use App\Models\User;
use App\Services\GeoIp\GeoIpDatabase;
use App\Services\GeoIp\GeoIpDatabaseUpdater;
use App\Services\GeoIp\GeoIpSource;
use App\Services\Redirect\CountryResolver;
use App\Services\Tasks\PeriodicTasks;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\MmdbFixture;
use Tests\TestCase;

/** 国判定のデータベース（DB-IP IP to Country Lite）の自動取得と毎月の更新 */
final class GeoIpDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-09-20 10:00:00';

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/geoip-'.Str::random(8));
        config([
            'shortener.geoip.manual_database' => $this->directory.'/GeoLite2-Country.mmdb',
            'shortener.geoip.auto_database' => $this->directory.'/dbip-country-lite.mmdb',
            'shortener.geoip.auto_update' => true,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_downloads_database_when_missing_and_uses_it_for_country_detection(): void
    {
        Http::fake(['download.db-ip.com/*' => Http::response(self::gzippedDatabase('US'))]);
        $updater = $this->app->make(GeoIpDatabaseUpdater::class);

        $this->assertTrue($updater->isDue(self::now()));

        $result = $updater->update(self::now());

        $this->assertTrue($result->successful, $result->message);
        $this->assertSame('2026-09', $updater->installedRelease());
        $this->assertFileExists($this->directory.'/dbip-country-lite.mmdb');
        $this->assertSame(['dbip-country-lite.mmdb'], array_map('basename', File::files($this->directory)));
        Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://download.db-ip.com/free/dbip-country-lite-2026-09.mmdb.gz');

        $this->assertSame(GeoIpSource::DbIp, $this->app->make(GeoIpDatabase::class)->source());
        $this->app->forgetScopedInstances();
        $this->assertSame('US', $this->app->make(CountryResolver::class)->countryCode('8.8.8.8'));

        // 同じ月の間は取得し直さない
        $this->assertFalse($updater->isDue(self::now()->addDays(3)));
    }

    public function test_uses_previous_month_until_the_new_release_is_published(): void
    {
        Http::fake([
            'download.db-ip.com/free/dbip-country-lite-2026-09.mmdb.gz' => Http::response('Not Found', 404),
            'download.db-ip.com/free/dbip-country-lite-2026-08.mmdb.gz' => Http::response(self::gzippedDatabase('US')),
        ]);
        $updater = $this->app->make(GeoIpDatabaseUpdater::class);

        $this->assertTrue($updater->update(self::now())->successful);
        $this->assertSame('2026-08', $updater->installedRelease());

        // 新しい月の版を待つ間は 1 日 1 回だけ確認する
        $this->assertFalse($updater->isDue(self::now()->addHours(23)));
        $this->assertTrue($updater->isDue(self::now()->addHours(24)));
    }

    public function test_keeps_current_database_when_the_download_is_broken(): void
    {
        $this->installDatabase('JP', '2026-08');
        Http::fake(['download.db-ip.com/*' => Http::response(gzencode('this is not a database'))]);
        $updater = $this->app->make(GeoIpDatabaseUpdater::class);

        $result = $updater->update(self::now());

        $this->assertFalse($result->successful);
        $this->assertSame('2026-08', $updater->installedRelease());
        $this->assertSame('JP', (new CountryResolver($this->directory.'/dbip-country-lite.mmdb'))->countryCode('8.8.8.8'));
        $this->assertSame(['dbip-country-lite.mmdb'], array_map('basename', File::files($this->directory)));
    }

    public function test_retries_hourly_while_the_database_is_missing(): void
    {
        Http::fake(['download.db-ip.com/*' => Http::response('Server Error', 500)]);
        $updater = $this->app->make(GeoIpDatabaseUpdater::class);

        $this->assertFalse($updater->update(self::now())->successful);

        $this->assertFalse($updater->isDue(self::now()->addMinutes(59)));
        $this->assertTrue($updater->isDue(self::now()->addMinutes(60)));
    }

    public function test_manually_placed_geolite2_takes_precedence(): void
    {
        File::ensureDirectoryExists($this->directory);
        File::put($this->directory.'/GeoLite2-Country.mmdb', MmdbFixture::build('8.8.8.0/24', 'CA', 'GeoLite2-Country'));
        Http::fake();
        $updater = $this->app->make(GeoIpDatabaseUpdater::class);

        $this->assertFalse($updater->isDue(self::now()));
        $this->assertTrue($updater->update(self::now())->successful);
        Http::assertNothingSent();

        $this->assertSame(GeoIpSource::MaxMind, $this->app->make(GeoIpDatabase::class)->source());
        $this->app->forgetScopedInstances();
        $this->assertSame('CA', $this->app->make(CountryResolver::class)->countryCode('8.8.8.8'));
    }

    public function test_automatic_download_can_be_disabled(): void
    {
        config(['shortener.geoip.auto_update' => false]);

        $this->assertFalse($this->app->make(GeoIpDatabaseUpdater::class)->isDue(self::now()));
    }

    public function test_periodic_tasks_download_the_database_right_after_installation(): void
    {
        AppSetting::store(AppSetting::UPDATE_ENABLED, false);
        Http::fake(['download.db-ip.com/*' => Http::response(self::gzippedDatabase('US'))]);

        $report = $this->app->make(PeriodicTasks::class)->runDue('web', self::now());

        $this->assertNotNull($report?->geoIp);
        $this->assertTrue($report->geoIp->successful);
        $this->assertFileExists($this->directory.'/dbip-country-lite.mmdb');
    }

    public function test_admin_can_see_status_and_update_now(): void
    {
        $admin = User::factory()->admin()->create();
        Http::fake(['download.db-ip.com/*' => Http::response(self::gzippedDatabase('US'))]);

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/services'))
            ->assertOk()
            ->assertSee('まだ取得していません')
            ->assertSee('今すぐ取得する');

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/services'))
            ->post($this->dashboardUrl('/admin/services/geoip'))
            ->assertRedirect($this->dashboardUrl('/admin/services'))
            ->assertSessionHas('notice');

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/services'))
            ->assertSee('DB-IP IP to Country Lite')
            ->assertSee('版');

        $this->actingAs(User::factory()->create())
            ->post($this->dashboardUrl('/admin/services/geoip'))
            ->assertForbidden();
    }

    public function test_link_statistics_credit_db_ip_when_its_data_is_used(): void
    {
        $user = User::factory()->create();
        $link = ShortUrl::factory()->for($user)->create();

        $this->actingAs($user)->get($this->dashboardUrl('/links/'.$link->id))
            ->assertOk()
            ->assertDontSee('IP Geolocation by DB-IP');

        $this->installDatabase('US', '2026-09');

        $this->actingAs($user)->get($this->dashboardUrl('/links/'.$link->id))
            ->assertSee('IP Geolocation by DB-IP');
    }

    private function installDatabase(string $isoCode, string $release): void
    {
        File::ensureDirectoryExists($this->directory);
        File::put($this->directory.'/dbip-country-lite.mmdb', MmdbFixture::build('8.8.8.0/24', $isoCode));
        AppSetting::store(GeoIpDatabaseUpdater::RELEASE_KEY, $release);
    }

    private static function gzippedDatabase(string $isoCode): string
    {
        return (string) gzencode(MmdbFixture::build('8.8.8.0/24', $isoCode));
    }

    private static function now(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::NOW, 'UTC');
    }
}
