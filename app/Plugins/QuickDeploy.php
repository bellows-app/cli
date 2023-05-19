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

    public function deploy(): void
    {
        // Nothing to do here
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
