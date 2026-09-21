<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UpdateRunStatus;
use App\Models\AppSetting;
use App\Models\UpdateRun;
use App\Models\User;
use App\Services\Update\AppVersion;
use App\Services\Update\ArtisanProcess;
use App\Services\Update\BackupManager;
use App\Services\Update\CodeTree;
use App\Services\Update\DatabaseBackup;
use App\Services\Update\DiscordWebhookNotifier;
use App\Services\Update\GitHubReleaseClient;
use App\Services\Update\ReleaseZipStrategy;
use App\Services\Update\UpdateException;
use App\Services\Update\Updater;
use App\Services\Update\UpdateSettings;
use App\Support\ExternalServiceKeys;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use ZipArchive;

/** 自動アップデート（requirements.md 7 章）: 配布用 zip 方式での更新・ロールバック */
final class AutoUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://discord.com/api/webhooks/123456/webhook-token';

    private string $root;

    private string $basePath;

    private string $backupPath;

    /** @var list<list<string>> */
    private array $artisanCalls = [];

    private bool $healthCheckFails = false;

    private int $databaseRestores = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/update-'.Str::random(8));
        $this->basePath = $this->root.'/app-root';
        $this->backupPath = $this->root.'/backups';

        // 現在のバージョン（v26.9.0）のコード
        $this->writeTree($this->basePath, [
            'VERSION' => "v26.9.0\n",
            'artisan' => 'old artisan',
            'app/Old.php' => '<?php // old',
            'vendor/autoload.php' => '<?php // old vendor',
            'public/index.php' => '<?php // old public',
        ]);

        AppSetting::store(AppSetting::UPDATE_GITHUB_TOKEN, 'github-token-value', encrypt: true);
        AppSetting::store(AppSetting::DISCORD_WEBHOOK_URL, self::WEBHOOK, encrypt: true);
        app(ExternalServiceKeys::class)->forget();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        Mockery::close();

        parent::tearDown();
    }

    public function test_applies_new_release_after_backup_and_notifies(): void
    {
        $this->fakeGitHub('v26.9.1');

        $outcome = $this->updater()->run();

        $this->assertSame(UpdateRunStatus::Succeeded, $outcome->status);
        $this->assertFileExists($this->basePath.'/app/New.php');
        $this->assertFileDoesNotExist($this->basePath.'/app/Old.php');
        $this->assertSame("v26.9.1\n", File::get($this->basePath.'/VERSION'));

        // 新しいコードでマイグレーションとヘルスチェックを実行する
        $this->assertSame([['optimize:clear'], ['migrate', '--force'], ['app:health-check']], $this->artisanCalls);

        // 更新前のバックアップ（DB ダンプとコード一式）
        $backups = File::directories($this->backupPath);
        $this->assertCount(1, $backups);
        $this->assertFileExists($backups[0].'/database.sql');
        $this->assertFileExists($backups[0].'/code.zip');

        $run = UpdateRun::query()->sole();
        $this->assertSame('v26.9.0', $run->from_version);
        $this->assertSame('v26.9.1', $run->to_version);
        $this->assertSame(UpdateRunStatus::Succeeded, $run->status);

        Http::assertSent(fn ($request): bool => $request->url() === self::WEBHOOK && str_contains((string) $request['content'], '成功'));

        // 「application/vnd.github+json, application/octet-stream」になると、GitHub は zip ではなく JSON を返す（v26.9.2 の不具合）
        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/releases/assets/')
            && $request->header('Accept') === ['application/octet-stream']);
    }

    /** GitHub が zip の代わりに添付ファイルの説明（JSON）を返した場合は、更新せずに元へ戻す */
    public function test_a_download_that_is_not_a_zip_is_rolled_back(): void
    {
        $this->fakeGitHub('v26.9.1', assetResponse: Http::response(['url' => 'https://api.github.com/repos/x/y/releases/assets/1', 'name' => 'url-shortener-v26.9.1.zip'], 200, ['Content-Type' => 'application/json']));

        $outcome = $this->updater()->run();

        $this->assertSame(UpdateRunStatus::RolledBack, $outcome->status);
        $this->assertStringContainsString('zip 以外のデータが返りました', $outcome->message);
        $this->assertSame('v26.9.0
', File::get($this->basePath.'/VERSION'));
        $this->assertFileExists($this->basePath.'/app/Old.php');
    }

    public function test_rolls_back_code_and_database_when_health_check_fails(): void
    {
        $this->fakeGitHub('v26.9.1');
        $this->healthCheckFails = true;

        $outcome = $this->updater()->run();

        $this->assertSame(UpdateRunStatus::RolledBack, $outcome->status);
        $this->assertFileExists($this->basePath.'/app/Old.php');
        $this->assertFileDoesNotExist($this->basePath.'/app/New.php');
        $this->assertSame("v26.9.0\n", File::get($this->basePath.'/VERSION'));
        $this->assertSame('<?php // old vendor', File::get($this->basePath.'/vendor/autoload.php'));
        $this->assertSame(1, $this->databaseRestores);

        $this->assertSame(UpdateRunStatus::RolledBack, UpdateRun::query()->sole()->status);
        Http::assertSent(fn ($request): bool => $request->url() === self::WEBHOOK && str_contains((string) $request['content'], 'ロールバック済み'));
    }

    public function test_does_nothing_when_already_up_to_date(): void
    {
        $this->fakeGitHub('v26.9.0');

        $outcome = $this->updater()->run();

        $this->assertNull($outcome->status);
        $this->assertSame(0, UpdateRun::query()->count());
        $this->assertSame([], $this->artisanCalls);
        Http::assertNotSent(fn ($request): bool => $request->url() === self::WEBHOOK);
    }

    public function test_respects_disabled_setting_unless_run_manually(): void
    {
        AppSetting::store(AppSetting::UPDATE_ENABLED, false);
        $this->fakeGitHub('v26.9.1');

        $this->assertNull($this->updater()->run()->status);
        $this->assertSame(UpdateRunStatus::Succeeded, $this->updater()->run(manual: true)->status);
    }

    public function test_updates_a_public_repository_without_a_token(): void
    {
        AppSetting::query()->where('key', AppSetting::UPDATE_GITHUB_TOKEN)->delete();
        app(ExternalServiceKeys::class)->forget();
        $this->fakeGitHub('v26.9.1');

        $this->assertSame(UpdateRunStatus::Succeeded, $this->updater()->run()->status);

        // トークンが無いときは Authorization ヘッダーを付けない
        Http::assertSent(static fn (Request $request): bool => ! $request->hasHeader('Authorization'));
    }

    public function test_skips_when_the_current_version_is_unknown(): void
    {
        File::delete($this->basePath.'/VERSION');

        $this->assertStringContainsString('バージョンを判定できない', $this->updater()->run()->message);
    }

    public function test_keeps_only_three_backup_generations(): void
    {
        foreach (['20260101_000000_v26.1.0', '20260201_000000_v26.2.0', '20260301_000000_v26.3.0', '20260401_000000_v26.4.0'] as $name) {
            File::ensureDirectoryExists($this->backupPath.'/'.$name);
        }

        $this->fakeGitHub('v26.9.1');
        $this->updater()->run();

        $remaining = array_map('basename', File::directories($this->backupPath));
        $this->assertCount(3, $remaining);
        $this->assertNotContains('20260101_000000_v26.1.0', $remaining);
        $this->assertNotContains('20260201_000000_v26.2.0', $remaining);
    }

    public function test_admin_can_run_the_update_from_the_dashboard(): void
    {
        // SSH が使えない環境向け。自動アップデートが無効に設定されていても実行する
        AppSetting::store(AppSetting::UPDATE_ENABLED, false);
        $this->fakeGitHub('v26.9.1');
        $this->app->instance(Updater::class, $this->updater());
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get($this->dashboardUrl('/admin/updates'))->assertOk()->assertSee('今すぐ更新する');

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/updates'))
            ->post($this->dashboardUrl('/admin/updates/run'))
            ->assertRedirect($this->dashboardUrl('/admin/updates'))
            ->assertSessionHas('notice', static fn (string $message): bool => str_contains($message, 'v26.9.1'));

        $this->assertFileExists($this->basePath.'/app/New.php');
        $this->assertSame(UpdateRunStatus::Succeeded, UpdateRun::query()->sole()->status);
    }

    public function test_a_failed_update_from_the_dashboard_is_reported_on_the_screen(): void
    {
        $this->fakeGitHub('v26.9.1');
        $this->healthCheckFails = true;
        $this->app->instance(Updater::class, $this->updater());

        $this->actingAs(User::factory()->admin()->create())->from($this->dashboardUrl('/admin/updates'))
            ->post($this->dashboardUrl('/admin/updates/run'))
            ->assertSessionHas('error');

        $this->assertSame(UpdateRunStatus::RolledBack, UpdateRun::query()->sole()->status);
    }

    public function test_members_cannot_run_the_update(): void
    {
        User::factory()->admin()->create();

        $this->actingAs(User::factory()->create())
            ->post($this->dashboardUrl('/admin/updates/run'))
            ->assertForbidden();

        $this->assertSame(0, UpdateRun::query()->count());
    }

    private function updater(): Updater
    {
        /** @var DatabaseBackup&MockInterface $database */
        $database = Mockery::mock(DatabaseBackup::class);
        $database->shouldReceive('dump')->andReturnUsing(static fn (string $path) => File::put($path, '-- dump'));
        $database->shouldReceive('restore')->andReturnUsing(function (): void {
            $this->databaseRestores++;
        });

        /** @var ArtisanProcess&MockInterface $artisan */
        $artisan = Mockery::mock(ArtisanProcess::class);
        $artisan->shouldReceive('run')->andReturnUsing(function (array $arguments): string {
            $this->artisanCalls[] = $arguments;

            if ($arguments === ['app:health-check'] && $this->healthCheckFails) {
                throw new UpdateException('php artisan app:health-check が失敗しました。');
            }

            return '';
        });

        $code = new CodeTree;
        $github = new GitHubReleaseClient;

        return new Updater(
            settings: app(UpdateSettings::class),
            version: new AppVersion($this->basePath),
            github: $github,
            backups: new BackupManager($this->basePath, $this->backupPath, 3, $database, $code),
            database: $database,
            strategy: new ReleaseZipStrategy($this->basePath, $this->root.'/work', $github, $code),
            artisan: $artisan,
            notifier: app(DiscordWebhookNotifier::class),
        );
    }

    private function fakeGitHub(string $tag, ?PromiseInterface $assetResponse = null): void
    {
        $package = $this->root.'/package.zip';
        $this->makeZip($package, [
            'url-shortener/VERSION' => "{$tag}\n",
            'url-shortener/artisan' => 'new artisan',
            'url-shortener/app/New.php' => '<?php // new',
            'url-shortener/vendor/autoload.php' => '<?php // new vendor',
            'url-shortener/public/index.php' => '<?php // new public',
        ]);

        Http::fake([
            'api.github.com/repos/*/releases/latest' => Http::response([
                'tag_name' => $tag,
                'html_url' => "https://github.com/choko1229/URL-Shortener/releases/tag/{$tag}",
                'assets' => [['name' => "url-shortener-{$tag}.zip", 'url' => 'https://api.github.com/repos/choko1229/URL-Shortener/releases/assets/1']],
            ]),
            'api.github.com/repos/*/releases/assets/*' => $assetResponse ?? Http::response((string) file_get_contents($package), 200, ['Content-Type' => 'application/octet-stream']),
            'discord.com/api/webhooks/*' => Http::response(null, 204),
        ]);
    }

    /** @param  array<string, string>  $files */
    private function writeTree(string $base, array $files): void
    {
        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($base.'/'.$path));
            File::put($base.'/'.$path, $contents);
        }
    }

    /** @param  array<string, string>  $files */
    private function makeZip(string $path, array $files): void
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
    }
}
