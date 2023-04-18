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

    public function wrapUp(): void
    {
        $this->site->enableQuickDeploy();
    }
}
