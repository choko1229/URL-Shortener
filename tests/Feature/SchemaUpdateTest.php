<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Installer\InstallationState;
use App\Installer\SchemaState;
use App\Installer\SchemaUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** 配布物を入れ替えただけでも、次のアクセスでテーブルが最新になること */
final class SchemaUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $recordPath;

    private SchemaState $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recordPath = storage_path('framework/testing/schema-update-test.json');
        $this->state = new SchemaState($this->recordPath, database_path('migrations'));
        $this->app->instance(SchemaState::class, $this->state);
    }

    protected function tearDown(): void
    {
        foreach ([$this->recordPath, $this->recordPath.'.lock'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_pending_migrations_are_applied_on_the_first_access_after_an_update(): void
    {
        // 更新前のマイグレーション構成が記録されている状態
        $this->writeRecord(['signature' => 'before-the-update']);

        $this->get($this->mainUrl())->assertOk();

        $this->assertTrue($this->state->isCurrent());
        $this->assertArrayHasKey('updated_at', $this->readRecord());
    }

    public function test_nothing_runs_when_the_record_is_already_current(): void
    {
        $this->writeRecord(['signature' => $this->state->signature(), 'updated_at' => '2026-01-01T00:00:00+00:00']);

        $this->get($this->mainUrl())->assertOk();

        // 記録が書き換わっていない＝マイグレーションを実行していない
        $this->assertSame('2026-01-01T00:00:00+00:00', $this->readRecord()['updated_at']);
    }

    public function test_the_check_is_skipped_while_setting_up(): void
    {
        // セットアップ前はインストーラ自身がテーブルを作成するため、確認も記録もしない
        $this->app->instance(InstallationState::class, new InstallationState($this->recordPath.'.installed'));

        $this->get($this->mainUrl())->assertRedirect(route('install.show'));

        $this->assertFalse(is_file($this->recordPath));
    }

    public function test_a_failure_is_recorded_and_retried_after_a_while(): void
    {
        Log::spy();
        config(['database.default' => 'unsupported']);

        $this->updater()->updateIfNeeded();

        $this->assertFalse($this->state->isCurrent());
        $this->assertArrayHasKey('failed_at', $this->readRecord());
        Log::shouldHaveReceived('error')->once();

        // 直後は再試行しない（毎アクセスで失敗を繰り返さないため）
        config(['database.default' => 'sqlite']);
        $this->updater()->updateIfNeeded();
        $this->assertFalse($this->state->isCurrent());

        // 待ち時間が過ぎていれば、次のアクセスで再び試みる
        $this->writeRecord(['failed_at' => time() - 3600]);
        $this->updater()->updateIfNeeded();
        $this->assertTrue($this->state->isCurrent());
    }

    private function updater(): SchemaUpdater
    {
        return $this->app->make(SchemaUpdater::class);
    }

    /** @param  array<string, mixed>  $record */
    private function writeRecord(array $record): void
    {
        file_put_contents($this->recordPath, json_encode($record, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function readRecord(): array
    {
        return json_decode((string) file_get_contents($this->recordPath), true, flags: JSON_THROW_ON_ERROR);
    }
}
