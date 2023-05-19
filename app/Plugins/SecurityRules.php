<?php

namespace Bellows\Plugins;

use Bellows\Data\SecurityRule;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Deployable;
use Bellows\Plugins\Contracts\Launchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SecurityRules extends Plugin implements Launchable, Deployable
{
    /** @var \Illuminate\Support\Collection<\Bellows\Data\SecurityRule> */
    protected Collection $securityRules;

    public function enabled(): bool
    {
        return Console::confirm(
            'Do you want to add any security rules? (basic auth)',
            Str::contains(Project::config()->domain, ['dev.', 'staging.'])
        );
    }

    public function launch(): void
    {
        $this->securityRules = collect();

        do {
            $groupName = Console::ask(
                'Security rule group name',
                'Restricted Access'
            );

            $path = Console::ask(
                'Path (leave blank to password protect all routes within your site, any valid Nginx location path)'
            );

            $credentials = collect();

            do {
                $username = Console::ask('Username');
                $password = Console::secret('Password');

                $credentials->push([
                    'username' => $username,
                    'password' => $password,
                ]);
            } while (Console::confirm('Add another user?'));

            $this->securityRules->push(
                SecurityRule::from([
                    'name'        => $groupName,
                    'path'        => $path,
                    'credentials' => $credentials,
                ])
            );
        } while (Console::confirm('Add another security rule group?'));
    }

    public function deploy(): void
    {
        // Nothing to do here
    }

    public function canDeploy(): bool
    {
        return true;
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
