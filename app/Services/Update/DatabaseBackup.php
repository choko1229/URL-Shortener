<?php

declare(strict_types=1);

namespace App\Services\Update;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use PDO;
use Throwable;

/**
 * データベースのバックアップと復元（requirements.md 7-1: DB ダンプ）。
 * MySQL では mysqldump を使い、使えないレンタルサーバーでは PHP で同等のダンプを作る。
 * テストで差し替えられるよう final にしていない。
 */
class DatabaseBackup
{
    /** @var list<string> 構造のみ保存し、中身は保存しないテーブル（一時的なデータ） */
    private const STRUCTURE_ONLY_TABLES = ['sessions', 'cache', 'cache_locks'];

    private const INSERT_CHUNK = 200;

    public function __construct(
        private readonly ?string $connectionName = null,
        private readonly string $mysqldumpBinary = 'mysqldump',
    ) {}

    /** @throws UpdateException */
    public function dump(string $path): void
    {
        $connection = $this->connection();

        if ($connection->getDriverName() === 'mysql' && $this->dumpWithMysqldump($connection, $path)) {
            return;
        }

        try {
            $this->dumpWithPhp($connection, $path);
        } catch (Throwable $e) {
            Log::error('データベースのダンプに失敗しました。', ['exception' => $e::class, 'error' => $e->getMessage()]);

            throw new UpdateException('データベースのバックアップを作成できませんでした。');
        }
    }

    /** @throws UpdateException */
    public function restore(string $path): void
    {
        $sql = is_file($path) ? file_get_contents($path) : false;

        if (! is_string($sql) || $sql === '') {
            throw new UpdateException('データベースのバックアップが見つかりません。');
        }

        $connection = $this->connection();

        try {
            // 更新で追加されたテーブルも含めて一度すべて削除し、バックアップ時点の状態に戻す
            Schema::connection($connection->getName())->dropAllTables();
            $connection->unprepared($this->wrapForeignKeyChecks($connection, $sql));
        } catch (Throwable $e) {
            Log::error('データベースの復元に失敗しました。', ['exception' => $e::class, 'error' => $e->getMessage()]);

            throw new UpdateException('データベースを復元できませんでした。');
        }
    }

    private function dumpWithMysqldump(Connection $connection, string $path): bool
    {
        $config = $connection->getConfig();

        $result = Process::timeout(600)
            ->env(['MYSQL_PWD' => (string) ($config['password'] ?? '')])
            ->run([
                $this->mysqldumpBinary,
                '--single-transaction',
                '--no-tablespaces',
                '--skip-lock-tables',
                '--host='.($config['host'] ?? '127.0.0.1'),
                '--port='.($config['port'] ?? '3306'),
                '--user='.($config['username'] ?? ''),
                '--result-file='.$path,
                (string) ($config['database'] ?? ''),
            ]);

        if ($result->successful() && is_file($path) && filesize($path) > 0) {
            return true;
        }

        // レンタルサーバーで mysqldump が使えない場合は PHP でのダンプに切り替える
        Log::warning('mysqldump を実行できないため、PHP でダンプします。', ['exit_code' => $result->exitCode()]);

        return false;
    }

    private function dumpWithPhp(Connection $connection, string $path): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new UpdateException('バックアップファイルを作成できません。');
        }

        try {
            fwrite($handle, '-- chok.ooo database backup '.gmdate(DATE_ATOM)."\n");

            foreach ($this->tables($connection) as $table => $createSql) {
                $quoted = $this->quoteIdentifier($connection, $table);
                fwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n{$createSql};\n");

                if (in_array($table, self::STRUCTURE_ONLY_TABLES, true)) {
                    continue;
                }

                $this->writeRows($connection, $handle, $table);
            }

            foreach ($this->indexes($connection) as $indexSql) {
                fwrite($handle, "{$indexSql};\n");
            }
        } finally {
            fclose($handle);
        }
    }

    /** @param  resource  $handle */
    private function writeRows(Connection $connection, $handle, string $table): void
    {
        $pdo = $connection->getPdo();
        $quotedTable = $this->quoteIdentifier($connection, $table);
        $buffer = [];
        $columns = null;

        foreach ($connection->table($table)->cursor() as $row) {
            $values = (array) $row;
            $columns ??= implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($connection, $column), array_keys($values)));
            $buffer[] = '('.implode(', ', array_map(static fn (mixed $value): string => match (true) {
                $value === null => 'NULL',
                is_int($value), is_float($value) => (string) $value,
                is_bool($value) => $value ? '1' : '0',
                default => (string) $pdo->quote((string) $value),
            }, $values)).')';

            if (count($buffer) >= self::INSERT_CHUNK) {
                fwrite($handle, "INSERT INTO {$quotedTable} ({$columns}) VALUES\n".implode(",\n", $buffer).";\n");
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            fwrite($handle, "INSERT INTO {$quotedTable} ({$columns}) VALUES\n".implode(",\n", $buffer).";\n");
        }
    }

    /** @return array<string, string> テーブル名 => CREATE 文 */
    private function tables(Connection $connection): array
    {
        $tables = [];

        if ($connection->getDriverName() === 'sqlite') {
            foreach ($connection->select("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $row) {
                $tables[(string) $row->name] = (string) $row->sql;
            }

            return $tables;
        }

        foreach ($connection->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
            $name = (string) array_values((array) $row)[0];
            $create = (array) $connection->selectOne('SHOW CREATE TABLE '.$this->quoteIdentifier($connection, $name));
            $tables[$name] = (string) ($create['Create Table'] ?? array_values($create)[1]);
        }

        return $tables;
    }

    /** @return list<string> SQLite のインデックス（MySQL は CREATE TABLE に含まれる） */
    private function indexes(Connection $connection): array
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return [];
        }

        return array_map(
            static fn (object $row): string => (string) $row->sql,
            $connection->select("SELECT sql FROM sqlite_master WHERE type = 'index' AND sql IS NOT NULL"),
        );
    }

    private function wrapForeignKeyChecks(Connection $connection, string $sql): string
    {
        return $connection->getDriverName() === 'sqlite'
            ? "PRAGMA foreign_keys = OFF;\n{$sql}\nPRAGMA foreign_keys = ON;"
            : "SET FOREIGN_KEY_CHECKS = 0;\n{$sql}\nSET FOREIGN_KEY_CHECKS = 1;";
    }

    private function quoteIdentifier(Connection $connection, string $name): string
    {
        return $connection->getDriverName() === 'sqlite'
            ? '"'.str_replace('"', '""', $name).'"'
            : '`'.str_replace('`', '``', $name).'`';
    }

    private function connection(): Connection
    {
        $connection = DB::connection($this->connectionName);
        $connection->getPdo()->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $connection;
    }
}
