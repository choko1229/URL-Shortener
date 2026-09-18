<?php

/*
| ディレクトリを zip にまとめる（scripts/build-release.sh から使用）。
| 環境によって zip コマンドが無いため PHP の ZipArchive を使う。
|
| 使い方: php scripts/zip-directory.php <元ディレクトリ> <出力する zip> <zip 内のフォルダ名>
*/

declare(strict_types=1);

if ($argc !== 4) {
    fwrite(STDERR, "使い方: php scripts/zip-directory.php <元ディレクトリ> <出力する zip> <zip 内のフォルダ名>\n");
    exit(2);
}

[, $source, $output, $rootName] = $argv;

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "PHP の zip 拡張が有効になっていません。\n");
    exit(1);
}

$source = realpath($source);
if ($source === false || ! is_dir($source)) {
    fwrite(STDERR, "元ディレクトリが見つかりません。\n");
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "{$output} を作成できません。\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);

$count = 0;
foreach ($iterator as $item) {
    /** @var SplFileInfo $item */
    $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));
    $entry = $rootName.'/'.$relative;

    if ($item->isDir()) {
        $zip->addEmptyDir($entry);

        continue;
    }

    if (! $zip->addFile($item->getPathname(), $entry)) {
        fwrite(STDERR, "{$relative} を追加できません。\n");
        exit(1);
    }
    $count++;
}

if (! $zip->close()) {
    fwrite(STDERR, "{$output} を書き込めません。\n");
    exit(1);
}

echo "{$count} ファイルを {$output} にまとめました。\n";
