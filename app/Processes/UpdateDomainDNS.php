<?php

namespace Bellows\Processes;

use Bellows\Data\DeploymentData;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Facades\Dns;
use Bellows\PluginSdk\Facades\Project;
use Bellows\Util\Domain;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UpdateDomainDNS
{
    public function __invoke(DeploymentData $deployment, Closure $next)
    {
        if (!Dns::available()) {
            return $next($deployment);
        }

        $allDomains = $this->getDomainsToCheck();

        $needsUpdating = $allDomains->first(
            fn ($subdomain) => $subdomain === 'www'
                ? !in_array(Dns::getCNAMERecord($subdomain), [Project::domain(), '@'])
                : Dns::getARecord($subdomain) !== Deployment::primaryServer()->ip_address,
        );

        if ($needsUpdating === null) {
            return $next($deployment);
        }

        // If we got this far, something needs updating
        $domains = $allDomains->map(
            fn ($subdomain) => Domain::getFullDomain($subdomain, Project::domain())
        );

        $question = sprintf(
            'Update DNS %s for [%s] to point to server?',
            Str::plural('record', $domains),
            $domains->join(', ')
        );

        if (!Console::confirm($question, true)) {
            return $next($deployment);
        }

        $this->updateDomainRecords();

        return $next($deployment);
    }

    protected function updateDomainRecords(): void
    {
        Console::step('Updating DNS Records');

        $this->getDomainsToCheck()
            ->map(fn ($subdomain) => [
                'subdomain'  => $subdomain,
                'domain'     => Domain::getFullDomain($subdomain, Project::domain()),
                'ip_address' => Dns::getARecord($subdomain),
            ])
            ->filter($this->confirmDomainUpdate(...))
            ->filter(fn ($config) => $config['ip_address'] !== Deployment::primaryServer()->ip_address)
            ->each($this->updateRecord(...));
    }

    protected function updateRecord(array $config)
    {
        if ($config['subdomain'] === 'www') {
            return Dns::addCNAMERecord($config['subdomain'], '@', 1800);
        }

        return Dns::addARecord(
            $config['subdomain'],
            Deployment::primaryServer()->ip_address,
            1800,
        );
    }

    protected function confirmDomainUpdate(array $config)
    {
        if ($config['ip_address'] === null) {
            return true;
        }

        if ($config['ip_address'] === Deployment::primaryServer()->ip_address) {
            return false;
        }

        return Console::confirm(
            sprintf(
                'DNS record for [%s] currently points to [%s]. Update to [%s]?',
                $config['domain'],
                $config['ip_address'],
                Deployment::primaryServer()->ip_address
            ),
            true
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
