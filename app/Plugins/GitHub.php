<?php

namespace Bellows\Plugins;

use Bellows\Data\Repository;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Plugin;
use Bellows\Plugins\Contracts\Installable;
use Bellows\Plugins\Helpers\CanBeInstalled;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class GitHub extends Plugin implements Installable
{
    use CanBeInstalled;

    public function getName(): string
    {
        return 'GitHub';
    }

    public function installWrapUp(): void
    {
        if (!Console::confirm('Initialize a GitHub repo?', true)) {
            return;
        }

        $username = $this->getUsername();

        if ($username) {
            $repo = $username . '/' . Str::slug(Project::config()->appName);
        }

        $githubRepo = Console::ask('GitHub repo name', $repo ?? null);

        if ($githubRepo) {
            Project::config()->repository = new Repository($githubRepo, 'main');
        }

        $ghInstalled = trim(Process::run('which gh')->output()) !== '';

        if (!$ghInstalled) {
            Console::warn('GitHub CLI is not installed. Cannot create remote repository.');

            return;
        }

        $repoVisiblitity = Console::choice(
            'Repo visibility',
            ['public', 'private'],
            'private'
        );

        Process::runWithOutput("gh repo create {$githubRepo} --{$repoVisiblitity}");
        Process::runWithOutput('git init');
        Process::runWithOutput('git add .');
        Process::runWithOutput('git commit -m "kickoff"');
        Process::runWithOutput('git branch -M main');
        Process::runWithOutput("git remote add origin git@github.com:{$githubRepo}.git");
        Process::runWithOutput('git push -u origin main');
    }

    protected function getUsername(): string
    {
        if ($gitUserName = Process::run('git config --global user.username')->output()) {
            return $gitUserName;
        }

        $ghCliConfigPath = env('HOME') . '/.config/gh/hosts.yml';

        if (file_exists($ghCliConfigPath)) {
            $ghInfo = Yaml::parseFile($ghCliConfigPath);

            if ($username = $ghInfo['github.com']['user'] ?? null) {
                return $username;
            }
        }

        return null;
    }
}
