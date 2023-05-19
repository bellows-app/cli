<?php

namespace Bellows\Plugins\Contracts;

interface Deployable
{
    public function deploy(): bool;

    public function canDeploy(): bool;
}
