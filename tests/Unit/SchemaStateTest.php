<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Installer\SchemaState;
use PHPUnit\Framework\TestCase;

final class SchemaStateTest extends TestCase
{
    private string $directory;

    private string $migrations;

    private SchemaState $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/schema-state-'.bin2hex(random_bytes(4));
        $this->migrations = $this->directory.'/migrations';
        mkdir($this->migrations, 0755, true);
        touch($this->migrations.'/2026_09_17_000001_create_short_urls_table.php');

        $this->state = new SchemaState($this->directory.'/private/schema.json', $this->migrations);
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->directory);

        parent::tearDown();
    }

    private static function removeDirectory(string $directory): void
    {
        foreach ((array) glob($directory.'/*') as $path) {
            is_dir((string) $path) ? self::removeDirectory((string) $path) : unlink((string) $path);
        }

        rmdir($directory);
    }

    public function test_the_record_is_current_only_until_a_migration_is_added(): void
    {
        $this->assertFalse($this->state->isCurrent());

        $this->state->markUpdated();
        $this->assertTrue($this->state->isCurrent());

        // 更新でマイグレーションが増えたら、適用が必要だと分かる
        touch($this->migrations.'/2026_09_20_000003_add_preview_columns_to_short_urls_table.php');
        $this->assertFalse($this->state->isCurrent());
    }

    public function test_a_failure_is_remembered_for_the_given_time(): void
    {
        $this->assertFalse($this->state->failedRecently(600));

        $this->state->markFailed();

        $this->assertTrue($this->state->failedRecently(600));
        $this->assertFalse($this->state->failedRecently(0));
        $this->assertFalse($this->state->isCurrent());
    }

    public function test_only_one_process_runs_the_update_at_a_time(): void
    {
        $nested = true;

        $this->assertTrue($this->state->withLock(function () use (&$nested): void {
            $nested = $this->state->withLock(static fn () => null);
        }));

        $this->assertFalse($nested);
        // ロックを解放した後は再び取得できる
        $this->assertTrue($this->state->withLock(static fn () => null));
    }

    public function test_a_broken_record_is_treated_as_not_applied(): void
    {
        $this->state->markUpdated();
        file_put_contents($this->directory.'/private/schema.json', '{ broken');

        $this->assertFalse($this->state->isCurrent());
        $this->assertFalse($this->state->failedRecently(600));
    }
}
