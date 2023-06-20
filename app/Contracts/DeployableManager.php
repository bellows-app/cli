<?php

namespace Bellows\Contracts;

interface DeployableManager
{
    public function daemons(): array;

    public function environmentVariables(array $initialValue = []): array;

    public function jobs(): array;

    public function securityRules(): array;

    public function workers(): array;

    public function updateDeployScript(): string;

    public function wrapUp(): void;
}
