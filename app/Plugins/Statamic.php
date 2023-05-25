<?php

namespace Bellows\Plugins;

use Bellows\DeployScript;
use Bellows\Facades\Console;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Plugins\Helpers\CanBeInstalled;

class Statamic extends Plugin implements Launchable, Deployable, Installable
{
    use CanBeInstalled;

    protected bool $gitEnabled = false;

    protected bool $gitAutoCommit = false;

    protected bool $gitAutoPush = false;

    protected ?string $gitEmail = null;

    protected ?string $gitUsername = null;

    protected array $requiredComposerPackages = [
        'statamic/cms',
    ];

    public function __construct()
    {
    }

    public function launch(): void
    {
        $this->gitEnabled = Console::confirm('Enable git?', true);

        if (!$this->gitEnabled) {
            return;
        }

        $this->gitEmail = Console::ask('Git user email (leave blank to use default)');
        $this->gitUsername = Console::ask('Git user name (leave blank to use default)');
        $this->gitAutoCommit = Console::confirm('Automatically commit changes?', true);

        if (!$this->gitAutoCommit) {
            return;
        }

        $this->gitAutoPush = Console::confirm('Automatically push changes?', true);

        Console::info('To prevent circular deployments, customize your commit message as described here:');
        Console::comment('https://statamic.dev/git-automation#customizing-commits');
    }

    public function deploy(): bool
    {
        return true;
    }

    public function canDeploy(): bool
    {
        return true;
    }

    public function environmentVariables(): array
    {
        $vars = [];

        if ($this->gitEnabled) {
            $vars['STATAMIC_GIT_ENABLED'] = true;
        }

        if ($this->gitUsername) {
            $vars['STATAMIC_GIT_USER_NAME'] = $this->gitUsername;
        }

        if ($this->gitEmail) {
            $vars['STATAMIC_GIT_USER_EMAIL'] = $this->gitEmail;
        }

        if ($this->gitAutoCommit) {
            $vars['STATAMIC_GIT_AUTOMATIC'] = true;
        }

        if ($this->gitAutoPush) {
            $vars['STATAMIC_GIT_PUSH'] = true;
        }

        return $vars;
    }

    public function updateDeployScript(string $deployScript): string
    {
        $script = DeployScript::addAfterGitPull($deployScript, 'php please cache:clear');

        if ($this->gitAutoCommit) {
            $botCheck = <<<'SCRIPT'
            if [[ $FORGE_DEPLOY_MESSAGE =~ "[BOT]" ]]; then
                echo "AUTO-COMMITTED ON PRODUCTION. NOTHING TO DEPLOY."
                exit 0
            fi
            SCRIPT;

            $script = $botCheck . PHP_EOL . PHP_EOL . $script;
        }

        return $script;
    }
}
