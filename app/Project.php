<?php

namespace Bellows;

use Bellows\Data\ProjectConfig;

class Project
{
    public Env $env;

    public function __construct(public ProjectConfig $config)
    {
        $this->env = Env::fromDir($config->directory);
    }
}
