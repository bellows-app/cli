<?php

namespace App\DeployMate;

trait ReadsFromConfig
{
    public function askForConfig(string $key, string $question, ?string $default = null): string
    {
        $choices = array_keys(config("forge.{$key}"));

        if (count($choices) === 1) {
            return $choices[0];
        }

        return $this->choice(
            $question,
            $choices,
            $default,
        );
    }
}
