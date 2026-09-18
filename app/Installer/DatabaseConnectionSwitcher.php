<?php

declare(strict_types=1);

namespace App\Installer;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;

/**
 * 入力された接続情報を、このリクエストの中でそのまま使えるようにする
 * （.env を書き換えても、読み込み済みの設定には反映されないため）。
 * テストで差し替えられるよう final にしていない。
 */
class DatabaseConnectionSwitcher
{
    private const CONNECTION = 'mysql';

    public function __construct(private readonly Config $config) {}

    public function use(DatabaseCredentials $credentials): void
    {
        $this->config->set('database.connections.'.self::CONNECTION, array_merge(
            (array) $this->config->get('database.connections.'.self::CONNECTION, []),
            [
                'host' => $credentials->host,
                'port' => (string) $credentials->port,
                'database' => $credentials->database,
                'username' => $credentials->username,
                'password' => $credentials->password,
            ],
        ));
        $this->config->set('database.default', self::CONNECTION);

        DB::purge(self::CONNECTION);
    }
}
