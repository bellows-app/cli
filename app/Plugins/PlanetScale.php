<?php

namespace Bellows\Plugins;

use Bellows\Data\AddApiCredentialsPrompt;
use Bellows\Env;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Http as BellowsHttp;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PlanetScale extends Plugin implements Launchable, Deployable
{
    protected array $credentials = [];

    protected string $organizationName = 'YOUR-ORGANIZATION-SLUG';

    protected string $databaseName;

    protected string $branchName;

    public function __construct(
        protected BellowsHttp $http,
    ) {
    }

    public function getName(): string
    {
        return 'PlanetScale';
    }

    public function launch(): void
    {
        $this->http->createJsonClient(
            'https://api.planetscale.com/v1/',
            fn (PendingRequest $request, array $credentials) => $request->withHeaders([
                'Authorization' => $credentials['service_token_id'] . ':' . $credentials['service_token'],
            ]),
            new AddApiCredentialsPrompt(
                url: 'https://app.planetscale.com/YOUR-ORGANIZATION-SLUG/settings/service-tokens',
                credentials: ['service_token_id', 'service_token'],
                displayName: 'PlanetScale',
                helpText: 'Once you are logged into your organization, ' .
                    'click the <comment>Settings</comment> tab, then <comment>Service Tokens</comment>.',
                requiredScopes: [
                    'read_organization',
                    'read_databases',
                    '{database}:read_branch',
                    '{database}:read_database',
                    '{database}:connect_branch',
                ],
                optionalScopes: [
                    'create_databases',
                    '{database}:create_branch',
                ],
            ),
            fn (PendingRequest $request) => $request->get('organizations', ['per_page' => 1]),
        );

        $organizations = $this->http->client()->get('organizations', ['per_page' => 1])->json();

        if ($this->isForbidden($organizations)) {
            $this->missingScope('list organizations', ['read_organization']);

            return;
        }

        // A service token can only be attached to one organization, so we can safely assume
        // that the first organization in the list is the one we want.
        $organization = $organizations['data'][0];

        $this->organizationName = $organization['name'];

        $database = $this->getDatabase();

        if ($database === null) {
            return;
        }

        $this->databaseName = $database['name'];

        $branch = $this->getBranch();

        if ($branch === null) {
            return;
        }

        $this->branchName = $branch['name'];

        $password = $this->getPassword();

        if ($password === null) {
            return;
        }

        $credentials = new Env($password['connection_strings']['laravel']);

        $this->credentials = $credentials->all();
        // Forge only uses Ubuntu so just ensure that that this is the value
        $this->credentials['MYSQL_ATTR_SSL_CA'] = '/etc/ssl/certs/ca-certificates.crt';
    }

    public function deploy(): bool
    {
        $current = $this->site->getEnv()->get('DB_HOST');

        if (
            $current
            && !Str::contains($current, 'psdb.cloud')
            && !Console::confirm("Your current database connection is pointed to {$current}, continue?", true)
        ) {
            return false;
        }

        $this->launch();

        return true;
    }

    public function canDeploy(): bool
    {
        return !Str::contains($this->primarySite->getEnv()->get('DB_HOST'), 'psdb.cloud')
            || !$this->site->getEnv()->has('MYSQL_ATTR_SSL_CA');
    }

    public function environmentVariables(): array
    {
        return $this->credentials;
    }

    protected function getBranch(): ?array
    {
        $branches = $this->http->client()->get(
            "organizations/{$this->organizationName}/databases/{$this->databaseName}/branches"
        )->json();

        if ($this->isForbidden($branches)) {
            $this->missingScope('list database branches', ['{database}:read_branch']);

            return null;
        }

        $branch = Console::choiceFromCollection(
            'Branch',
            collect($branches['data'])->concat([['name' => 'Create new branch']]),
            'name'
        );

        if ($branch['name'] === 'Create new branch') {
            return $this->createBranch(collect($branches['data']));
        }

        return $branch;
    }

    protected function getPassword(): ?array
    {
        $password = $this->http->client()->post(
            "organizations/{$this->organizationName}/databases/{$this->databaseName}/branches/{$this->branchName}/passwords"
        )->json();

        if ($this->isForbidden($password)) {
            $this->missingScope('create a password', ['{database}:connect_branch']);

            return null;
        }

        return $password;
    }

    protected function getDatabase(): ?array
    {
        $databases = $this->http->client()->get("organizations/{$this->organizationName}/databases")->json();

        if ($this->isForbidden($databases)) {
            $this->missingScope('list databases', ['read_databases', '{database}:read_database']);

            return null;
        }

        if (count($databases['data']) === 0) {
            Console::warn('No databases found.');
            Console::confirm('Create a new database?', true);

            return $this->createDatabase();
        }

        $database = Console::choiceFromCollection(
            'Database',
            collect($databases['data'])->concat([['name' => 'Create new database']]),
            'name'
        );

        if ($database['name'] === 'Create new database') {
            return $this->createDatabase();
        }

        return $database;
    }

    protected function createBranch(Collection $existingBranches): ?array
    {
        $branchName = Console::askRequired('Branch name');

        $parentBranch = Console::choiceFromCollection(
            'Parent branch',
            $existingBranches,
            'name'
        );

        $branch = $this->http->client()->post(
            "organizations/{$this->organizationName}/databases/{$this->databaseName}/branches",
            ['name' => $branchName, 'parent_branch' => $parentBranch['name']],
        )->json();

        if ($this->isForbidden($branch)) {
            $this->missingScope('create a branch', ['{database}:create_branch']);

            return null;
        }

        return $branch;
    }

    protected function createDatabase(): ?array
    {
        $databaseName = Console::ask('Database name', Project::config()->isolatedUser);

        $database = $this->http->client()->post(
            "organizations/{$this->organizationName}/databases",
            ['name' => $databaseName]
        )->json();

        if ($this->isForbidden($database)) {
            $this->missingScope('create a database', ['create_databases']);

            return null;
        }

        if (Str::contains(Arr::get($database, 'message'), 'This organization is at its limit')) {
            Console::warn(Arr::get($database, 'message'));

            if (Console::confirm('Continue <comment>without</comment> setting up PlanetScale?', true)) {
                return null;
            }

            Console::info('Starting PlanetScale setup again...');

            $this->http->clearClients();
            $this->launch();

            return null;
        }

        // We've created a database! Now we need them to update the service token
        // scopes to include the new database.
        // Confirm here when that's done.
        Console::info('Database created! Now we need to update the service token scopes to include the new database.');

        Console::newLine();

        Console::info("Head here: <comment>https://app.planetscale.com/{$this->organizationName}/settings/service-tokens</comment>");

        Console::newLine();

        $scopes = implode(', ', array_map(fn ($s) => "<comment>{$s}</comment>", [
            'read_database',
            'connect_branch',
            'read_branch',
            'create_branch',
        ]));

        Console::info('- Click on your service token');
        Console::info('- Under <comment>Database access</comment>, click <comment>Add a database</comment>');
        Console::info("- Select <comment>{$database['name']}</comment> from the dropdown");
        Console::info("- Add the following scopes: {$scopes}.");

        Console::confirm('Once you have added the scopes: Continue?', true);

        return $database;
    }

    protected function isForbidden(array $response): bool
    {
        return Arr::get($response, 'code') === 'forbidden';
    }

    protected function missingScope(string $action, array $scopes): void
    {
        $scope = implode(', ', array_map(fn ($s) => "<comment>{$s}</comment>", $scopes));
        $descriptor = Str::plural('scope', $scopes);

        Console::warn("Bellows doesn't have permission to {$action}.");
        Console::info("Make sure your service token has the {$scope} {$descriptor}.");
        Console::info('You can update your service token at ' .
            "<comment>https://app.planetscale.com/{$this->organizationName}/settings/service-tokens</comment>");
    }
}
