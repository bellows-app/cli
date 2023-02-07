<?php

namespace App\Plugins;

use App\DeployMate\Data\DefaultEnabledDecision;
use App\DeployMate\Data\AddApiCredentialsPrompt;
use App\DeployMate\Plugin;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DigitalOceanDatabase extends Plugin
{
    protected string $host;
    protected string $port;
    protected string $password;
    protected string $databaseName;
    protected string $databaseUser;

    public function isEnabledByDefault(): DefaultEnabledDecision
    {
        return $this->enabledByDefault('There is a good chance you are using a DigitalOcean database');
    }

    public function setup($server): void
    {
        $this->databaseName = $this->console->ask('Database', $this->projectConfig->isolatedUser);
        $this->databaseUser = $this->console->ask('Database User', $this->databaseName);

        $this->http->createJsonClient(
            'https://api.digitalocean.com/v2/',
            fn ($request, $credentials) => $request->withToken($credentials['token']),
            new AddApiCredentialsPrompt(
                url: 'https://cloud.digitalocean.com/account/api/tokens',
                credentials: ['token'],
            ),
        );

        $response = $this->http->client()->get('databases');

        $dbs = collect(
            $response->json()['databases']
        );

        if ($dbs->count() === 1) {
            $db = $dbs->first();
        } else {
            $dbName = $this->console->choice(
                'Which database?',
                $dbs->pluck('name')->sort()->values()->toArray(),
            );

            $db = $dbs->firstWhere('name', $dbName);
        }

        if (!$this->setUser($db) || !$this->setDatabase($db)) {
            $this->setup($server);
            return;
        }

        config()->set('database.connections.' . $db['name'], [
            'driver'    => 'mysql',
            'host'      => $db['connection']['host'],
            'port'      => $db['connection']['port'],
            'database'  => $this->databaseName,
            'username'  => $db['connection']['user'],
            'password'  => $db['connection']['password'],
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false
        ]);

        // $this->fixUserPermissions($db);

        $this->host = $db['private_connection']['host'];
        $this->port = $db['private_connection']['port'];
    }

    protected function getDefaultNewAccountName(string $token): ?string
    {
        $result = Http::baseUrl('https://api.digitalocean.com/v2/')
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->get('account')
            ->json();

        $teamName = Arr::get($result, 'account.team.name');

        return $teamName === 'My Team' ? null : $teamName;
    }

    protected function fixUserPermissions($db)
    {
        // TODO: Move this to its own plugin
        $sqlUserStatements = collect([
            "REVOKE SELECT, INSERT, UPDATE, DELETE, REFERENCES, CREATE, DROP, ALTER, INDEX, TRIGGER, REPLICATION CLIENT, REPLICATION SLAVE, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EXECUTE, RELOAD, PROCESS, CREATE TEMPORARY TABLES, LOCK TABLES, SHOW DATABASES, CREATE USER, GRANT OPTION, EVENT ON *.* FROM '{$this->databaseUser}'@'%';",

            "GRANT LOCK TABLES, SELECT, ALTER ROUTINE, SHOW VIEW, EVENT, DELETE, EXECUTE, UPDATE, INDEX, INSERT, CREATE, REFERENCES, ALTER, DROP, CREATE VIEW, GRANT OPTION, TRIGGER, CREATE ROUTINE, CREATE TEMPORARY TABLES ON `{$this->databaseName}`.* TO '{$this->databaseUser}'@'%';",

            "FLUSH PRIVILEGES;",
        ])->implode("\n\n");

        ray($sqlUserStatements);

        $result = DB::connection($db['name'])->statement($sqlUserStatements);

        ray($result)->label('Result of user statements');
    }

    protected function setUser($db)
    {
        $existingUser = collect($db['users'])->firstWhere('name', $this->databaseUser);

        if ($existingUser) {
            if (!$this->console->confirm('User already exists, do you want to continue?', true)) {
                return false;
            }

            $this->password = $existingUser['password'];

            return true;
        }

        $newDbUser = $this->http->client()->post(
            "databases/{$db['id']}/users",
            [
                'name' => $this->databaseUser,
            ],
        )->json()['user'];

        $this->password = $newDbUser['password'];

        return true;
    }

    protected function setDatabase($db)
    {
        $existingDatabase = collect($db['db_names'])->contains($this->databaseName);

        if ($existingDatabase) {
            return $this->console->confirm('Database already exists, do you want to continue?', true);
        }

        $this->http->client()->post(
            "databases/{$db['id']}/dbs",
            [
                'name' => $this->databaseName,
            ],
        );

        return true;
    }

    public function setEnvironmentVariables($server, $site, array $envVars): array
    {
        return [
            'DB_DATABASE'          => $this->databaseName,
            'DB_USERNAME'          => $this->databaseUser,
            'DB_HOST'              => $this->host,
            'DB_PORT'              => $this->port,
            'DB_PASSWORD'          => $this->password,
            'DB_ALLOW_DISABLED_PK' => 'true',
        ];
    }
}
