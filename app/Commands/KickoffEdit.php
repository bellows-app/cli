<?php

namespace Bellows\Commands;

use Bellows\Config\BellowsConfig;
use Bellows\Config\KickoffConfig;
use Bellows\Util\Editor;
use LaravelZero\Framework\Commands\Command;

class KickoffEdit extends Command
{
    protected $signature = 'kickoff:edit';

    protected $description = 'Edit a kickoff config';

    public function handle(Editor $editor)
    {
        $configs = collect(glob(BellowsConfig::getInstance()->kickoffConfigPath('*.json')))
            ->map(fn ($path) => new KickoffConfig($path))
            ->sortBy(fn (KickoffConfig $config) => $config->displayName());

        if ($configs->count() === 0) {
            $this->newLine();
            $this->warn('No kickoff configs found!');

            return Command::FAILURE;
        }

        $editor->open($this->getConfigFile($configs)->path());
    }

    protected function getConfigFile($configs): KickoffConfig
    {
        if ($configs->count() === 1) {
            return $configs->first();
        }

        $name = $this->choice(
            'Select a config to edit',
            $configs->map(fn (KickoffConfig $c) => $c->displayName())->toArray(),
        );

        return $configs->first(fn (KickoffConfig $c) => $c->displayName() === $name);
    }
}
