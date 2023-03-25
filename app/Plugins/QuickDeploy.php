<?php

namespace Bellows\Plugins;

use Bellows\Plugin;

class QuickDeploy extends Plugin
{
    public function enabled(): bool
    {
        return $this->console->confirm('Enable quick deploy?', true);
    }

    public function wrapUp(): void
    {
        $this->site->enableQuickDeploy();
    }
}
