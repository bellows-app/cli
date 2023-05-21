<?php

namespace Bellows\Plugins\Contracts;

interface Installable
{
    public function install(): void;

    public function installWrapUp(): void;

    public function composerPackagesToInstall(): array;

    public function npmPackagesToInstall(): array;
}
