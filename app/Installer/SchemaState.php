<?php

declare(strict_types=1);

namespace App\Installer;

use Closure;
use JsonException;
use RuntimeException;

/**
 * どのマイグレーション構成まで適用したかの記録。
 * ファイルを入れ替えただけ（zip の上書き等）でもテーブルの更新漏れに気付けるよう、DB ではなくファイルで管理する。
 */
final class SchemaState
{
    /** storage/ からの相対パス（storage/app/private は .gitignore 済みで Web 非公開） */
    public const FILE = 'app/private/schema.json';

    public function __construct(
        private readonly string $path,
        private readonly string $migrationsPath,
    ) {}

    /** 記録が現在のマイグレーション構成と一致していれば true（この場合は未適用のものが無い） */
    public function isCurrent(): bool
    {
        return ($this->read()['signature'] ?? null) === $this->signature();
    }

    /** 直近の失敗から指定秒数が経っていなければ true（毎アクセスで再試行しないため） */
    public function failedRecently(int $seconds): bool
    {
        $failedAt = $this->read()['failed_at'] ?? null;

        return is_int($failedAt) && $failedAt + $seconds > time();
    }

    /**
     * 同時に複数のプロセスが更新しないよう、排他ロックを取ってから処理を実行する。
     * すでに別のプロセスが実行中の場合は何もせず false を返す。
     */
    public function withLock(Closure $callback): bool
    {
        $this->ensureDirectory();

        $handle = @fopen($this->path.'.lock', 'c');

        if ($handle === false) {
            throw new RuntimeException("{$this->path}.lock を作成できません。");
        }

        try {
            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                return false;
            }

            $callback();

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** 適用済みとして記録する */
    public function markUpdated(): void
    {
        $this->write(['signature' => $this->signature(), 'updated_at' => gmdate(DATE_ATOM)]);
    }

    /** 失敗を記録する（構成は記録しないため、待ち時間の経過後に再び試みる） */
    public function markFailed(): void
    {
        $this->write(['failed_at' => time(), 'failed_at_iso' => gmdate(DATE_ATOM)]);
    }

    /** 現在のマイグレーション構成を表す値（ファイルが増減すると変わる） */
    public function signature(): string
    {
        $files = array_map('basename', glob($this->migrationsPath.'/*.php') ?: []);
        sort($files);

        return hash('sha1', implode('|', $files));
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        $contents = is_file($this->path) ? @file_get_contents($this->path) : false;

        if ($contents === false) {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @param  array<string, mixed>  $record */
    private function write(array $record): void
    {
        $this->ensureDirectory();

        try {
            $json = json_encode($record, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            throw new RuntimeException('データベース更新の記録を作成できません。', previous: $e);
        }

        if (@file_put_contents($this->path, $json, LOCK_EX) === false) {
            throw new RuntimeException("{$this->path} に書き込めません。");
        }
    }

    private function ensureDirectory(): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("{$directory} を作成できません。");
        }
    }
}
