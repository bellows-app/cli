<?php

namespace Bellows\Plugins;

use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;
use Illuminate\Support\Str;

class Hashids extends Plugin implements Launchable, Deployable
{
    protected array $anyRequiredComposerPackages = [
        'mtvs/eloquent-hashids',
        'vinkla/hashids',
    ];

    public function launch(): void
    {
        // Nothing to do here
    }

    public function deploy(): bool
    {
        return true;
    }

    public function canDeploy(): bool
    {
        return !$this->site->getEnv()->has('HASH_IDS_SALT');
    }

    public function environmentVariables(): array
    {
        return [
            'HASH_IDS_SALT' => Str::random(16),
        ];
    }
}
