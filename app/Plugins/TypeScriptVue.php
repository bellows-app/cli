<?php

namespace Bellows\Plugins;

use Bellows\Facades\Project;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Helpers\CanBeInstalled;

class TypeScriptVue extends Plugin implements Installable
{
    use CanBeInstalled;

    public function npmPackagesToInstall(): array
    {
        return [
            '@vue/tsconfig',
        ];
    }

    public function installWrapUp(): void
    {
        // TODO: Good way to customize the aliases?
        // Maybe default stub but user can override stub for their config?
        // Or maybe in the config there is a way to customize arguments that we can pass into the install method?
        /**
         * {
         *    "TypeScriptVue": {
         *        "aliases": {
         *            "@": "resources/js"
         *        },
         *    }
         * }
         */
        Project::writeFile(
            'tsconfig.json',
            <<<'JSON'
{
    "extends": "@vue/tsconfig/tsconfig.json",
    "compilerOptions": {
        "target": "esnext",
        "module": "esnext",
        "strict": true,
        "jsx": "preserve",
        "moduleResolution": "node",
        "baseUrl": ".",
        "paths": {
            "@/*": ["resources/js/*"]
        }
    },
    "include": ["resources/js/**/*", "./*.js"],
    "exclude": ["node_modules"]
}
JSON
        );
    }
}
