<?php

namespace Bellows\Commands;

use Bellows\ServerProviders\Forge\Site;
use Bellows\ServerProviders\ServerProviderInterface;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class DeleteSite extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'site:delete';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(ServerProviderInterface $serverProvider)
    {
        $serverProvider->setCredentials();

        $server = $serverProvider->getServer();

        $allSites = $server->getSites();

        $sites = $this->choice(
            question: 'Which sites do you want to delete?',
            choices: $allSites->map(fn ($s) => $s->name)->toArray(),
            multiple: true,
        );

        $toDelete = collect($sites)->map(
            fn ($s) => $allSites->first(fn ($site) => $site->name === $s)
        );

        $this->warn('The following sites will be deleted:');

        $this->newLine();

        $toDelete->each(fn ($s) => $this->warn($s->name));

        if (!$this->confirm('Are you sure you want to delete these sites?', true)) {
            return;
        }

        $this->withProgressBar($toDelete, function ($s) use ($server) {
            $site = new Site($s, $server->serverData());

            $site->delete();
        });
    }
}
