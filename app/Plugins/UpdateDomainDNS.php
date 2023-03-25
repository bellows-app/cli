<?php

namespace Bellows\Plugins;

use Bellows\Data\DefaultEnabledDecision;
use Bellows\Plugin;
use Bellows\Util\Domain;
use Illuminate\Support\Collection;

class UpdateDomainDNS extends Plugin
{
    public $priority = 100;

    public function isEnabledByDefault(): ?DefaultEnabledDecision
    {
        if (!$this->dnsProvider) {
            return $this->disabledByDefault('No DNS provider found for your domain');
        }

        $needsUpdating = $this->getDomainsToCheck()->first(
            fn ($subdomain) => $subdomain === 'www'
                ? !in_array($this->dnsProvider->getCNAMERecord($subdomain), [$this->projectConfig->domain, '@'])
                : $this->dnsProvider->getARecord($subdomain) !== $this->server->ip_address,
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
            fn ($subdomain) => Domain::getFullDomain($subdomain, $this->projectConfig->domain)
        );

        $description = $domains->count() === 1 ? 'record' : 'records';

        return $this->console->confirm(
            "Update DNS {$description} for [{$domains->join(', ')}] to point to server?",
            true
        );
    }

    public function setup(): void
    {
        $this->console->info('Updating DNS records...');

        $this->getDomainsToCheck()
            ->map(
                fn ($subdomain) => [
                    'subdomain'  => $subdomain,
                    'domain'     => Domain::getFullDomain($subdomain, $this->projectConfig->domain),
                    'ip_address' => $this->dnsProvider->getARecord($subdomain),
                ],
            )
            ->filter(
                fn ($config) => $config['ip_address'] && $config['ip_address'] !== $this->server->ip_address
                    ? $this->console->confirm(
                        "DNS record for [{$config['domain']}] currently points to [{$config['ip_address']}]. Update to [{$this->server->ip_address}]?",
                        true
                    )
                    : true,
            )
            ->filter(fn ($config) => $config['ip_address'] !== $this->server->ip_address)
            ->each(
                fn ($config) => $config['subdomain'] === 'www' ? $this->dnsProvider->addCNAMERecord(
                    $config['subdomain'],
                    '@',
                    1800,
                ) : $this->dnsProvider->addARecord(
                    $config['subdomain'],
                    $this->server->ip_address,
                    1800,
                ),
            );
    }

    protected function getDomainsToCheck(): Collection
    {
        if (Domain::isBaseDomain($this->projectConfig->domain)) {
            return collect(['www', '']);
        }

        return collect([Domain::getSubdomain($this->projectConfig->domain)]);
    }
}
