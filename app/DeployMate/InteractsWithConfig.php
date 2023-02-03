<?php

namespace App\DeployMate;

use Illuminate\Support\Arr;

trait InteractsWithConfig
{
    public function askForToken(
        ?string $question = null,
        ?string $default = null,
        ?NewTokenPrompt $newTokenPrompt = null,
    ): string {
        if (!$this->getConfig()) {
            $this->info('No config found for ' . $this->getName() . '.');

            $token = $this->addNewConfig($newTokenPrompt);

            return $token;
        }

        $choices = collect(array_keys($this->getConfig()));

        $addNewAccountText = 'Add new account';

        $choices->push($addNewAccountText);

        $result = $this->choice(
            $question ?: 'Select account',
            $choices->toArray(),
            $default ?? array_key_first($choices->toArray()),
        );

        if ($result === $addNewAccountText) {
            $token = $this->addNewConfig($newTokenPrompt);

            return $token;
        }

        return $this->getFromConfig($result);
    }

    protected function getConfig()
    {
        return $this->config->get($this->getKey());
    }

    protected function getFromConfig(string $key, ?string $default = null)
    {
        return Arr::get($this->getConfig(), $key, $default);
    }


    protected function setConfig(string $key, $value)
    {
        $this->config->set($this->getKey() . '.' . $key, $value);
    }

    protected function addNewConfig(?NewTokenPrompt $newTokenPrompt = null): string
    {
        if ($newTokenPrompt) {
            if ($newTokenPrompt->helpText) {
                $this->info($newTokenPrompt->helpText);
                $this->info($newTokenPrompt->url);
            } else {
                $this->info('You can get your token from ' . $newTokenPrompt->url);
            }
        }

        $token = $this->secret('Token');

        $accountName = $this->ask('Name (for your reference)', $this->getDefaultNewAccountName($token));

        $this->setConfig($accountName, $token);

        return $token;
    }

    protected function getKey()
    {
        return 'plugins.' . get_class($this);
    }
}
