<?php

namespace Bellows\Processes;

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

        $fromConfig = collect($installation->config->get('vendor-publish-tags', []))->map(fn ($tag) => compact('tag'))->merge(
            collect($installation->config->get('vendor-publish-providers', []))->map(fn ($provider) => compact('provider')),
        )->merge($installation->config->get('vendor-publish', []));

        collect($installation->manager->vendorPublish())->map(
            fn ($t) => collect($t)->filter()->map(
                fn ($value, $key) => sprintf('--%s="%s"', $key, $value)
            )->implode(' ')
        )->each(
            fn ($params) => Process::runWithOutput(
                Artisan::local("vendor:publish {$params}"),
            ),
        );

        return $next($installation);
    }
}
