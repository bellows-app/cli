<?php

namespace App\Plugins;

use App\Bellows\Plugin;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SecurityRules extends Plugin
{
    public function enabled(): bool
    {
        return $this->console->confirm(
            'Do you want to add any security rules? (basic auth)',
            Str::contains($this->projectConfig->domain, ['dev.', 'staging.'])
        );
    }

    public function wrapUp($server, $site): void
    {
        $this->console->info('Setting up security rules (basic auth)...');

        do {
            $groupName = $this->console->ask('Security rule group name', 'Restricted Access');
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

            Http::forgeServer()->post(
                'security-rules',
                [
                    'name'        => $groupName,
                    'path'        => $path,
                    'credentials' => $credentials->toArray(),
                ]
            );
        } while ($this->console->confirm('Add another security rule group?'));
    }
}
