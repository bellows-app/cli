<?php

namespace Bellows\Plugins\Contracts;

interface Deployable
{
    public function deploy(): void;

    public function canDeploy(): bool;
}
