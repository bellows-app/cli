<?php

namespace Bellows\Commands;

use Bellows\Config;
use Bellows\Data\CreateSiteParams;
use Bellows\Data\ForgeServer;
use Bellows\Data\InstallRepoParams;
use Bellows\Data\PhpVersion;
use Bellows\Data\ProjectConfig;
use Bellows\Data\Repository;
use Bellows\Facades\Project;
use LaravelZero\Framework\Commands\Command;

class PluginTester extends Command
{
    protected $signature = 'plugin:test {plugin} {--demo} {--project.isolatedUser=bellows_tester} {--project.repositoryUrl=bellows/tester} {--project.repositoryBranch=main} {--project.phpVersion=8.1} {--project.phpBinary=php81} {--project.projectDirectory=tests/stubs/plugins/default} {--project.domain=bellowstester.com} {--project.appName=Bellows Tester} {--project.secureSite=true}';

    protected $description = 'Test plugins in isolation';

    public function handle()
    {
        Project::setDir($this->option('project.projectDirectory'));
        Project::setConfig(new ProjectConfig(
            isolatedUser: $this->option('project.isolatedUser'),
            repository: new Repository(
                $this->option('project.repositoryUrl'),
                $this->option('project.repositoryBranch')
            ),
            phpVersion: new PhpVersion(
                $this->option('project.phpVersion'),
                $this->option('project.phpBinary'),
                $this->option('project.phpVersion')
            ),
            directory: $this->option('project.projectDirectory'),
            domain: $this->option('project.domain'),
            appName: $this->option('project.appName'),
            secureSite: $this->option('project.secureSite'),
        ));

        app()->bind(ForgeServer::class, function () {
            return ForgeServer::from([
                'id'         => 123,
                'name'       => 'test-server',
                'type'       => 'php',
                'ip_address' => '123.123.123.123',
            ]);
        });

        app()->bind(
            Config::class,
            fn () => new Config(base_path('tests/stubs/config')),
        );

        $deployScript = <<<'DEPLOY'
cd /home/link_leap/linkleapapp.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
DEPLOY;

        if ($this->option('demo')) {
            $this->newLine(30);
        }

        $this->info("Configuring <comment>{$this->argument('plugin')}</comment> plugin...");

        $plugin = app('\\Bellows\\Plugins\\' . $this->argument('plugin'));

        $plugin->launch();

        $this->newLine(2);

        if (!$this->option('demo')) {
            dd([
                'createSiteParams'     => $plugin->createSiteParams(
                    new CreateSiteParams(
                        domain: $this->option('project.domain'),
                        projectType: 'php',
                        directory: $this->option('project.projectDirectory'),
                        isolated: true,
                        username: $this->option('project.isolatedUser'),
                        phpVersion: $this->option('project.phpVersion'),
                    ),
                ),
                'installRepoParams'    => $plugin->installRepoParams(
                    new InstallRepoParams(
                        'github',
                        $this->option('project.repositoryUrl'),
                        $this->option('project.repositoryBranch'),
                        composer: true,
                    ),
                ),
                'environmentVariables' => $plugin->environmentVariables(),
                'updateDeployScript'   => $plugin->updateDeployScript($deployScript),
                'workers'              => $plugin->workers(),
                'jobs'                 => $plugin->jobs(),
                'daemons'              => $plugin->daemons(),
                // 'wrapUp' => $plugin->wrapUp(),
            ]);
        }
    }
}
