<?php

namespace Bellows\Commands;

use Bellows\Plugins\Manager;
use LaravelZero\Framework\Commands\Command;

class PluginUpdate extends Command
{
    protected $signature = 'plugin:update';

    protected $description = 'Update Bellows plugins';

    public function handle(Manager $pluginManager)
    {
        $this->newLine();

        $this->info('Updating plugins...');

        $pluginManager->updateAll();

        $this->newLine();

        $this->info('Plugins updated successfully!');
    }
}
