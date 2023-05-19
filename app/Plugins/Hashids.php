<?php

namespace Bellows\Plugins;

use Bellows\Plugin;
use Illuminate\Support\Str;

class Hashids extends Plugin
{
    protected array $anyRequiredComposerPackages = [
        'mtvs/eloquent-hashids',
        'vinkla/hashids',
    ];

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
