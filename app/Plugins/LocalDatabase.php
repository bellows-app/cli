<?php

namespace Bellows\Plugins;

use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Helpers\CanBeInstalled;
use Illuminate\Support\Str;

class LocalDatabase extends Plugin implements Installable
{
    use CanBeInstalled;

    protected string $connection;

    protected string $database;

    protected string $username;

    protected string $password;

    public function install(): void
    {
        $database = Console::choice('Database', ['MySQL', 'PostgreSQL', 'SQLite'], 0);

        $this->connection = match ($database) {
            'MySQL'      => 'mysql',
            'PostgreSQL' => 'pgsql',
            'SQLite'     => 'sqlite',
        };

        $this->database = Console::ask('Database name', Str::snake(Project::config()->appName));
        $this->username = Console::ask('Database user', 'root');
        $this->password = Console::ask('Database password') ?? '';

        // TODO: Handle things other than MySQL
        // TODO: $this->createDatabase();
        // DB::statement('CREATE DATABASE IF NOT EXISTS ' . $this->db);
    }

    public function environmentVariables(): array
    {
        return [
            'DB_CONNECTION' => $this->connection,
            'DB_DATABASE'   => $this->database,
            'DB_USERNAME'   => $this->username,
            'DB_PASSWORD'   => $this->password,
        ];
    }
}
