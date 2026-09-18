<?php

declare(strict_types=1);

namespace App\Installer;

final readonly class DatabaseCredentials
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
    ) {}

    /** @return array<string, string|int> */
    public function environmentValues(): array
    {
        return [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $this->host,
            'DB_PORT' => $this->port,
            'DB_DATABASE' => $this->database,
            'DB_USERNAME' => $this->username,
            'DB_PASSWORD' => $this->password,
        ];
    }
}
