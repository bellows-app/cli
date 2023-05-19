<?php

namespace Bellows\Plugins;

use Bellows\Data\AddApiCredentialsPrompt;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Http as BellowsHttp;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DigitalOceanDatabase extends Plugin implements Launchable, Deployable
{
    protected string $host;

    protected string $port;

    protected string $password;

    protected string $databaseName;

    protected string $databaseUser;

    protected string $dbType;

    public function __construct(
        protected BellowsHttp $http,
    ) {
    }

    public function launch(): void
    {
        $this->http->createJsonClient(
            'https://api.digitalocean.com/v2/',
            fn (PendingRequest $request, array $credentials) => $request->withToken($credentials['token']),
            new AddApiCredentialsPrompt(
                url: 'https://cloud.digitalocean.com/account/api/tokens',
                credentials: ['token'],
                displayName: 'DigitalOcean',
            ),
            fn (PendingRequest $request) => $request->get('databases', ['per_page' => 1]),
        );

        $this->databaseName = Console::ask('Database', Project::config()->isolatedUser);
        $this->databaseUser = Console::ask('Database User', $this->databaseName);

        $response = $this->http->client()->get('databases');

        $dbs = collect(
            $response->json()['databases']
        );

        if ($dbs->count() === 1) {
            $db = $dbs->first();
        } else {
            $db = Console::choiceFromCollection(
                'Choose a database',
                $dbs,
                'name'
            );
        }

        $this->dbType = collect(['pg' => 'pgsql'])->get($db['engine'], $db['engine']);

        if (!$this->setUser($db) || !$this->setDatabase($db)) {
            $this->launch();

            return;
        }

        $this->host = $db['private_connection']['host'];
        $this->port = $db['private_connection']['port'];
    }

    public function deploy(): bool
    {
        $current = $this->site->getEnv()->get('DB_HOST');

        if (
            $current
            && !Console::confirm("Your current database connection is pointed to {$current}, continue?", true)
        ) {
            return false;
        }

        $this->launch();

        return true;
    }

    public function canDeploy(): bool
    {
        return !Str::contains($this->site->getEnv()->get('DB_HOST'), 'db.ondigitalocean.com');
    }

    public function environmentVariables(): array
    {
        return [
            'DB_CONNECTION'        => $this->dbType,
            'DB_DATABASE'          => $this->databaseName,
            'DB_USERNAME'          => $this->databaseUser,
            'DB_HOST'              => $this->host,
            'DB_PORT'              => $this->port,
            'DB_PASSWORD'          => $this->password,
            'DB_ALLOW_DISABLED_PK' => true,
        ];
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
            if (!Console::confirm('User already exists, do you want to continue?', true)) {
                return false;
            }

            $this->password = $existingUser['password'];

            return true;
        }

        Console::miniTask('Creating user', $this->databaseUser);

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
            return Console::confirm('Database already exists, do you want to continue?', true);
        }

        Console::miniTask('Creating database', $this->databaseName);

        $this->http->client()->post(
            "databases/{$db['id']}/dbs",
            [
                'name' => $this->databaseName,
            ],
        );

        return true;
    }
}
