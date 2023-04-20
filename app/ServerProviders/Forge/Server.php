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
use Illuminate\Support\Str;

class Server implements ServerInterface
{
    protected PendingRequest $client;

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
    public function validPhpVersionsFromProject(): Collection
    {
        $requiredPhpVersion = $this->getRequiredPhpVersion(Project::dir());

        $phpVersions = collect($this->client->get('php')->json())->sortByDesc('version');

        return $phpVersions->filter(
            fn ($p) => Semver::satisfies(
                Str::replace('php', '', $p['binary_name']),
                $requiredPhpVersion
            )
        )->map(
            fn ($phpVersion) => new PhpVersion(
                version: $phpVersion['version'],
                binary: $phpVersion['binary_name'],
                display: $phpVersion['displayable_version'],
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
        $existingDomain = Console::withSpinner(
            title: 'Checking for existing domain on ' . $this->server->name,
            task: fn () => collect($this->client->get('sites')->json()['sites'])->first(
                fn ($site) => $site['name'] === $domain
            ),
            message: fn ($result) => $result ? 'Domain already exists on server!' : 'No site found, on we go!',
            success: fn ($result) => $result === null,
        );

        if ($existingDomain) {
            return ForgeSite::from($existingDomain);
        }

        return $existingDomain;
    }

    public function createSite(CreateSiteParams $params): Site
    {
        $siteResponse = $this->client->post('sites', $params->toArray())->json();

        $site = $siteResponse['site'];

        while ($site['status'] !== 'installed') {
            sleep(2);

            $site = $this->client->get("sites/{$site['id']}")->json()['site'];
        }

        $site = ForgeSite::from($site);

        return new Site($site, $this->server);
    }

    public function createDaemon(Daemon $daemon): array
    {
        return $this->client->post('daemons', $daemon->toArray())->json();
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
                        $phpVersion = collect($this->client->get('php')->json())->first(
                            fn ($p) => $p['version'] === $version
                        );

                        return new PhpVersion(
                            version: $phpVersion['version'],
                            binary: $phpVersion['binary_name'],
                            display: $phpVersion['displayable_version'],
                        );
                    }

                    throw $e;
                }

                do {
                    $phpVersion = collect($this->client->get('php')->json())->first(
                        fn ($p) => $p['version'] === $version
                    );

                    sleep(2);
                } while ($phpVersion['status'] !== 'installed');

                return new PhpVersion(
                    version: $phpVersion['version'],
                    binary: $phpVersion['binary_name'],
                    display: $phpVersion['displayable_version'],
                );
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
