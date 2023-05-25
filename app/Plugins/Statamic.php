<?php

namespace Bellows\Plugins;

use Bellows\DeployScript;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\PackageManagers\Composer;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Plugins\Helpers\CanBeInstalled;
use Bellows\Util\ConfigHelper;

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

    public function installWrapUp(): void
    {
        Composer::addScript('post-autoload-dump', '@php artisan statamic:install --ansi');
        Composer::allowPlugin('pixelfear/composer-dist-plugin');
        Composer::require('statamic/cms', false, '--with-dependencies');

        (new ConfigHelper)->update('statamic.users.repository', 'file');

        if (!Console::confirm('Create Statamic user?', true)) {
            return;
        }

        $email = Console::ask('Email');
        $name = Console::ask('Name');
        $password = Console::secret('Password');

        Project::writeFile("users/{$email}.yaml", <<<YAML
        name: {$name}
        super: true
        password: {$password}
        YAML);
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
        // TODO: We could do this for them?
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
