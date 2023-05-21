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

    public function install(): void
    {
        $username = $this->getUsername();

        if ($username) {
            $repo = $username . '/' . Str::slug(Project::config()->appName);
        }

        // TODO: What if they don't want to create a repo? I guess they can just leave it blank.
        $githubRepo = Console::ask('GitHub repo', $repo ?? null);

        if ($githubRepo) {
            Project::config()->repository = new Repository($githubRepo, 'main');
        }

        // TODO: wrap up -> git add . commit push to remote

        // $this->step('Creating GitHub repo...');

        // exec("gh repo create {$this->githubRepo} --private");
        // exec('git init');
        // exec('git add .');
        // exec('git commit -m "kickoff, just the framework"');
        // exec('git branch -M main');
        // exec("git remote add origin git@github.com:{$this->githubRepo}.git");
        // exec('git push -u origin main');
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
