<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Dns\DnsProvider;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Util\Domain;
use Illuminate\Support\Collection;

class UpdateDomainDNS extends Plugin implements Launchable
{
    public int $priority = 100;

    public function __construct(
        protected ?DnsProvider $dnsProvider = null,
    ) {
    }

    public function isEnabledByDefault(): ?DefaultEnabledDecision
    {
        if (!$this->dnsProvider) {
            return $this->disabledByDefault('No DNS provider found for your domain');
        }

        $needsUpdating = $this->getDomainsToCheck()->first(
            fn ($subdomain) => $subdomain === 'www'
                ? !in_array($this->dnsProvider->getCNAMERecord($subdomain), [Project::config()->domain, '@'])
                : $this->dnsProvider->getARecord($subdomain) !== $this->primaryServer->ip_address,
        );

        if ($needsUpdating === null) {
            return $this->disabledByDefault('DNS records are up to date');
        }

        return null;
    }

    public function enabled(): bool
    {
        if ($this->hasADefaultEnabledDecision() && !$this->isEnabledByDefault()->enabled) {
            return false;
        }

        // If we got this far, something needs updating, default to true
        $domains = $this->getDomainsToCheck()->map(
            fn ($subdomain) => Domain::getFullDomain($subdomain, Project::config()->domain)
        );

        $description = $domains->count() === 1 ? 'record' : 'records';

        return Console::confirm(
            "Update DNS {$description} for [{$domains->join(', ')}] to point to server?",
            true
        );
    }

    public function launch(): void
    {
        once(fn () => $this->updateDomainRecords());
    }

    protected function updateDomainRecords(): void
    {
        Console::info('Updating DNS records...');

        $this->getDomainsToCheck()
            ->map(
                fn ($subdomain) => [
                    'subdomain'  => $subdomain,
                    'domain'     => Domain::getFullDomain($subdomain, Project::config()->domain),
                    'ip_address' => $this->dnsProvider->getARecord($subdomain),
                ],
            )
            ->filter(
                fn ($config) => $config['ip_address'] && $config['ip_address'] !== $this->primaryServer->ip_address
                    ? Console::confirm(
                        "DNS record for [{$config['domain']}] currently points to [{$config['ip_address']}]. Update to [{$this->primaryServer->ip_address}]?",
                        true
                    )
                    : true,
            )
            ->filter(fn ($config) => $config['ip_address'] !== $this->primaryServer->ip_address)
            ->each(
                fn ($config) => $config['subdomain'] === 'www' ? $this->dnsProvider->addCNAMERecord(
                    $config['subdomain'],
                    '@',
                    1800,
                ) : $this->dnsProvider->addARecord(
                    $config['subdomain'],
                    $this->primaryServer->ip_address,
                    1800,
                ),
            );
    }

    protected function getDomainsToCheck(): Collection
    {
        if (Domain::isBaseDomain(Project::config()->domain)) {
            return collect(['www', '']);
        }

        return collect([Domain::getSubdomain(Project::config()->domain)]);
    }
}
