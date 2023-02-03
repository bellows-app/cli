<?php

namespace App\DeployMate;

class Artisan
{
    public function __construct(
        protected ProjectConfig $config,
    ) {
    }

    public function runInProject(string $command): void
    {
        exec("php {$this->config->projectDirectory}/artisan {$command}");
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
        return "{$this->config->phpBinary} /home/{$this->config->isolatedUser}/{$this->config->domain}artisan " . trim($command);
    }
}
