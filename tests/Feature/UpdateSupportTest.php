<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ShortUrl;
use App\Models\User;
use App\Services\Update\DatabaseBackup;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/** 自動アップデートを支える機能: DB ダンプと復元、ヘルスチェック、定期実行、管理画面 */
final class UpdateSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_backup_restores_previous_state(): void
    {
        $directory = storage_path('framework/testing/db-'.Str::random(8));
        File::ensureDirectoryExists($directory);
        File::put($directory.'/test.sqlite', '');
        config(['database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $directory.'/test.sqlite', 'prefix' => '', 'foreign_key_constraints' => true]]);

        try {
            $schema = Schema::connection('backup_test');
            $schema->create('items', static function ($table): void {
                $table->id();
                $table->string('name')->index();
                $table->text('note')->nullable();
            });
            DB::connection('backup_test')->table('items')->insert([
                ['name' => "it's \"quoted\"", 'note' => null],
                ['name' => '日本語', 'note' => "改行\nあり"],
            ]);

            $backup = new DatabaseBackup('backup_test');
            $backup->dump($directory.'/dump.sql');

            // 更新で変わった状態（行の追加・テーブルの追加）
            DB::connection('backup_test')->table('items')->insert(['name' => 'added later']);
            $schema->create('added_by_migration', static fn ($table) => $table->id());

            $backup->restore($directory.'/dump.sql');

            $this->assertSame(["it's \"quoted\"", '日本語'], DB::connection('backup_test')->table('items')->orderBy('id')->pluck('name')->all());
            $this->assertSame("改行\nあり", DB::connection('backup_test')->table('items')->where('name', '日本語')->value('note'));
            $this->assertFalse($schema->hasTable('added_by_migration'));
        } finally {
            DB::disconnect('backup_test');
            File::deleteDirectory($directory);
        }
    }

    public function test_health_check_command_passes_and_leaves_no_data(): void
    {
        $public = storage_path('framework/testing/public-'.Str::random(8));
        File::ensureDirectoryExists($public.'/build');
        File::put($public.'/build/manifest.json', '{}');
        $this->app->usePublicPath($public);

        try {
            $this->artisan('app:health-check')->assertSuccessful();
            $this->assertSame(0, ShortUrl::withTrashed()->count());
        } finally {
            File::deleteDirectory($public);
        }
    }

    public function test_health_check_fails_without_built_assets(): void
    {
        $this->app->usePublicPath(storage_path('framework/testing/missing-public'));

        $this->artisan('app:health-check')->assertFailed();
    }

    public function test_periodic_tasks_are_checked_every_minute_when_cron_is_configured(): void
    {
        $events = array_filter(
            app(Schedule::class)->events(),
            static fn (Event $event): bool => str_contains((string) $event->command, 'app:periodic-tasks'),
        );

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', array_values($events)[0]->expression);
    }

    public function test_admin_can_save_update_settings_with_encrypted_secrets(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/updates'))
            ->put($this->dashboardUrl('/admin/updates/settings'), [
                'enabled' => '1',
                'github_token' => 'github_pat_abcdefghijklmnop',
                'discord_webhook_url' => 'https://discord.com/api/webhooks/123456/abc-DEF_123',
            ])
            ->assertSessionHas('notice');

        $this->assertSame('github_pat_abcdefghijklmnop', AppSetting::valueFor(AppSetting::UPDATE_GITHUB_TOKEN));
        $this->assertTrue(AppSetting::query()->where('key', AppSetting::UPDATE_GITHUB_TOKEN)->value('is_encrypted'));
        $this->assertTrue(AppSetting::query()->where('key', AppSetting::DISCORD_WEBHOOK_URL)->value('is_encrypted'));

        // 空欄なら変更しない
        $this->actingAs($admin)->from($this->dashboardUrl('/admin/updates'))
            ->put($this->dashboardUrl('/admin/updates/settings'), []);
        $this->assertSame('github_pat_abcdefghijklmnop', AppSetting::valueFor(AppSetting::UPDATE_GITHUB_TOKEN));
        $this->assertFalse(AppSetting::valueFor(AppSetting::UPDATE_ENABLED));

        $this->actingAs($admin)->from($this->dashboardUrl('/admin/updates'))
            ->put($this->dashboardUrl('/admin/updates/settings'), ['discord_webhook_url' => 'https://evil.example/hook'])
            ->assertSessionHasErrors('discord_webhook_url');
    }

    public function test_admin_can_check_latest_release(): void
    {
        AppSetting::store(AppSetting::UPDATE_GITHUB_TOKEN, 'github-token-value', encrypt: true);
        // 現在のバージョン（git のタグ）より必ず新しいタグにする。同じタグだと「最新の状態です」になる
        Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v99.1.0', 'html_url' => 'https://github.com/x', 'assets' => []])]);

        $this->actingAs(User::factory()->admin()->create())
            ->from($this->dashboardUrl('/admin/updates'))
            ->post($this->dashboardUrl('/admin/updates/check'))
            ->assertSessionHas('notice', fn (string $message): bool => str_contains($message, 'v99.1.0'));

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer github-token-value'));
    }
}
