<?php

namespace Bellows\Processes;

use Bellows\Config\KickoffConfigKeys;
use Bellows\Data\InstallationData;
use Bellows\PluginSdk\Facades\Artisan;
use Bellows\PluginSdk\Facades\Console;
use Closure;
use Illuminate\Support\Facades\Process;

class PublishVendorFiles
{
    public function __invoke(InstallationData $installation, Closure $next)
    {
        Console::step('Vendor Publish');

        $tags = collect(
            $installation->config->get(KickoffConfigKeys::VENDOR_PUBLISH_TAGS)
        )->map(fn ($tag) => compact('tag'));

        $providers = collect(
            $installation->config->get(KickoffConfigKeys::VENDOR_PUBLISH_PROVIDERS)
        )->map(fn ($provider) => compact('provider'));

        $fromConfig = $tags->merge($providers)->merge($installation->config->get(KickoffConfigKeys::VENDOR_PUBLISH));

        collect($installation->manager->vendorPublish($fromConfig->toArray()))->map(
            fn ($t) => collect($t)->filter()->map(
                fn ($value, $key) => sprintf('--%s="%s"', $key, $value)
            )->implode(' ')
        )->each(fn ($params) => Process::runWithOutput(
            Artisan::local("vendor:publish {$params}"),
        ));

        return $next($installation);
    }
}
