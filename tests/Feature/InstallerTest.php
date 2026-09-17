<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Installer\DatabaseConnectionTester;
use App\Installer\DatabaseTestResult;
use App\Installer\InstallationState;
use App\Models\AppSetting;
use App\Models\ReservedWord;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

final class InstallerTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'http://setup.example.test';

    private string $workDirectory;

    private InstallationState $state;

    protected function setUp(): void
    {
        parent::setUp();

        // 実際の .env やインストール記録に触れないよう、一時ディレクトリに差し替える
        $this->workDirectory = storage_path('framework/testing/installer-'.Str::random(8));
        File::ensureDirectoryExists($this->workDirectory);
        File::put($this->workDirectory.'/.env', "APP_NAME=chok.ooo\nSESSION_DRIVER=file\nDB_CONNECTION=mysql\n");
        $this->app->useEnvironmentPath($this->workDirectory);

        $this->state = new InstallationState($this->workDirectory.'/installed.json');
        $this->app->instance(InstallationState::class, $this->state);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workDirectory);

        parent::tearDown();
    }

    public function test_redirects_every_page_to_installer_until_installed(): void
    {
        $this->get(self::SITE.'/')->assertRedirect(self::SITE.'/install');
        $this->get(self::SITE.'/some/unknown/path')->assertRedirect(self::SITE.'/install');
    }

    public function test_installer_is_not_found_after_installation(): void
    {
        $this->state->markInstalled();

        $this->get(self::SITE.'/install')->assertNotFound();
        $this->get(self::SITE.'/install/database')->assertNotFound();
    }

    public function test_requirements_step_blocks_when_env_file_is_exposed(): void
    {
        Http::fake([
            '*/.env' => Http::response('APP_KEY=base64:secret', 200),
            '*' => Http::response('Not Found', 404),
        ]);

        $this->get(self::SITE.'/install')
            ->assertOk()
            ->assertSee('外部から閲覧できる状態です')
            ->assertSee(self::SITE.'/.env')
            ->assertDontSee('次へ進む');

        $this->post(self::SITE.'/install')
            ->assertRedirect(self::SITE.'/install')
            ->assertSessionHas('error');
    }

    public function test_requirements_step_passes_when_protected_paths_return_other_pages(): void
    {
        // 未インストール時の /.env はセットアップ画面（200）を返すが、ファイルの中身ではないので問題なし
        Http::fake(['*' => Http::response('<!DOCTYPE html><title>セットアップ</title>', 200)]);

        $this->get(self::SITE.'/install')
            ->assertOk()
            ->assertDontSee('外部から閲覧できる状態です')
            ->assertDontSee('name="confirmed"', false)
            ->assertSee('次へ進む');
    }

    public function test_requirements_step_requires_manual_confirmation_when_probe_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->get(self::SITE.'/install')
            ->assertOk()
            ->assertSee('サーバー自身から確認できませんでした')
            ->assertSee('name="confirmed"', false);

        $this->post(self::SITE.'/install')->assertSessionHasErrors('confirmed');

        $this->post(self::SITE.'/install', ['confirmed' => '1'])
            ->assertRedirect(self::SITE.'/install/database');
    }

    public function test_database_step_requires_requirements_step(): void
    {
        $this->get(self::SITE.'/install/database')->assertRedirect(self::SITE.'/install');
    }

    public function test_database_step_shows_error_and_does_not_keep_password_when_connection_fails(): void
    {
        $this->mock(DatabaseConnectionTester::class, function (MockInterface $mock): void {
            $mock->shouldReceive('test')->once()->andReturn(DatabaseTestResult::failed('ユーザー名またはパスワードが正しくありません。'));
        });

        $this->withSession(['install.requirements_confirmed' => true])
            ->from(self::SITE.'/install/database')
            ->post(self::SITE.'/install/database', $this->databaseInput())
            ->assertRedirect(self::SITE.'/install/database')
            ->assertSessionHas('error', 'ユーザー名またはパスワードが正しくありません。')
            ->assertSessionHas('_old_input.db_username', 'chok')
            ->assertSessionMissing('_old_input.db_password');

        $this->assertArrayNotHasKey('DB_HOST', $this->envValues());
    }

    public function test_database_step_saves_credentials_to_env_file(): void
    {
        $this->mock(DatabaseConnectionTester::class, function (MockInterface $mock): void {
            $mock->shouldReceive('test')->once()->andReturn(DatabaseTestResult::succeeded('8.0.36'));
        });

        $this->withSession(['install.requirements_confirmed' => true])
            ->post(self::SITE.'/install/database', $this->databaseInput(['db_password' => 'p@ss "word" $x #1']))
            ->assertRedirect(self::SITE.'/install/site')
            ->assertSessionHas('install.database_configured', true);

        $values = $this->envValues();
        $this->assertSame('mysql.example.jp', $values['DB_HOST']);
        $this->assertSame('3306', $values['DB_PORT']);
        $this->assertSame('p@ss "word" $x #1', $values['DB_PASSWORD']);
    }

    public function test_site_step_completes_installation(): void
    {
        $this->mock(DatabaseConnectionTester::class, function (MockInterface $mock): void {
            $mock->shouldReceive('canConnectWithCurrentConfiguration')->andReturn(true);
        });

        $this->withSession(['install.database_configured' => true])
            ->post(self::SITE.'/install/site', [
                'main_domain' => 'Chok.ooo',
                'dashboard_domain' => 'dash.chok.ooo',
                'api_domain' => 'api.chok.ooo',
                'redirect_domain' => 'redirect.chok.ooo',
                'discord_client_id' => '123456789012345678',
                'discord_client_secret' => 'abcdefghijklmnopqrstuvwxyz_12345',
            ])
            ->assertOk()
            ->assertSee('セットアップが完了しました')
            ->assertSee('http://chok.ooo');

        $this->assertTrue($this->state->isInstalled());
        $this->assertGreaterThan(0, ReservedWord::query()->count());
        $this->assertSame('123456789012345678', AppSetting::valueFor(AppSetting::DISCORD_CLIENT_ID));
        $this->assertSame('abcdefghijklmnopqrstuvwxyz_12345', AppSetting::valueFor(AppSetting::DISCORD_CLIENT_SECRET));
        $this->assertTrue(AppSetting::query()->where('key', AppSetting::DISCORD_CLIENT_SECRET)->value('is_encrypted'));
        $this->assertStringNotContainsString(
            'abcdefghijklmnopqrstuvwxyz_12345',
            (string) AppSetting::query()->where('key', AppSetting::DISCORD_CLIENT_SECRET)->value('value'),
        );

        $values = $this->envValues();
        $this->assertSame('chok.ooo', $values['SHORTENER_MAIN_DOMAIN']);
        $this->assertSame('dash.chok.ooo', $values['SHORTENER_DASHBOARD_DOMAIN']);
        $this->assertSame('http://chok.ooo', $values['APP_URL']);
        $this->assertSame('.chok.ooo', $values['SESSION_DOMAIN']);
        $this->assertSame('database', $values['SESSION_DRIVER']);
    }

    public function test_site_step_rejects_duplicate_domains(): void
    {
        $this->mock(DatabaseConnectionTester::class, function (MockInterface $mock): void {
            $mock->shouldReceive('canConnectWithCurrentConfiguration')->andReturn(true);
        });

        $this->withSession(['install.database_configured' => true])
            ->post(self::SITE.'/install/site', [
                'main_domain' => 'chok.ooo',
                'dashboard_domain' => 'chok.ooo',
                'api_domain' => 'https://api.chok.ooo',
                'redirect_domain' => 'redirect.chok.ooo',
            ])
            ->assertSessionHasErrors(['main_domain', 'api_domain']);

        $this->assertFalse($this->state->isInstalled());
    }

    /** @return array<string, string> */
    private function databaseInput(array $overrides = []): array
    {
        return $overrides + [
            'db_host' => 'mysql.example.jp',
            'db_port' => '3306',
            'db_database' => 'chok_ooo',
            'db_username' => 'chok',
            'db_password' => 'secret',
        ];
    }

    /** @return array<string, string|null> */
    private function envValues(): array
    {
        return Dotenv::parse((string) File::get($this->workDirectory.'/.env'));
    }
}
