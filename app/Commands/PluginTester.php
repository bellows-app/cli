<?php

namespace Bellows\Commands;

use Bellows\Config;
use Bellows\Data\ForgeServer;
use Bellows\Data\ProjectConfig;
use LaravelZero\Framework\Commands\Command;

class PluginTester extends Command
{
    protected $signature = 'plugin:test {plugin} {--project.isolatedUser=tester} {--project.repositoryUrl=bellows/tester} {--project.repositoryBranch=main} {--project.phpVersion=8.1} {--project.phpBinary=php81} {--project.projectDirectory=tests/stubs/plugins/default} {--project.domain=bellowstester.com} {--project.appName=Bellows Tester} {--project.secureSite=true}';

    protected $description = 'Test plugins in isolation';

    public function handle()
    {
        app()->bind(ProjectConfig::class, function () {
            return ProjectConfig::from([
                'isolatedUser'     => $this->option('project.isolatedUser'),
                'repositoryUrl'    => $this->option('project.repositoryUrl'),
                'repositoryBranch' => $this->option('project.repositoryBranch'),
                'phpVersion'       => $this->option('project.phpVersion'),
                'phpBinary'        => $this->option('project.phpBinary'),
                'projectDirectory' => $this->option('project.projectDirectory'),
                'domain'           => $this->option('project.domain'),
                'appName'          => $this->option('project.appName'),
                'secureSite'       => $this->option('project.secureSite'),
            ]);
        });

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

        $plugin = app('\\Bellows\\Plugins\\' . $this->argument('plugin'));

        $plugin->setup();

        dd([
            'createSiteParams'     => $plugin->createSiteParams([]),
            'installRepoParams'    => $plugin->installRepoParams([]),
            'environmentVariables' => $plugin->environmentVariables(),
            'updateDeployScript'   => $plugin->updateDeployScript($deployScript),
            'workers'              => $plugin->workers(),
            'jobs'                 => $plugin->jobs(),
            'daemons'              => $plugin->daemons(),
            // 'wrapUp' => $plugin->wrapUp(),
        ]);
    }
}