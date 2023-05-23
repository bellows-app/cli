<?php

namespace Bellows\Plugins;

use Bellows\Facades\Project;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Helpers\CanBeInstalled;
use Illuminate\Support\Facades\Process;

class Duster extends Plugin implements Installable
{
    use CanBeInstalled;

    public function composerDevPackagesToInstall(): array
    {
        return [
            'tightenco/duster',
        ];
    }

    public function installWrapUp(): void
    {
        // TODO: Make this configurable?
        // plugin-files/pint.json? Or make it part of the config, point a file?
        Project::writeFile('pint.json', <<<'JSON'
{
    "preset": "laravel",
    "rules": {
        "concat_space": {
            "spacing":  "one"
        },
        "not_operator_with_successor_space": false,
        "binary_operator_spaces": {
            "operators": {
                "=": "single_space",
                "=>": "align"
            }
        }
    }
}
JSON);

        Process::runWithOutput('./vendor/bin/duster fix');
    }
}
