<?php

namespace Bellows\Plugins;

use Bellows\PluginSdk\Contracts\Launchable;
use Bellows\PluginSdk\Data\DefaultEnabledDecision;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Facades\Dns;
use Bellows\PluginSdk\Facades\Project;
use Bellows\PluginSdk\Plugin;
use Bellows\PluginSdk\PluginResults\CanBeDeployed;
use Bellows\PluginSdk\PluginResults\DeployResult;
use Bellows\PluginSdk\Util\Domain;
use Illuminate\Support\Collection;

class UpdateDomainDNS extends Plugin implements Launchable
{
    use CanBeDeployed;

    public int $priority = 100;

    public function __construct()
    {
    }

    // public function isEnabledByDefault(): ?DefaultEnabledDecision
    // {
    //     // TODO: How do we properly deal with plugins that need to handle this logic internally? ...do they actually?
    //     if (!Dns::available()) {
    //         return $this->disabledByDefault('No DNS provider found for your domain');
    //     }

    //     $needsUpdating = $this->getDomainsToCheck()->first(
    //         fn ($subdomain) => $subdomain === 'www'
    //             ? !in_array(Dns::getCNAMERecord($subdomain), [Project::domain(), '@'])
    //             : Dns::getARecord($subdomain) !== Deployment::primaryServer()->ip_address,
    //     );

    //     if ($needsUpdating === null) {
    //         return $this->disabledByDefault('DNS records are up to date');
    //     }

    //     return null;
    // }

    // public function enabled(): bool
    // {
    //     if ($this->hasADefaultEnabledDecision() && !$this->isEnabledByDefault()->enabled) {
    //         return false;
    //     }

    //     // If we got this far, something needs updating, default to true
    //     $domains = $this->getDomainsToCheck()->map(
    //         fn ($subdomain) => Domain::getFullDomain($subdomain, Project::domain())
    //     );

    //     $description = $domains->count() === 1 ? 'record' : 'records';

    //     return Console::confirm(
    //         "Update DNS {$description} for [{$domains->join(', ')}] to point to server?",
    //         true
    //     );
    // }

    public function deploy(): ?DeployResult
    {
        // TODO: DO we still need to do this once?
        $this->updateDomainRecords();

        return null;
    }

    protected function updateDomainRecords(): void
    {
        Console::info('Updating DNS records...');

        $this->getDomainsToCheck()
            ->map(
                fn ($subdomain) => [
                    'subdomain'  => $subdomain,
                    'domain'     => Domain::getFullDomain($subdomain, Project::domain()),
                    'ip_address' => Dns::getARecord($subdomain),
                ],
            )
            ->filter(
                fn ($config) => $config['ip_address'] && $config['ip_address'] !== Deployment::primaryServer()->ip_address
                    ? Console::confirm(
                        "DNS record for [{$config['domain']}] currently points to [{$config['ip_address']}]. Update to [{Deployment::primaryServer()->ip_address}]?",
                        true
                    )
                    : true,
            )
            ->filter(fn ($config) => $config['ip_address'] !== Deployment::primaryServer()->ip_address)
            ->each(
                fn ($config) => $config['subdomain'] === 'www' ? Dns::addCNAMERecord(
                    $config['subdomain'],
                    '@',
                    1800,
                ) : Dns::addARecord(
                    $config['subdomain'],
                    Deployment::primaryServer()->ip_address,
                    1800,
                ),
            );
    }

    protected function getDomainsToCheck(): Collection
    {
        if (Domain::isBaseDomain(Project::domain())) {
            return collect(['www', '']);
        }

        return collect([Domain::getSubdomain(Project::domain())]);
    }
}
