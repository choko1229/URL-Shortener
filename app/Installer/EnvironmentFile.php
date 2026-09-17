<?php

declare(strict_types=1);

namespace App\Installer;

use Dotenv\Dotenv;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * .env ファイルの読み書き。
 * Laravel 起動前（Preflight）からも使うため、フレームワークの機能に依存しない。
 */
final class EnvironmentFile
{
    private const KEY_PATTERN = '/\A[A-Z][A-Z0-9_]*\z/';

    // クォート不要でそのまま書ける値（base64 の APP_KEY や URL を含む）
    private const BARE_VALUE_PATTERN = '/\A[A-Za-z0-9_.:\/@+=,-]+\z/';

    public function __construct(private readonly string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * @return array<string, string|null>
     *
     * @throws RuntimeException 読み込み・解析に失敗した場合
     */
    public function values(): array
    {
        try {
            return Dotenv::parse($this->contents());
        } catch (Throwable $e) {
            throw new RuntimeException("{$this->path} を解析できません。", previous: $e);
        }
    }

    /**
     * 指定したキーの値を書き換える（無いキーは末尾に追加）。
     * 書き込み前に解析し直して値が意図どおりか検証し、一時ファイル経由で置き換える。
     *
     * @param  array<string, string|int|bool|null>  $values
     * @param  string|null  $baseContents  元にする内容。null なら現在のファイル内容
     *
     * @throws InvalidArgumentException キー・値の形式が不正な場合
     * @throws RuntimeException 書き込み・検証に失敗した場合
     */
    public function write(array $values, ?string $baseContents = null): void
    {
        $contents = $baseContents ?? $this->contents();

        foreach ($values as $key => $value) {
            if (preg_match(self::KEY_PATTERN, $key) !== 1) {
                throw new InvalidArgumentException("環境変数名が不正です: {$key}");
            }

            $line = $key.'='.self::encode($value);
            $pattern = '/^[ \t]*(?:export[ \t]+)?'.preg_quote($key, '/').'[ \t]*=.*$/m';
            $replaced = 0;
            $contents = (string) preg_replace_callback($pattern, static fn (): string => $line, $contents, -1, $replaced);

            if ($replaced === 0) {
                $contents = rtrim($contents, "\r\n")."\n".$line."\n";
            }
        }

        $this->verify($contents, $values);
        $this->replaceFile($contents);
    }

    /**
     * 値を .env の書式に変換する。
     *
     * @throws InvalidArgumentException 改行を含むなど、安全に表現できない値の場合
     */
    public static function encode(string|int|bool|null $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if (preg_match('/[\r\n\0]/', $value) === 1) {
            throw new InvalidArgumentException('環境変数の値に改行は使えません。');
        }

        if ($value === '' || preg_match(self::BARE_VALUE_PATTERN, $value) === 1) {
            return $value;
        }

        // シングルクォート内は変数展開・エスケープが行われない
        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        if (! str_contains($value, '${')) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        throw new InvalidArgumentException("環境変数の値に ' と \${ を同時に含めることはできません。");
    }

    private function contents(): string
    {
        $contents = is_file($this->path) ? @file_get_contents($this->path) : false;

        if (! is_string($contents)) {
            throw new RuntimeException("{$this->path} を読み込めません。");
        }

        return $contents;
    }

    /** @param  array<string, string|int|bool|null>  $expected */
    private function verify(string $contents, array $expected): void
    {
        try {
            $parsed = Dotenv::parse($contents);
        } catch (Throwable $e) {
            throw new RuntimeException('書き込もうとした .env の内容を解析できません。', previous: $e);
        }

        foreach ($expected as $key => $value) {
            $expectedValue = match (true) {
                $value === null => 'null',
                is_bool($value) => $value ? 'true' : 'false',
                default => (string) $value,
            };

            if (($parsed[$key] ?? null) !== $expectedValue) {
                throw new RuntimeException("{$key} を .env に正しく書き込めません。");
            }
        }
    }

    private function replaceFile(string $contents): void
    {
        $temporary = dirname($this->path).DIRECTORY_SEPARATOR.'.env.tmp-'.bin2hex(random_bytes(6));

        if (@file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException(dirname($this->path).' に書き込めません。フォルダの書き込み権限を確認してください。');
        }

        // DB パスワードや APP_KEY を含むため所有者のみ読み書き可能にする（失敗しても続行）
        @chmod($temporary, 0600);

        if (! @rename($temporary, $this->path)) {
            @unlink($temporary);

            throw new RuntimeException("{$this->path} を更新できません。");
        }
    }
}
