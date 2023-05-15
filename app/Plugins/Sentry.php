<?php

namespace Bellows\Plugins;

use Bellows\Data\AddApiCredentialsPrompt;
use Bellows\Facades\Console;
use Bellows\Facades\Project;
use Bellows\Http;
use Bellows\Plugin;
use Illuminate\Http\Client\PendingRequest;

abstract class Sentry extends Plugin
{
    protected array $organization;

    protected ?string $sentryClientKey;

    protected ?float $tracesSampleRate = null;

    public function __construct(
        protected Http $http,
        protected Project $project,
    ) {
    }

    public function setupClient(): void
    {
        // TODO: List Scopes necessary for this plugin
        $this->http->createJsonClient(
            'https://sentry.io/api/',
            fn (PendingRequest $request, array $credentials) => $request->withToken($credentials['token']),
            new AddApiCredentialsPrompt(
                url: 'https://sentry.io/settings/account/api/auth-tokens/',
                credentials: ['token'],
                displayName: 'Sentry',
            ),
            fn (PendingRequest $request) => $request->get('0/projects/', ['per_page' => 1]),
        );

        $this->organization = $this->http->client()->get('0/organizations/')->json()[0];
    }

    abstract protected function getProjectType(): string;

    protected function createProject(string $type): array
    {
        $teams = collect($this->http->client()->get("0/organizations/{$this->organization['slug']}/teams/")->json());

        $team = Console::choiceFromCollection(
            'Select a team',
            $teams->sortBy('name'),
            'name',
            $teams->count() === 1 ? $teams->first()['name'] : null,
        );

        $name = Console::ask('Project name', Project::config()->appName);

        $project = $this->http->client()->post("0/teams/{$this->organization['slug']}/{$team['slug']}/projects/", [
            'name'     => $name,
            'platform' => $type,
        ])->json();

        return $project;
    }

    protected function getProject()
    {
        // TODO: Instead of asking if they want to create a project,
        // fetch their projects up front and see if there's a project with
        // a matching name
        $projectType = $this->getProjectType();

        if (Console::confirm('Create Sentry project?', true)) {
            return $this->createProject($projectType);
        }

        return $this->selectFromExistingProjects($projectType);
    }

    protected function setupSentry()
    {
        $this->setupClient();
        $this->setClientKey();
        $this->setTracesSampleRate();
    }

    protected function setTracesSampleRate(): void
    {
        $projectType = $this->getProjectType();
        $language = collect(explode('-', $projectType))->first();

        Console::info('To enable performance monitoring, set a number greater than 0.0 (max 1.0).');
        Console::info('Leave blank to disable.');
        Console::newLine();
        Console::info("More info: https://docs.sentry.io/platforms/{$language}/performance/");

        try {
            $this->tracesSampleRate = Console::ask('Traces Sample Rate');
        } catch (\Throwable $e) {
            Console::error('Invalid value!');
            Console::newLine();

            $this->setTracesSampleRate();

            return;
        }

        if ($this->tracesSampleRate === null) {
            return;
        }

        if (!is_numeric($this->tracesSampleRate) || $this->tracesSampleRate < 0 || $this->tracesSampleRate > 1) {
            $this->setTracesSampleRate();
        }
    }

    protected function setClientKey()
    {
        $project = $this->getProject();

        $keys = collect($this->http->client()->get("0/projects/{$this->organization['slug']}/{$project['slug']}/keys/")->json());

        $key = Console::choiceFromCollection(
            'Select a client key',
            $keys->sortBy('name'),
            'name',
        );

        $this->sentryClientKey = $key['dsn']['public'];
    }

    protected function selectFromExistingProjects(string $type): array
    {
        $result = $this->http->client()->get('0/projects/', [
            'per_page' => 100,
        ])->json();

        $result = collect($result)->filter(fn ($project) => $project['platform'] === $type)->values();

        if ($result->count() === 0) {
            Console::error('No existing projects found!');

            if (Console::confirm('Create new project?', true)) {
                $project = $this->createProject($type);

                return $project;
            }

            Console::error('No project selected!');
            exit;
        }

        return Console::choiceFromCollection(
            'Select a Sentry project',
            $result->sortBy('name'),
            'name',
            Project::config()->appName,
        );
    }
}
