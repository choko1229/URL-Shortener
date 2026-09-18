<?php

declare(strict_types=1);

namespace App\Services\Update;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * artisan コマンドを別プロセスで実行するための PHP（CLI）の場所を探す。
 * Web から実行される場合、PHP_BINARY は php-fpm / php-cgi を指すことがあるため、CLI 版を候補から確認する。
 */
class PhpBinaryResolver
{
    private const MINIMUM_VERSION = '8.2.0';

    private bool $resolved = false;

    private ?string $binary = null;

    public function __construct(private readonly ?string $configured) {}

    /** 見つからない、または別プロセスを起動できない環境では null */
    public function resolve(): ?string
    {
        if ($this->resolved) {
            return $this->binary;
        }

        $this->resolved = true;

        if ($this->configured !== null && $this->configured !== '') {
            return $this->binary = $this->configured;
        }

        if (PHP_SAPI === 'cli') {
            return $this->binary = PHP_BINARY;
        }

        if (! function_exists('proc_open')) {
            Log::info('proc_open が使えないため、artisan コマンドは同じプロセスで実行します。');

            return null;
        }

        foreach ($this->candidates() as $candidate) {
            if ($this->isUsableCli($candidate)) {
                return $this->binary = $candidate;
            }
        }

        Log::info('PHP（CLI）が見つからないため、artisan コマンドは同じプロセスで実行します。');

        return null;
    }

    /** @return list<string> */
    private function candidates(): array
    {
        $version = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;

        return array_values(array_unique([
            PHP_BINDIR.DIRECTORY_SEPARATOR.'php',
            PHP_BINDIR.DIRECTORY_SEPARATOR.'php'.$version,
            '/usr/local/bin/php'.$version,
            '/usr/bin/php'.$version,
            '/usr/local/bin/php',
            '/usr/bin/php',
            'php',
        ]));
    }

    private function isUsableCli(string $candidate): bool
    {
        try {
            $result = Process::timeout(10)->run([$candidate, '-r', 'echo PHP_SAPI, " ", PHP_VERSION;']);
        } catch (Throwable) {
            return false;
        }

        if (! $result->successful()) {
            return false;
        }

        [$sapi, $version] = array_pad(explode(' ', trim($result->output()), 2), 2, '');

        return $sapi === 'cli' && version_compare($version, self::MINIMUM_VERSION, '>=');
    }
}
