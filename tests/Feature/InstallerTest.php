<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Installer\DatabaseConnectionSwitcher;
use App\Installer\DatabaseConnectionTester;
use App\Installer\DatabaseTestResult;
use App\Installer\InstallationState;
use App\Installer\RequirementChecker;
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

        // テストは SQLite で動かすため、入力された MySQL への切り替えは行わない
        $this->mock(DatabaseConnectionSwitcher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('use')->byDefault();
        });
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
        $this->get(self::SITE.'/install/ping')->assertNotFound();
    }

    public function test_setup_page_asks_only_for_database_and_prefills_domains(): void
    {
        Http::fake(['*' => Http::response('<!DOCTYPE html><title>セットアップ</title>', 200)]);

        $this->get('http://www.chok.example/install')
            ->assertOk()
            ->assertSee('name="db_host"', false)
            ->assertSee('name="db_password"', false)
            ->assertSee('value="chok.example"', false)
            ->assertSee('value="dash.chok.example"', false)
            ->assertDontSee('discord_client_id')
            ->assertSee('セットアップを完了する');
    }

    public function test_setup_page_blocks_when_env_file_is_exposed(): void
    {
        Http::fake([
            '*/.env' => Http::response('APP_KEY=base64:secret', 200),
            '*' => Http::response('Not Found', 404),
        ]);

        $this->get(self::SITE.'/install')
            ->assertOk()
            ->assertSee('外部から閲覧できる状態です')
            ->assertSee(self::SITE.'/.env')
            ->assertDontSee('セットアップを完了する');

        $this->post(self::SITE.'/install', $this->input())
            ->assertRedirect(self::SITE.'/install')
            ->assertSessionHas('error');

        $this->assertFalse($this->state->isInstalled());
    }

    public function test_setup_page_passes_when_protected_paths_return_other_pages(): void
    {
        // 未インストール時の /.env はセットアップ画面（200）を返すが、ファイルの中身ではないので問題なし
        Http::fake(['*' => Http::response('<!DOCTYPE html><title>セットアップ</title>', 200)]);

        $this->get(self::SITE.'/install')
            ->assertOk()
            ->assertDontSee('外部から閲覧できる状態です')
            ->assertDontSee('name="confirmed"', false)
            // サブドメインは応答が違うため「向いていない」扱い（注意のみで進める）
            ->assertSee('向いていないか')
            ->assertSee('セットアップを完了する');
    }

    public function test_subdomain_check_passes_when_every_subdomain_serves_this_folder(): void
    {
        $token = $this->app->make(RequirementChecker::class)->pingToken();
        Http::fake([
            '*/install/ping' => Http::response($token, 200),
            '*' => Http::response('Not Found', 404),
        ]);

        $this->get(self::SITE.'/install')
            ->assertOk()
            ->assertSee('サブドメインの向き先')
            ->assertDontSee('向いていないか');
    }

    public function test_ping_returns_token_of_this_installation(): void
    {
        $token = $this->app->make(RequirementChecker::class)->pingToken();

        $this->get(self::SITE.'/install/ping')
            ->assertOk()
            ->assertSeeText($token);
    }

    public function test_requires_manual_confirmation_when_probe_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->mockConnectionTester(DatabaseTestResult::succeeded('8.0.36'));

        $this->get(self::SITE.'/install')
            ->assertOk()
            ->assertSee('サーバー自身から確認できませんでした')
            ->assertSee('name="confirmed"', false);

        $this->post(self::SITE.'/install', $this->input())->assertSessionHasErrors('confirmed');
        $this->assertFalse($this->state->isInstalled());

        $this->post(self::SITE.'/install', $this->input(['confirmed' => '1']))
            ->assertOk()
            ->assertSee('セットアップが完了しました');
        $this->assertTrue($this->state->isInstalled());
    }

    public function test_shows_error_and_does_not_keep_password_when_connection_fails(): void
    {
        Http::fake(['*' => Http::response('Not Found', 404)]);
        $this->mockConnectionTester(DatabaseTestResult::failed('ユーザー名またはパスワードが正しくありません。'));

        $this->post(self::SITE.'/install', $this->input())
            ->assertRedirect(self::SITE.'/install')
            ->assertSessionHas('error', 'ユーザー名またはパスワードが正しくありません。')
            ->assertSessionHas('_old_input.db_username', 'chok')
            ->assertSessionMissing('_old_input.db_password');

        $this->assertArrayNotHasKey('DB_HOST', $this->envValues());
        $this->assertFalse($this->state->isInstalled());
    }

    public function test_completes_installation_with_database_credentials_only(): void
    {
        Http::fake(['*' => Http::response('Not Found', 404)]);
        $this->mockConnectionTester(DatabaseTestResult::succeeded('8.0.36'));

        $this->post(self::SITE.'/install', $this->input([
            'db_password' => 'p@ss "word" $x #1',
            'main_domain' => 'Chok.ooo',
            'dashboard_domain' => 'dash.chok.ooo',
            'api_domain' => 'api.chok.ooo',
            'redirect_domain' => 'redirect.chok.ooo',
        ]))
            ->assertOk()
            ->assertSee('セットアップが完了しました')
            ->assertSee('http://dash.chok.ooo/login');

        $this->assertTrue($this->state->isInstalled());
        $this->assertGreaterThan(0, ReservedWord::query()->count());

        $values = $this->envValues();
        $this->assertSame('mysql.example.jp', $values['DB_HOST']);
        $this->assertSame('3306', $values['DB_PORT']);
        $this->assertSame('p@ss "word" $x #1', $values['DB_PASSWORD']);
        $this->assertSame('chok.ooo', $values['SHORTENER_MAIN_DOMAIN']);
        $this->assertSame('dash.chok.ooo', $values['SHORTENER_DASHBOARD_DOMAIN']);
        $this->assertSame('http://chok.ooo', $values['APP_URL']);
        $this->assertSame('.chok.ooo', $values['SESSION_DOMAIN']);
        $this->assertSame('database', $values['SESSION_DRIVER']);
    }

    public function test_switches_to_entered_database_before_creating_tables(): void
    {
        Http::fake(['*' => Http::response('Not Found', 404)]);
        $this->mockConnectionTester(DatabaseTestResult::succeeded('8.0.36'));

        $this->mock(DatabaseConnectionSwitcher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('use')->once()->withArgs(
                static fn ($credentials): bool => $credentials->host === 'mysql.example.jp' && $credentials->database === 'chok_ooo',
            );
        });

        $this->post(self::SITE.'/install', $this->input())->assertOk();
    }

    public function test_does_not_write_env_when_table_creation_fails(): void
    {
        Http::fake(['*' => Http::response('Not Found', 404)]);
        $this->mockConnectionTester(DatabaseTestResult::succeeded('8.0.36'));

        // 接続できない DB に切り替わったことにして、テーブル作成を失敗させる
        $this->mock(DatabaseConnectionSwitcher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('use')->once()->andReturnUsing(static function (): void {
                config([
                    'database.connections.broken' => ['driver' => 'unsupported'],
                    'database.default' => 'broken',
                ]);
            });
        });

        $this->post(self::SITE.'/install', $this->input())
            ->assertRedirect(self::SITE.'/install')
            ->assertSessionHas('error');

        config(['database.default' => 'sqlite']);

        $this->assertArrayNotHasKey('DB_HOST', $this->envValues());
        $this->assertFalse($this->state->isInstalled());
    }

    public function test_rejects_duplicate_domains(): void
    {
        Http::fake(['*' => Http::response('Not Found', 404)]);

        $this->post(self::SITE.'/install', $this->input([
            'main_domain' => 'chok.ooo',
            'dashboard_domain' => 'chok.ooo',
            'api_domain' => 'https://api.chok.ooo',
            'redirect_domain' => 'redirect.chok.ooo',
        ]))->assertSessionHasErrors(['main_domain', 'api_domain']);

        $this->assertFalse($this->state->isInstalled());
    }

    private function mockConnectionTester(DatabaseTestResult $result): void
    {
        $this->mock(DatabaseConnectionTester::class, function (MockInterface $mock) use ($result): void {
            $mock->shouldReceive('test')->andReturn($result);
        });
    }

    /** @return array<string, string> */
    private function input(array $overrides = []): array
    {
        return $overrides + [
            'db_host' => 'mysql.example.jp',
            'db_port' => '3306',
            'db_database' => 'chok_ooo',
            'db_username' => 'chok',
            'db_password' => 'secret',
            'main_domain' => 'setup.example.test',
            'dashboard_domain' => 'dash.setup.example.test',
            'api_domain' => 'api.setup.example.test',
            'redirect_domain' => 'redirect.setup.example.test',
        ];
    }

    /** @return array<string, string|null> */
    private function envValues(): array
    {
        return Dotenv::parse((string) File::get($this->workDirectory.'/.env'));
    }
}
