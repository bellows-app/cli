<?php

namespace Bellows\Plugins;

use Bellows\Facades\Console;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;
use Bellows\Plugins\Helpers\CanBeLaunched;

class QuickDeploy extends Plugin implements Launchable, Deployable
{
    use CanBeLaunched;

    public function enabled(): bool
    {
        return Console::confirm('Enable quick deploy?', true);
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
