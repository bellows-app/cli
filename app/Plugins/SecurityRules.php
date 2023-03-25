<?php

namespace Bellows\Plugins;

use Bellows\Data\SecurityRule;
use Bellows\Plugin;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SecurityRules extends Plugin
{
    /** @var \Illuminate\Support\Collection<\Bellows\Data\SecurityRule> */
    protected Collection $securityRules;

    public function enabled(): bool
    {
        return $this->console->confirm(
            'Do you want to add any security rules? (basic auth)',
            Str::contains($this->projectConfig->domain, ['dev.', 'staging.'])
        );
    }

    public function setup(): void
    {
        $this->securityRules = collect();

        do {
            $groupName = $this->console->ask(
                'Security rule group name',
                'Restricted Access'
            );

            $path = $this->console->ask(
                'Path (leave blank to password protect all routes within your site, any valid Nginx location path)'
            );

            $credentials = collect();

            do {
                $username = $this->console->ask('Username');
                $password = $this->console->secret('Password');

                $credentials->push([
                    'username' => $username,
                    'password' => $password,
                ]);
            } while ($this->console->confirm('Add another user?'));

            $this->securityRules->push(
                SecurityRule::from([
                    'name'        => $groupName,
                    'path'        => $path,
                    'credentials' => $credentials,
                ])
            );
        } while ($this->console->confirm('Add another security rule group?'));
    }

    public function wrapUp(): void
    {
        if ($this->securityRules->isEmpty()) {
            return;
        }

        $this->securityRules->each(
            fn ($rule) => $this->site->addSecurityRule($rule),
        );
    }
}
