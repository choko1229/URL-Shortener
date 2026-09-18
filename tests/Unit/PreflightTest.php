<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Installer\InstallationState;
use App\Installer\Preflight;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

final class PreflightTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir().'/chok-ooo-preflight-'.bin2hex(random_bytes(4));
        foreach (Preflight::WRITABLE_DIRECTORIES as $directory) {
            mkdir($this->basePath.'/'.$directory, 0755, true);
        }
        file_put_contents($this->basePath.'/.env.example', "APP_NAME=chok.ooo\nAPP_KEY=\nSESSION_DRIVER=database\nSESSION_DOMAIN=.chok.ooo\nSESSION_SECURE_COOKIE=true\n");
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);

        parent::tearDown();
    }

    public function test_creates_env_file_with_generated_key_and_install_time_settings(): void
    {
        $problems = (new Preflight($this->basePath))->prepare(secureRequest: false);

        $this->assertSame([], $problems);

        $values = Dotenv::parse((string) file_get_contents($this->basePath.'/.env'));
        $this->assertSame('chok.ooo', $values['APP_NAME']);
        $this->assertMatchesRegularExpression('/\Abase64:[A-Za-z0-9+\/]{43}=\z/', (string) $values['APP_KEY']);
        $this->assertSame('file', $values['SESSION_DRIVER']);
        $this->assertSame('null', $values['SESSION_DOMAIN']);
        $this->assertSame('false', $values['SESSION_SECURE_COOKIE']);
    }

    public function test_does_not_overwrite_existing_env_file(): void
    {
        file_put_contents($this->basePath.'/.env', "APP_KEY=base64:existing\n");

        (new Preflight($this->basePath))->prepare(secureRequest: true);

        $this->assertSame("APP_KEY=base64:existing\n", file_get_contents($this->basePath.'/.env'));
    }

    public function test_reports_missing_writable_directory(): void
    {
        rmdir($this->basePath.'/storage/logs');

        $problems = (new Preflight($this->basePath))->prepare(secureRequest: true);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('storage/logs', $problems[0]);
    }

    public function test_detects_installation_by_lock_file(): void
    {
        $preflight = new Preflight($this->basePath);
        $this->assertFalse($preflight->isInstalled());

        (new InstallationState($this->basePath.'/storage/'.InstallationState::LOCK_FILE))->markInstalled();

        $this->assertTrue($preflight->isInstalled());
    }

    public function test_detects_https_from_server_variables(): void
    {
        $this->assertTrue(Preflight::isSecureRequest(['HTTPS' => 'on']));
        $this->assertTrue(Preflight::isSecureRequest(['HTTP_X_FORWARDED_PROTO' => 'https']));
        $this->assertFalse(Preflight::isSecureRequest(['HTTPS' => 'off', 'SERVER_PORT' => '80']));
    }

    public function test_failure_page_escapes_messages(): void
    {
        $html = Preflight::renderFailurePage(['<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path.'/'.$entry;
            is_dir($child) ? $this->removeDirectory($child) : unlink($child);
        }

        rmdir($path);
    }
}
