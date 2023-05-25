<?php

namespace Bellows\Plugins;

use Bellows\Artisan;
use Bellows\DeployScript;
use Bellows\Facades\Project;
use Bellows\Git\Git;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Plugins\Helpers\CanBeInstalled;
use Bellows\Plugins\Helpers\CanBeLaunched;
use Bellows\Util\Vite;
use Illuminate\Support\Facades\File;

class MomentumTrail extends Plugin implements Launchable, Deployable, Installable
{
    use CanBeInstalled, CanBeLaunched;

    protected array $requiredComposerPackages = [
        'based/momentum-trail',
    ];

    public function deploy(): bool
    {
        return true;
    }

    public function canDeploy(): bool
    {
        return !$this->site->isInDeploymentScript('trail:generate');
    }

    public function npmPackagesToInstall(): array
    {
        return ['momentum-trail'];
    }

    public function npmDevPackagesToInstall(): array
    {
        return ['vite-plugin-watch'];
    }

    public function installWrapUp(): void
    {
        if (!Project::fileExists('resources/js/routes.json')) {
            Project::writeFile('resources/js/routes.json', '{}');
        }

        Git::ignore('resources/js/routes.json');

        Vite::addImport("import { watch } from 'vite-plugin-watch'");
        Vite::addPlugin(<<<'PLUGIN'
watch({
    pattern: 'routes/*.php',
    command: 'php artisan trail:generate',
})
PLUGIN);

        Project::file('resources/js/app.ts')
            ->addJsImport([
                "import { trail } from 'momentum-trail'",
                "import routes from '@/routes/routes.json'",
            ])
            ->replace('.use(ZiggyVue, Ziggy)', ".use(ZiggyVue, Ziggy)\n.use(trail, { routes })");
    }

    public function updateConfig(): array
    {
        return [
            'trail.output.routes'     => "resource_path('js/routes.json')",
            'trail.output.typescript' => "resource_path('types/routes.d.ts')",
        ];
    }

    public function publishTags(): array
    {
        return ['trail-config'];
    }

    public function updateDeployScript(string $deployScript): string
    {
        return DeployScript::addAfterComposerInstall(
            $deployScript,
            [
                <<<'SCRIPT'
if [ ! -f resources/js/routes.json ]; then
    echo "Creating resources/js/routes.json"
    echo "{}" > resources/js/routes.json
fi
SCRIPT,
                Artisan::inDeployScript('trail:generate'),
            ],
        );
    }
}
