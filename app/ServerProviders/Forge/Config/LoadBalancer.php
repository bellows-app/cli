<?php

namespace Bellows\ServerProviders\Forge\Config;

use Bellows\Data\CreateSiteParams;
use Bellows\Data\ForgeServer;
use Bellows\Data\ForgeSite;
use Bellows\Data\PhpVersion;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Contracts\ServerProviders\ServerInterface;
use Bellows\PluginSdk\Contracts\ServerProviders\SiteInterface;
use Bellows\ServerProviders\AsksForDomain;
use Bellows\ServerProviders\Forge\Client;
use Bellows\ServerProviders\Forge\Server;
use Bellows\ServerProviders\Forge\Site;
use Bellows\ServerProviders\ServerDeployTarget;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LoadBalancer implements ServerDeployTarget
{
    use AsksForDomain;

    protected string $domain;

    protected Collection $servers;

    protected SiteInterface $primarySite;

    protected PendingRequest $client;

    public function __construct(
        protected ServerInterface $server,
    ) {
        $this->client = Client::getInstance()->http();
    }

    public function getExistingSite(): ?SiteInterface
    {
        foreach ($this->servers as $server) {
            $site = Console::withSpinner(
                title: 'Checking for existing domain on ' . $this->server->name,
                task: fn () => $server->getSiteByDomain($this->getDomain()),
                message: fn ($result) => $result ? 'Domain already exists on server!' : 'No site found, on we go!',
                success: fn ($result) => $result === null,
            );

            if ($site) {
                return new Site($site, $server->serverData());
            }
        }

        return null;
    }

    /**
     * @return Collection<ServerInterface>
     */
    public function sites(): Collection
    {
        return $this->servers->map(
            fn (ServerInterface $server) => $server->getSiteByDomain($this->primarySite->name)
        )
            // TODO: We're doing that as a failsafe... is this ok? I guess we should only deploy to valid sites?
            ->filter()
            ->map(fn (ForgeSite $site, $i) => new Site($site, $this->servers[$i]->serverData()));
    }

    public function getDomain(): string
    {
        $this->domain ??= $this->askForDomain();

        return $this->domain;
    }

    public function setupForDeploy(SiteInterface $site): void
    {
        $this->primarySite = $site;
        $this->setLoadBalancedServers();
    }

    public function setupForLaunch(): void
    {
        if ($site = $this->server->getSiteByDomain($this->getDomain())) {
            if (Console::confirm('Load balancer already exists, use it?', true)) {
                $this->setupForDeploy(new Site($site, $this->server->serverData()));

                return;
            }

            unset($this->domain);

            $this->getDomain();
            $this->setupForLaunch();

            return;
        }

        $lbMethods = [
            'round_robin' => 'Round Robin',
            'least_conn'  => 'Least Connections',
            'ip_hash'     => 'IP Hash',
        ];

        $methodChoice = Console::choice(
            'Load balancing method',
            array_values($lbMethods),
            'Round Robin',
        );

        $lbMethod = array_search($methodChoice, $lbMethods);

        // Select servers to add to the load balancer
        $lbServers = Console::choiceFromCollection(
            'Select servers to add to the load balancer',
            collect($this->client->get('servers')->json()['servers'])
                ->filter(fn (array $server) => $server['type'] !== 'loadbalancer')
                ->sortBy('name')
                ->values(),
            'name',
            null,
            null,
            true
        );

        $serverParams = $lbServers->map(function (array $server) {
            Console::info('Config for: ' . $server['name']);

            return [
                'server' => $server,
                'weight' => Console::askForNumber('Weight', 1),
                'port'   => Console::askForNumber('Port', 80),
                'backup' => Console::confirm(
                    'Backup (Indicates if this server should serve as a fallback when all
other servers are down.)',
                    false
                ),
            ];
        });

        $lbParams = [
            'method'  => $lbMethod,
            'servers' => $serverParams->map(fn ($params) => [
                'id'     => $params['server']['id'],
                'weight' => $params['weight'],
                'port'   => $params['port'],
                'backup' => $params['backup'],
            ])->toArray(),
        ];

        $phpVersion = $this->server->validPhpVersionsFromProject()->sortByDesc('version')->first();

        // Create the load balanced site
        $lbSite = $this->server->createSite(new CreateSiteParams(
            domain: $this->getDomain(),
            projectType: 'php',
            directory: '/public',
            isolated: false,
            username: 'forge', // Always forge? Probably.
            phpVersion: $phpVersion->version,
        ));

        $this->primarySite = $lbSite;

        Client::getInstance()->http()->put(
            "servers/{$this->server->id}/sites/{$lbSite->id}/balancing",
            $lbParams,
        )->throw();

        $this->setLoadBalancedServers();
    }

    public function servers(): Collection
    {
        return $this->servers;
    }

    public function determinePhpVersion(): PhpVersion
    {
        // Figure out the PHP version for the load balanced sites
        $versions = Console::withSpinner(
            title: 'Determining installed PHP versions',
            task: fn () => $this->servers->map(fn (ServerInterface $server) => $server->validPhpVersionsFromProject()),
            message: fn ($result) => $result->flatten()->unique('version')->sortByDesc('version')->values()->map(fn (PhpVersion $version) => $version->display)->join(', '),
            success: fn ($result) => true,
        );

        $flattened = $versions->flatten();

        $byVersion = $flattened->groupBy('version');

        $commonVersion = $byVersion->first(fn ($versions) => $versions->count() === $this->servers->count());

        if ($commonVersion) {
            Console::miniTask('Using PHP version', $commonVersion->first()->display, true);

            return $commonVersion->first();
        }

        $phpVersions = $flattened->unique('version')->sortByDesc('version')->values();

        $selectedVersion = Console::choice(
            'Select PHP version',
            $phpVersions->map(
                fn (PhpVersion $version) => Str::replace('PHP ', '', $version->display)
            )->toArray(),
        );

        $phpVersion = $phpVersions->first(
            fn (PhpVersion $version) => Str::replace('PHP ', '', $version->display) === $selectedVersion
        );

        $this->servers->each(fn (ServerInterface $server) => $server->installPhpVersion($phpVersion->version));

        return $phpVersion;
    }

    public function getPrimarySite(): ?SiteInterface
    {
        return $this->primarySite;
    }

    protected function setLoadBalancedSite(): void
    {
        $sites = collect($this->client->get("/servers/{$this->server->id}/sites")->json()['sites']);

        $site = Console::choiceFromCollection(
            'Which site do you want to use?',
            $sites,
            'name',
        );

        $this->primarySite = new Site(ForgeSite::from($site), $this->server->serverData());
    }

    protected function setLoadBalancedServers(): void
    {
        $nodes = $this->client->get("servers/{$this->server->id}/sites/{$this->primarySite->id}/balancing")->json()['nodes'];

        $this->servers = collect($nodes)->map(
            fn ($node) => $this->client->get("servers/{$node['server_id']}")['server']
        )->map(fn ($server) => new Server(
            ForgeServer::from($server),
        ));
    }
}
