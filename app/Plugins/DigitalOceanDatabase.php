<?php

namespace App\Plugins;

use App\Bellows\Data\AddApiCredentialsPrompt;
use App\Bellows\Plugin;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class DigitalOceanDatabase extends Plugin
{
    protected string $host;

    protected string $port;

    protected string $password;

    protected string $databaseName;

    protected string $databaseUser;

    protected string $dbType;

    public function setup(): void
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
            $db = $this->console->choiceFromCollection(
                'Choose a database',
                $dbs,
                'name'
            );
        }

        $this->dbType = collect(['pg' => 'pgsql'])->get($db['engine'], $db['engine']);

        if (!$this->setUser($db) || !$this->setDatabase($db)) {
            $this->setup();
            return;
        }

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

        return $teamName === 'My Team' ? null : Str::slug($teamName);
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

    public function setEnvironmentVariables(): array
    {
        return [
            'DB_CONNECTION'        => $this->dbType,
            'DB_DATABASE'          => $this->databaseName,
            'DB_USERNAME'          => $this->databaseUser,
            'DB_HOST'              => $this->host,
            'DB_PORT'              => $this->port,
            'DB_PASSWORD'          => $this->password,
            'DB_ALLOW_DISABLED_PK' => 'true',
        ];
    }
}
