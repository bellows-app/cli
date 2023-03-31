<?php

namespace Bellows\ServerProviders\Forge;

use Bellows\Console;
use Bellows\Data\Daemon;
use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Data\Job;
use Bellows\Data\PhpVersion;
use Bellows\ServerProviders\ServerInterface;
use Composer\Semver\Semver;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Server implements ServerInterface
{
    protected PendingRequest $client;

    public function __construct(
        protected ForgeServer $server,
        protected Console $console
    ) {
        $this->client = Http::forge()->baseUrl(
            Forge::API_URL . "/servers/{$server->id}"
        );
    }

    /**
     * @throws Exception
     */
    public function phpVersionFromProject($projectDir): PhpVersion
    {
        $composerJson = File::json($projectDir . '/composer.json');

        $requiredPhpVersion = $composerJson['require']['php'] ?? null;

        $phpVersion = $this->console->withSpinner(
            title: 'Determining PHP Version',
            task: function () use ($requiredPhpVersion) {
                $phpVersions = collect($this->client->get('php')->json())->sortByDesc('version');

                return $requiredPhpVersion ? $phpVersions->first(
                    fn ($p) => Semver::satisfies(
                        Str::replace('php', '', $p['binary_name']),
                        $requiredPhpVersion
                    )
                ) : $phpVersions->first();
            },
            message: fn ($result) => $result['binary_name'] ?? null,
            success: fn ($result) => $result !== null,
        );

        if ($phpVersion) {
            return new PhpVersion(name:$phpVersion['version'], binary: $phpVersion['binary_name']);
        }

        $available = collect([
            'php82' => '8.2',
            'php81' => '8.1',
            'php80' => '8.0',
            'php74' => '7.4',
            'php73' => '7.3',
            'php72' => '7.2',
            'php71' => '7.1',
            'php70' => '7.0',
            'php56' => '5.6',
        ]);

        $toInstall = $available->first(
            fn ($v, $k) => Semver::satisfies($v, $requiredPhpVersion)
        );

        if (! $toInstall || ! $this->console->confirm("PHP {$toInstall} is required, but not installed. Install it now?", true)) {
            throw new Exception('No PHP version on server found that matches the required version in composer.json');
        }

        $phpVersion = $this->installPhpVersion($available->search($toInstall));

        return new PhpVersion(name:$phpVersion['version'], binary: $phpVersion['binary_name']);
    }

    /** @return \Illuminate\Support\Collection<\Bellows\Data\ForgeSite> */
    public function getSites(): Collection
    {
        return collect($this->client->get('sites')->json()['sites'])->map(fn ($site) => ForgeSite::from($site));
    }

    public function getSiteByDomain(string $domain): ?ForgeSite
    {
        $existingDomain = $this->console->withSpinner(
            title: 'Checking for existing domain on server',
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

    public function createSite(array $params): Site
    {
        $siteResponse = $this->client->post('sites', $params)->json();

        $site = $siteResponse['site'];

        while ($site['status'] !== 'installed') {
            sleep(2);

            $site = $this->client->get("sites/{$site['id']}")->json()['site'];
        }

        $site = ForgeSite::from($site);

        return new Site($site, $this->server, $this->console);
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

    // TODO: Better return type
    protected function installPhpVersion(string $version): ?array
    {
        return $this->console->withSpinner(
            title: 'Installing PHP on server',
            task: function () use ($version) {
                $this->cient->post('php', ['version' => $version]);

                do {
                    $phpVersion = collect($this->cient->get('php')->json())->first(
                        fn ($p) => $p['version'] === $version
                    );
                } while ($phpVersion['status'] !== 'installed');

                return $phpVersion;
            },
            message: fn ($result) => $result['binary_name'] ?? null,
            success: fn ($result) => $result !== null,
        );
    }

    public function __get($name)
    {
        return $this->server->{$name};
    }
}
