<?php

namespace App\Bellows;

use App\Bellows\Data\ProjectConfig;

class Artisan
{
    public function __construct(
        protected ProjectConfig $config,
    ) {
    }

    public function inDeployScript(string $command): string
    {
        return '$FORGE_PHP artisan ' . trim($command);
    }

    public function forDaemon(string $command): string
    {
        return "{$this->config->phpBinary} artisan " . trim($command);
    }

    public function forJob(string $command): string
    {
        return "{$this->config->phpBinary} /home/{$this->config->isolatedUser}/{$this->config->domain}/artisan " . trim($command);
    }
}
