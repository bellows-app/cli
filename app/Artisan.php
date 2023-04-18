<?php

namespace Bellows;

class Artisan
{
    public function __construct(
        protected Project $project,
    ) {
    }

    public function inDeployScript(string $command): string
    {
        return '$FORGE_PHP artisan ' . trim($command);
    }

    public function forDaemon(string $command): string
    {
        return "{$this->project->config->phpVersion->binary} artisan " . trim($command);
    }

    public function forJob(string $command): string
    {
        return collect([
            $this->project->config->phpVersion->binary,
            "/home/{$this->project->config->isolatedUser}/{$this->project->config->domain}/artisan",
            trim($command),
        ])->join(' ');
    }
}
