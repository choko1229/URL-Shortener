<?php

declare(strict_types=1);

namespace App\Services\Update;

use Illuminate\Support\Facades\Process;

/**
 * 現在動いているバージョン。
 * 配布用 zip では VERSION ファイル、Git で設置した環境ではタグから判定する。
 */
final class AppVersion
{
    public const VERSION_FILE = 'VERSION';

    public function __construct(private readonly string $basePath) {}

    /** 判定できない場合（開発中のコードなど）は null */
    public function current(): ?Version
    {
        $file = $this->basePath.DIRECTORY_SEPARATOR.self::VERSION_FILE;

        if (is_file($file)) {
            return Version::tryParse((string) file_get_contents($file));
        }

        if ($this->isGitCheckout()) {
            $result = Process::path($this->basePath)->timeout(10)->run(['git', 'describe', '--tags', '--exact-match']);

            return $result->successful() ? Version::tryParse(trim($result->output())) : null;
        }

        return null;
    }

    public function isGitCheckout(): bool
    {
        return is_dir($this->basePath.DIRECTORY_SEPARATOR.'.git');
    }
}
