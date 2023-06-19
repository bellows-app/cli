<?php

namespace Bellows\Data;

use Bellows\Contracts\ServerProviderServer;
use Bellows\Contracts\ServerProviderSite;
use Bellows\PluginManagers\DeploymentManager;
use Spatie\LaravelData\Data;

class DeploymentData extends Data
{
    public function __construct(
        public readonly DeploymentManager $manager,
        public readonly ServerProviderSite $site,
        public readonly ServerProviderServer $server,
        public array $summary = [],
    ) {
    }
}
