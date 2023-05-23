<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Helpers\CanBeInstalled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class LocalDatabase extends Plugin implements Installable
{
    use CanBeInstalled;

    protected string $connection;

    protected string $database;

    protected string $username;

    protected string $password;

    protected bool $enableForeignKeys;

    public function install(): void
    {
        $database = Console::choice('Database', ['MySQL', 'PostgreSQL', 'SQLite'], 0);

        $this->connection = match ($database) {
            'MySQL'      => 'mysql',
            'PostgreSQL' => 'pgsql',
            'SQLite'     => 'sqlite',
        };

        $databaseDefault = $this->connection === 'sqlite'
            ? Project::path('/database/database.sqlite')
            : Str::snake(Project::config()->appName);

        $this->database = Console::ask('Database name', $databaseDefault);
        $this->username = Console::ask('Database user', 'root');
        $this->password = Console::ask('Database password') ?? '';

        if ($this->connection === 'sqlite') {
            $this->enableForeignKeys = Console::confirm('Enable foreign keys?', true);

            if (File::missing($this->database)) {
                File::put($this->database, '');
            }
        } else {
            config([
                'database.connections.local' => [
                    'driver'    => $this->connection,
                    'host'      => '127.0.0.1',
                    'port'      => 3306,
                    // Leave blank, otherwise it will try to connect to the database which probably doesn't exist yet
                    'database'  => '',
                    'username'  => $this->username,
                    'password'  => $this->password,
                    'charset'   => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                ],
            ]);

            DB::purge('local');
            DB::connection('local')->statement('CREATE DATABASE IF NOT EXISTS ' . $this->database);
        }
    }

    public function environmentVariables(): array
    {
        $params = [
            'DB_CONNECTION' => $this->connection,
            'DB_DATABASE'   => $this->database,
            'DB_USERNAME'   => $this->username,
            'DB_PASSWORD'   => $this->password,
        ];

        if (isset($this->enableForeignKeys)) {
            $params['DB_FOREIGN_KEYS'] = $this->enableForeignKeys;
        }

        return $params;
    }
}
