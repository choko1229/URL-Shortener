<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Services\Tasks\PeriodicTasks;
use App\Services\Tasks\WebCron;
use App\Services\Update\ArtisanProcess;
use App\Services\Update\PhpBinaryResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

/** cron を登録しなくても動く定期処理（WP-Cron 方式）と、PHP（CLI）が無い環境での artisan 実行 */
final class PeriodicTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 更新処理そのものは行わない（無効なら即座に終わる）
        AppSetting::store(AppSetting::UPDATE_ENABLED, false);
    }

    public function test_is_due_once_a_day_after_four_in_japan_time(): void
    {
        $tasks = $this->app->make(PeriodicTasks::class);

        $this->assertTrue($tasks->isDue(self::jst('2026-09-19 03:00')));

        $tasks->runDue('web', self::jst('2026-09-19 03:00'));
        $this->assertFalse($tasks->isDue(self::jst('2026-09-19 03:59')));
        $this->assertTrue($tasks->isDue(self::jst('2026-09-19 04:00')));

        $tasks->runDue('web', self::jst('2026-09-19 04:10'));
        $this->assertFalse($tasks->isDue(self::jst('2026-09-19 23:59')));
        $this->assertFalse($tasks->isDue(self::jst('2026-09-20 03:59')));
        $this->assertTrue($tasks->isDue(self::jst('2026-09-20 04:00')));
    }

    public function test_run_due_records_time_and_trigger_and_does_not_run_twice(): void
    {
        $tasks = $this->app->make(PeriodicTasks::class);
        $now = self::jst('2026-09-19 05:00');

        $this->assertNotNull($tasks->runDue('web', $now));
        $this->assertNull($tasks->runDue('cron', $now->addHour()));

        $this->assertTrue($tasks->lastRunAt()?->equalTo($now));
        $this->assertSame('web', $tasks->lastTrigger());
    }

    public function test_command_records_cron_heartbeat_and_runs_tasks(): void
    {
        $this->artisan('app:periodic-tasks')->assertSuccessful();

        $tasks = $this->app->make(PeriodicTasks::class);
        $this->assertTrue($tasks->cronIsActive(CarbonImmutable::now()));
        $this->assertFalse($tasks->cronIsActive(CarbonImmutable::now()->addMinutes(11)));
        $this->assertSame('cron', $tasks->lastTrigger());
    }

    public function test_page_access_triggers_web_cron_when_due(): void
    {
        config(['shortener.web_cron' => true]);
        $this->expectWebCronTriggers(1);

        $this->get($this->mainUrl())->assertOk();
        // 確認は数分に 1 回だけ
        $this->get($this->mainUrl())->assertOk();
    }

    public function test_page_access_does_not_trigger_web_cron_when_cron_is_active_or_not_due(): void
    {
        config(['shortener.web_cron' => true]);
        $this->expectWebCronTriggers(0);

        $tasks = $this->app->make(PeriodicTasks::class);
        $tasks->recordCronHeartbeat(CarbonImmutable::now());
        $this->get($this->mainUrl())->assertOk();

        AppSetting::query()->where('key', PeriodicTasks::CRON_HEARTBEAT_KEY)->delete();
        $tasks->runDue('cron', CarbonImmutable::now());
        cache()->flush();
        $this->get($this->mainUrl())->assertOk();
    }

    public function test_web_cron_can_be_disabled(): void
    {
        config(['shortener.web_cron' => false]);
        $this->expectWebCronTriggers(0);

        $this->get($this->mainUrl())->assertOk();
    }

    public function test_web_cron_sends_signed_loopback_request(): void
    {
        Http::fake();
        $webCron = $this->app->make(WebCron::class);

        $webCron->trigger(CarbonImmutable::now());

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/_cron')
            && $request['token'] === $webCron->token());
    }

    public function test_cron_endpoint_requires_valid_token(): void
    {
        $this->post($this->mainUrl('/_cron'), ['token' => 'wrong'])->assertNotFound();
        $this->assertNull($this->app->make(PeriodicTasks::class)->lastRunAt());

        $token = $this->app->make(WebCron::class)->token();
        $this->post($this->mainUrl('/_cron'), ['token' => $token])->assertNoContent();

        $tasks = $this->app->make(PeriodicTasks::class);
        $this->assertNotNull($tasks->lastRunAt());
        $this->assertSame('web', $tasks->lastTrigger());
    }

    public function test_artisan_runs_in_process_when_php_cli_is_unavailable(): void
    {
        $resolver = new class(null) extends PhpBinaryResolver
        {
            public function resolve(): ?string
            {
                return null;
            }
        };

        $process = new ArtisanProcess(base_path(), $resolver, $this->app->make(Kernel::class));

        $this->assertStringContainsString('app:periodic-tasks', $process->run(['list']));
    }

    public function test_php_binary_resolver_prefers_configured_path_and_falls_back_to_current_cli(): void
    {
        $this->assertSame('/opt/php/bin/php', (new PhpBinaryResolver('/opt/php/bin/php'))->resolve());
        $this->assertSame(PHP_BINARY, (new PhpBinaryResolver(null))->resolve());
    }

    private function expectWebCronTriggers(int $times): void
    {
        $this->mock(WebCron::class, function (MockInterface $mock) use ($times): void {
            $mock->shouldReceive('trigger')->times($times);
        });
    }

    private static function jst(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'Asia/Tokyo')->utc();
    }
}
