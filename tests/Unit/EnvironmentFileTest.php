<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Installer\EnvironmentFile;
use Dotenv\Dotenv;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnvironmentFileTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/url-shortener-env-'.bin2hex(random_bytes(4));
        file_put_contents($this->path, "APP_NAME=URL-Shortener\n# DB_HOST=commented\nDB_HOST=127.0.0.1\nVITE_APP_NAME=\"\${APP_NAME}\"\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    public function test_replaces_existing_keys_and_appends_new_ones(): void
    {
        (new EnvironmentFile($this->path))->write([
            'DB_HOST' => 'mysql.example.jp',
            'DB_PORT' => 3306,
            'APP_DEBUG' => false,
            'SESSION_DOMAIN' => null,
        ]);

        $contents = (string) file_get_contents($this->path);
        $values = Dotenv::parse($contents);

        $this->assertSame('mysql.example.jp', $values['DB_HOST']);
        $this->assertSame('3306', $values['DB_PORT']);
        $this->assertSame('false', $values['APP_DEBUG']);
        $this->assertSame('null', $values['SESSION_DOMAIN']);
        $this->assertSame('URL-Shortener', $values['VITE_APP_NAME']);
        $this->assertStringContainsString('# DB_HOST=commented', $contents);
        $this->assertSame(1, substr_count($contents, "\nDB_HOST="));
    }

    /** @return iterable<string, array{string}> */
    public static function trickyValues(): iterable
    {
        yield 'スペースと記号' => ['p@ss word #1'];
        yield 'ダブルクォートとドル記号' => ['a"b$c${D}'];
        yield 'シングルクォート' => ["it's"];
        yield 'バックスラッシュ' => ['C:\\path\\to'];
        yield 'base64' => ['base64:AbC+/dEf=='];
        yield '空文字' => [''];
    }

    #[DataProvider('trickyValues')]
    public function test_round_trips_tricky_values(string $value): void
    {
        $file = new EnvironmentFile($this->path);
        $file->write(['DB_PASSWORD' => $value]);

        $this->assertSame($value, $file->values()['DB_PASSWORD']);
    }

    public function test_rejects_values_with_newlines(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new EnvironmentFile($this->path))->write(['DB_PASSWORD' => "line1\nAPP_DEBUG=true"]);
    }

    public function test_rejects_invalid_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new EnvironmentFile($this->path))->write(['db-host' => 'x']);
    }
}
