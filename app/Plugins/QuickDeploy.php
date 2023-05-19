<?php

namespace Bellows\Plugins;

use Bellows\Facades\Console;
use Bellows\Plugin;

class QuickDeploy extends Plugin
{
    public function enabled(): bool
    {
        return Console::confirm('Enable quick deploy?', true);
    }

    public function canDeploy(): bool
    {
        // TODO: Check if quick deploy is enabled currently?
        return false;
    }

    public function wrapUp(): void
    {
        $this->site->enableQuickDeploy();
    }
}
