<?php

namespace Bellows\Plugins;

use Bellows\Facades\Console;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;

class QuickDeploy extends Plugin implements Launchable, Deployable
{
    public function enabled(): bool
    {
        return Console::confirm('Enable quick deploy?', true);
    }

    public function launch(): void
    {
        // Nothing to do here
    }

    public function deploy(): bool
    {
        return true;
    }

    public function canDeploy(): bool
    {
        return !$this->site->quick_deploy;
    }

    public function wrapUp(): void
    {
        $this->site->enableQuickDeploy();
    }
}
