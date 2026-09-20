<?php

declare(strict_types=1);

namespace App\Services\Update;

use Illuminate\Support\Facades\File;

/**
 * 配布用 zip で設置した環境の更新（サーバーで git / Composer が使えなくても動く）。
 * GitHub Releases に添付された zip（vendor 同梱）をダウンロードして、コードを入れ替える。
 */
final class ReleaseZipStrategy implements UpdateStrategy
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $workPath,
        private readonly GitHubReleaseClient $github,
        private readonly CodeTree $code,
    ) {}

    public function name(): string
    {
        return 'zip';
    }

    public function currentRevision(): ?string
    {
        return null;
    }

    public function apply(ReleaseInfo $release, ?string $token): void
    {
        if ($release->packageAssetUrl === null) {
            throw new UpdateException("リリース {$release->tag} に配布用 zip が添付されていません。");
        }

        $work = $this->freshWorkDirectory('release');
        $zipPath = $work.DIRECTORY_SEPARATOR.'package.zip';

        try {
            $this->github->downloadAsset($release->packageAssetUrl, $token, $zipPath);
            $extracted = $work.DIRECTORY_SEPARATOR.'extracted';
            $this->code->extract($zipPath, $extracted);
            $this->code->install(self::packageRoot($extracted), $this->basePath, $work.DIRECTORY_SEPARATOR.'previous');
        } finally {
            File::deleteDirectory($work);
        }
    }

    public function rollback(Backup $backup): void
    {
        $work = $this->freshWorkDirectory('rollback');

        try {
            $this->code->extract($backup->codePath(), $work.DIRECTORY_SEPARATOR.'extracted');
            $this->code->install($work.DIRECTORY_SEPARATOR.'extracted', $this->basePath, $work.DIRECTORY_SEPARATOR.'failed');
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * zip の中身が入っているフォルダ。配布物のフォルダ名を将来変えても動くよう、中を見て決める
     *
     * @throws UpdateException
     */
    private static function packageRoot(string $extracted): string
    {
        // 直下に artisan があればそのまま、無ければ唯一のフォルダの中を使う
        if (is_file($extracted.DIRECTORY_SEPARATOR.'artisan')) {
            return $extracted;
        }

        $directories = File::directories($extracted);

        if (count($directories) !== 1 || ! is_file($directories[0].DIRECTORY_SEPARATOR.'artisan')) {
            throw new UpdateException('ダウンロードした zip の中身が想定と異なります。');
        }

        return $directories[0];
    }

    private function freshWorkDirectory(string $name): string
    {
        $path = $this->workPath.DIRECTORY_SEPARATOR.$name.'-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($path, 0700);

        return $path;
    }
}
