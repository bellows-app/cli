<?php

namespace App\Plugins;

use App\Bellows\Plugin;
use Illuminate\Support\Str;

class Hashids extends Plugin
{
    protected array $anyRequiredComposerPackages = [
        'mtvs/eloquent-hashids',
        'vinkla/hashids',
    ];

    public function setEnvironmentVariables(): array
    {
        return [
            'HASH_IDS_SALT' => Str::random(16),
        ];
    }
}
