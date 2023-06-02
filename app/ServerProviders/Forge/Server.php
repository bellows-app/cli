<?php

namespace Bellows\ServerProviders\Forge;

use Bellows\Data\CreateSiteParams;
use Bellows\Data\Daemon;
use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Data\Job;
use Bellows\Data\PhpVersion;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\ServerProviders\ServerInterface;
use Composer\Semver\Semver;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

class Server implements ServerInterface
{
    protected PendingRequest $client;

    protected Collection $daemons;

    protected Collection $jobs;

    public function __construct(
        protected ForgeServer $server,
    ) {
        $this->setClient();
    }

    public function setClient(): void
    {
        $this->client = Client::getInstance()->http()->baseUrl(
            Client::API_URL . "/servers/{$this->server->id}"
        );
    }

    public function determinePhpVersionFromProject(): PhpVersion
    {
        $validPhpVersions = $this->validPhpVersionsFromProject(Project::dir());

        if (!$validPhpVersions->isEmpty()) {
            return $validPhpVersions->first();
        }

        // TODO: Hm, should we get this from the server instead of just having it hardcoded?
        $available = collect([
            'php56' => '5.6',
            'php70' => '7.0',
            'php71' => '7.1',
            'php72' => '7.2',
            'php73' => '7.3',
            'php74' => '7.4',
            'php80' => '8.0',
            'php81' => '8.1',
            'php82' => '8.2',
        ]);

        $requiredPhpVersion = $this->getRequiredPhpVersion(Project::dir());

        $toInstall = $available->first(
            fn ($v, $k) => Semver::satisfies($v, $requiredPhpVersion)
        );

        if (!$toInstall || !Console::confirm("PHP {$toInstall} is required, but not installed. Install it now?", true)) {
            throw new Exception('No PHP version on server found that matches the required version in composer.json');
        }

        return $this->installPhpVersion($available->search($toInstall));
    }

    /** @return Collection<PhpVersion> */
    public function getPhpVersions(): Collection
    {
        return collect($this->client->get('php')->json())
            ->sortByDesc('version')
            ->map(fn ($version) => new PhpVersion(
                version: $version['version'],
                binary: $version['binary_name'],
                display: $version['displayable_version'],
                status: $version['status'],
                used_as_default: $version['used_as_default'],
                used_on_cli: $version['used_on_cli'],
            ));
    }

    /** @return Collection<PhpVersion> */
    public function validPhpVersionsFromProject(): Collection
    {
        $requiredPhpVersion = $this->getRequiredPhpVersion(Project::dir());

        return $this->getPhpVersions()->filter(
            fn (PhpVersion $p) => Semver::satisfies(
                Str::replace('php', '', $p->binary),
                $requiredPhpVersion
            )
        )->values();
    }

    /** @return \Illuminate\Support\Collection<\Bellows\Data\ForgeSite> */
    public function getSites(): Collection
    {
        return collect(
            $this->client->get('sites')->json()['sites']
        )->map(fn ($site) => ForgeSite::from($site));
    }

    public function getSiteByDomain(string $domain): ?ForgeSite
    {
        $existingDomain = collect($this->client->get('sites')->json()['sites'])->first(
            fn ($site) => $site['name'] === $domain
        );

        if ($existingDomain) {
            return ForgeSite::from($existingDomain);
        }

        return $existingDomain;
    }

    public function createSite(CreateSiteParams $params): Site
    {
        $params = $params->toArray();

        if ($params['username'] === 'forge') {
            $params['username'] = null;
            $params['isolated'] = false;
        }

        $siteResponse = $this->client->post('sites', $params)->json();

        $site = $siteResponse['site'];

        while ($site['status'] !== 'installed') {
            Sleep::for(2)->seconds();

            try {
                $site = $this->client->get("sites/{$site['id']}")->json()['site'];
            } catch (RequestException $e) {
                if ($e->getCode() === 404) {
                    Console::error('There was an error creating the site on your server.');
                    Console::info('View your server in Forge for full details:');
                    Console::info("https://forge.laravel.com/servers/{$this->server->id}/sites");
                    exit;
                }

                throw $e;
            }
        }

        $site = ForgeSite::from($site);

        return new Site($site, $this->server);
    }

    public function getDaemons(): Collection
    {
        $this->daemons ??= collect($this->client->get('daemons')->json()['daemons']);

        return $this->daemons;
    }

    public function hasDaemon(string $command): bool
    {
        return $this->getDaemons()->contains(
            fn ($daemon) => $daemon['command'] === $command
        );
    }

    public function createDaemon(Daemon $daemon): array
    {
        return $this->client->post('daemons', $daemon->toArray())->json();
    }

    public function getJobs(): Collection
    {
        $this->jobs ??= collect($this->client->get('jobs')->json()['jobs']);

        return $this->jobs;
    }

    public function hasJob(string $command): bool
    {
        return $this->getJobs()->contains(
            fn ($job) => $job['command'] === $command
        );
    }

    public function createJob(Job $job): array
    {
        return $this->client->post('jobs', $job->toArray())->json();
    }

    // I don't love this method, but we have times when we need
    // an arbitrary site env that's not the primary site, so we have this.
    public function getSiteEnv(int $id): string
    {
        return (string) $this->client->get("sites/{$id}/env");
    }

    public function installPhpVersion(string $version): ?PhpVersion
    {
        return Console::withSpinner(
            title: 'Installing PHP on server',
            task: function () use ($version) {
                try {
                    $this->client->post('php', ['version' => $version]);
                } catch (RequestException $e) {
                    if ($e->getCode() === 422) {
                        // PHP version already installed
                        return $this->getPhpVersions()->first(
                            fn (PhpVersion $p) => $p->version === $version
                        );
                    }

                    throw $e;
                }

                do {
                    $phpVersion = $this->getPhpVersions()->first(
                        fn (PhpVersion $p) => $p->version === $version
                    );

                    Sleep::for(2)->seconds();
                } while ($phpVersion->status !== 'installed');

                return $phpVersion;
            },
            message: fn ($result) => $result->binary ?? null,
            success: fn ($result) => $result !== null,
            longProcessMessages: [
                5   => 'This is going to take a little while...',
                25  => 'Still working...',
                45  => 'One moment...',
                60  => 'Almost done...',
                75  => 'Just a little longer...',
                90  => 'Almost there...',
                120 => 'Wrapping up...',
            ],
        );
    }

    public function serverData(): ForgeServer
    {
        return $this->server;
    }

    protected function getRequiredPhpVersion(string $projectDir): string
    {
        $path = $projectDir . '/composer.json';

        if (!file_exists($path)) {
            return '*';
        }

        return File::json($path)['require']['php'] ?? '*';
    }

    public function __get($name)
    {
        return $this->server->{$name};
    }

    public function __serialize(): array
    {
        return [
            'server'  => $this->server,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->server = $data['server'];
        $this->setClient();
    }
}
