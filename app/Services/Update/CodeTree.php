<?php

declare(strict_types=1);

namespace App\Services\Update;

use FilesystemIterator;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

/**
 * アプリケーションのコード一式（storage と .env を除く）の保存・入れ替え。
 * ディレクトリは丸ごと差し替えるため、新しい版で削除されたファイルも残らない。
 */
final class CodeTree
{
    /** @var list<string> 更新時に丸ごと差し替えるディレクトリ */
    public const DIRECTORIES = ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'vendor'];

    /** @var list<string> 更新時に上書きするファイル */
    public const FILES = ['artisan', 'index.php', '.htaccess', 'composer.json', 'composer.lock', '.env.example', AppVersion::VERSION_FILE];

    /** @throws UpdateException */
    public function archive(string $basePath, string $zipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new UpdateException('コードのバックアップを作成できません。');
        }

        foreach (self::DIRECTORIES as $directory) {
            $root = $basePath.DIRECTORY_SEPARATOR.$directory;
            if (! is_dir($root)) {
                continue;
            }

            $zip->addEmptyDir($directory);
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $item) {
                /** @var SplFileInfo $item */
                $relative = $directory.'/'.str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
                $item->isDir() ? $zip->addEmptyDir($relative) : $zip->addFile($item->getPathname(), $relative);
            }
        }

        foreach (self::FILES as $file) {
            $path = $basePath.DIRECTORY_SEPARATOR.$file;
            if (is_file($path)) {
                $zip->addFile($path, $file);
            }
        }

        if (! $zip->close()) {
            throw new UpdateException('コードのバックアップを書き込めません。');
        }
    }

    /** @throws UpdateException */
    public function extract(string $zipPath, string $destination): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new UpdateException('zip を開けません。');
        }

        File::ensureDirectoryExists($destination);
        $extracted = $zip->extractTo($destination);
        $zip->close();

        if (! $extracted) {
            throw new UpdateException('zip を展開できません。');
        }
    }

    /**
     * $sourceDir の内容で $basePath のコードを入れ替える。古いディレクトリは $trashDir に移す。
     *
     * @throws UpdateException
     */
    public function install(string $sourceDir, string $basePath, string $trashDir): void
    {
        if (! is_file($sourceDir.DIRECTORY_SEPARATOR.'artisan') || ! is_file($sourceDir.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')) {
            throw new UpdateException('更新用のファイルが不完全です（artisan または vendor が見つかりません）。');
        }

        File::ensureDirectoryExists($trashDir);

        foreach (self::DIRECTORIES as $directory) {
            $new = $sourceDir.DIRECTORY_SEPARATOR.$directory;
            if (! is_dir($new)) {
                continue;
            }

            $current = $basePath.DIRECTORY_SEPARATOR.$directory;
            if (is_dir($current) && ! @rename($current, $trashDir.DIRECTORY_SEPARATOR.$directory)) {
                throw new UpdateException("{$directory} を入れ替えられません。");
            }

            if (! @rename($new, $current)) {
                throw new UpdateException("{$directory} を配置できません。");
            }
        }

        foreach (self::FILES as $file) {
            $new = $sourceDir.DIRECTORY_SEPARATOR.$file;
            if (is_file($new) && ! @copy($new, $basePath.DIRECTORY_SEPARATOR.$file)) {
                throw new UpdateException("{$file} を上書きできません。");
            }
        }
    }
}
