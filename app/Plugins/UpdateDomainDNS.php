<?php

namespace App\Plugins;

use App\Bellows\Data\DefaultEnabledDecision;
use App\Bellows\Plugin;
use App\Bellows\Util\Domain;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class UpdateDomainDNS extends Plugin
{
    public $priority = 100;

    public function isEnabledByDefault(): ?DefaultEnabledDecision
    {
        if (!$this->dnsProvider) {
            return $this->disabledByDefault('No DNS provider found for your domain');
        }

        // TODO: We should pass this in as an argument? Don't love this.
        $server = Http::forgeServer()->get('/')->json();

        $needsUpdating = $this->getDomainsToCheck()->first(
            fn ($subdomain) => $this->dnsProvider->getARecord($subdomain) !== $server['server']['ip_address']
        );


        if ($needsUpdating === null) {
            return $this->disabledByDefault('DNS records are up to date');
        }

        return null;
    }

    public function enabled(): bool
    {
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

    protected function getDomainsToCheck(): Collection
    {
        if ($this->projectConfig->domain === Domain::getBaseDomain($this->projectConfig->domain)) {
            return collect(['www', '']);
        }

        return collect([Domain::getSubdomain($this->projectConfig->domain)]);
    }

    public function setup($server): void
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
                fn ($config) => $config['ip_address'] && $config['ip_address'] !== $server['ip_address']
                    ? $this->console->confirm(
                        "DNS record for [{$config['domain']}] currently points to [{$config['ip_address']}]. Update to [{$server['ip_address']}]?",
                        true
                    )
                    : true,
            )
            ->filter(fn ($config) => $config['ip_address'] !== $server['ip_address'])
            ->each(
                fn ($config) => $this->dnsProvider->addARecord(
                    $config['subdomain'],
                    $server['ip_address'],
                    1800,
                )
            );
    }
}
