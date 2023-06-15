<?php

namespace Bellows\Commands;

use Bellows\Config\BellowsConfig;
use Bellows\Git\Git;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class PluginInit extends Command
{
    protected $signature = 'plugin:init';

    protected $description = 'Create a new Bellows plugin';

    public function handle()
    {
        $className = $this->askRequired('Plugin class name?');

        $className = collect(preg_split('/(?=[A-Z])/', $className))->filter()->implode(' ');
        $name = Str::slug($className);

        $description = $this->askRequired('Short plugin description?');

        $vendorNamespace = $this->getVendorNamespace();

        $packageName = $this->ask('Package name?', "{$vendorNamespace}/bellows-plugin-{$name}");

        $suggestedPluginNamespace = Str::studly(Git::user() ?: $vendorNamespace);
        $pluginNamespace = $this->ask('Package namespace?', "{$suggestedPluginNamespace}\\BellowsPlugin");

        $abilities = $this->choice(
            'Is this plugin (choose at least one)',
            ['Installable', 'Deployable'],
            null,
            null,
            true
        );

        $interactsWithApi = $this->confirm('Does this plugin interact with an API?');

        $pluginFileContent = $this->getPluginFileContent(
            $className,
            $pluginNamespace,
            $abilities,
            $interactsWithApi,
        );

        $composerJson = $this->getComposerJsonFileContent(
            $packageName,
            $pluginNamespace,
            $description,
        );

        $testFileContent = $this->getTestFileContent(
            $className,
            $pluginNamespace,
        );

        $dir = BellowsConfig::getInstance()->localPluginPath($name);

        File::ensureDirectoryExists($dir);

        if (!File::isEmptyDirectory($dir)) {
            $this->error('Directory is not empty: ' . $dir);

            return;
        }

        File::copyDirectory(base_path('stubs/plugin'), $dir);

        File::put($dir . '/composer.json', $composerJson);
        File::put($dir . '/src/' . $className . 'Plugin.php', $pluginFileContent);
        File::put($dir . '/tests/Feature/' . $className . 'Test.php', $testFileContent);
        File::delete($dir . '/src/Plugin.php.stub');
        File::delete($dir . '/tests/Feature/Test.php.stub');

        if ($interactsWithApi) {
            Process::runWithOutput("cd {$dir} && composer require illuminate/http --no-interaction");
        }

        Process::runWithOutput("cd {$dir} && composer install --no-interaction");

        $this->info('Plugin created successfully!');

        if ($this->confirm('Would you like to open the plugin directory now?', true)) {
            // vendor/laravel-zero/foundation/src/Illuminate/Foundation/Concerns/ResolvesDumpSource.php
            $openIn = $this->choice('Open in', ['Finder', 'Terminal']);

            switch ($openIn) {
                case 'Finder':
                    Process::run("open {$dir}");
                    break;
                case 'Terminal':
                    // TODO: Doesn't actually work. Is this possible?
                    Process::run("cd {$dir}");
                    break;
            }
        }
    }

    protected function getComposerJsonFileContent(
        string $packageName,
        string $pluginNamespace,
        string $description,

    ): string {
        $authorName = $this->askRequired('Your name', Git::user());
        $authorEmail = $this->askRequired('Your email', Git::email());

        $replacements = [
            '{{ packageName }}'        => $packageName,
            '{{ pluginNamespace }}'    => str_replace('\\', '\\\\', $pluginNamespace),
            '{{ packageDescription }}' => str_replace('"', '\\"', $description),
            '{{ authorName }}'         => str_replace('"', '\\"', $authorName),
            '{{ authorEmail }}'        => $authorEmail,
        ];

        $json = File::get(base_path('stubs/plugin/composer.json'));

        $json = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $json
        );

        return $json;
    }

    protected function getTestFileContent(
        string $className,
        string $pluginNamespace,
    ): string {
        $testFileContent = File::get(base_path('stubs/plugin/tests/Feature/Test.php.stub'));

        $testFileContent = str_replace(
            '{{ pluginNamespace }}',
            $pluginNamespace,
            $testFileContent
        );

        $testFileContent = str_replace(
            '{{ pluginName }}',
            $className,
            $testFileContent
        );

        return $testFileContent;
    }

    protected function getPluginFileContent(
        string $className,
        string $pluginNamespace,
        array $abilities,
        bool $interactsWithApi,
    ): string {
        $pluginFileContent = File::get(base_path('stubs/plugin/src/Plugin.php.stub'));

        $defaultImports = collect([
            'Bellows\\PluginSdk\\Plugin',
        ]);

        $defaultImports->push(
            ...collect($abilities)->map(
                fn ($ability) => [
                    "Bellows\\PluginSdk\\Contracts\\{$ability}",
                    match ($ability) {
                        'Installable' => 'Bellows\\PluginSdk\\PluginResults\\CanBeInstalled',
                        'Deployable'  => 'Bellows\\PluginSdk\\PluginResults\\CanBeDeployed',
                    },
                    match ($ability) {
                        'Installable' => 'Bellows\\PluginSdk\\PluginResults\\InstallationResult',
                        'Deployable'  => 'Bellows\\PluginSdk\\PluginResults\\DeployResult',
                    },
                ]
            )->flatten()
        );

        if ($interactsWithApi) {
            $defaultImports->push('Bellows\\PluginSdk\\Contracts\\HttpClient');
            $defaultImports->push('Bellows\\PluginSdk\\Data\\AddApiCredentialsPrompt');
            $defaultImports->push('Illuminate\\Http\\Client\\PendingRequest');
        }

        $imports = $defaultImports->sort()->map(
            fn ($import) => "use {$import};"
        )->implode("\n");

        $implements = collect($abilities)->implode(', ');

        $traits = collect($abilities)->map(
            fn ($ability) => match ($ability) {
                'Installable' => 'CanBeInstalled',
                'Deployable'  => 'CanBeDeployed',
            }
        )->sort()->implode(', ');

        $pluginFileContent = str_replace(
            '{{ pluginNamespace }}',
            $pluginNamespace,
            $pluginFileContent
        );

        $pluginFileContent = str_replace(
            '{{ imports }}',
            $imports,
            $pluginFileContent
        );

        $pluginFileContent = str_replace(
            '{{ pluginName }}',
            $className,
            $pluginFileContent
        );

        $pluginFileContent = str_replace(
            '{{ implements }}',
            $implements,
            $pluginFileContent
        );

        $pluginFileContent = str_replace(
            '{{ traits }}',
            $traits,
            $pluginFileContent
        );

        if ($interactsWithApi) {
            $pluginFileContent = str_replace(
                '{{ constructor }}',
                <<<'PHP'
                    public function __construct(
                        protected HttpClient $http,
                    ) {
                    }
                PHP,
                $pluginFileContent
            );
        } else {
            $pluginFileContent = str_replace(
                PHP_EOL . PHP_EOL . '{{ constructor }}',
                '',
                $pluginFileContent
            );
        }

        if (in_array('Installable', $abilities)) {
            $pluginFileContent = str_replace(
                '{{ installMethod }}',
                <<<'PHP'
                    public function install(): ?InstallationResult
                    {
                {{ httpClientSetupForInstall }}
                        return InstallationResult::create();
                    }
                PHP,
                $pluginFileContent
            );
        } else {
            $pluginFileContent = str_replace(
                PHP_EOL . PHP_EOL . '{{ installMethod }}',
                '',
                $pluginFileContent
            );
        }

        if (in_array('Deployable', $abilities)) {
            $pluginFileContent = str_replace(
                '{{ deployMethod }}',
                <<<'PHP'
                    public function deploy(): ?DeployResult
                    {
                {{ httpClientSetupForDeploy }}
                        return DeployResult::create();
                    }
                PHP,
                $pluginFileContent
            );
        } else {
            $pluginFileContent = str_replace(
                PHP_EOL . PHP_EOL . '{{ deployMethod }}',
                '',
                $pluginFileContent
            );
        }

        $httpClientSetup = <<<'PHP'
                $this->http->createJsonClient(
                    'https://api.example.com',
                    fn (PendingRequest $request, array $credentials) => $request->withToken($credentials['token']),
                    new AddApiCredentialsPrompt(
                        url: 'https://example.com/direct-link/to-credentials',
                        credentials: ['token'],
                        displayName: '{{ pluginName }}',
                    ),
                    fn (PendingRequest $request) => $request->get('me'),
                );
        PHP;

        if (!$interactsWithApi) {
            $pluginFileContent = str_replace(
                [
                    '{{ httpClientSetupForInstall }}' . PHP_EOL,
                    '{{ httpClientSetupForDeploy }}' . PHP_EOL,
                ],
                '',
                $pluginFileContent
            );
        } elseif (str_contains($pluginFileContent, '{{ httpClientSetupForDeploy }}')) {
            $pluginFileContent = str_replace(
                '{{ httpClientSetupForDeploy }}',
                $httpClientSetup . PHP_EOL,
                $pluginFileContent
            );

            $pluginFileContent = str_replace(
                '{{ httpClientSetupForInstall }}' . PHP_EOL,
                '',
                $pluginFileContent
            );
        } elseif (str_contains($pluginFileContent, '{{ httpClientSetupForInstall }}')) {
            $pluginFileContent = str_replace(
                '{{ httpClientSetupForInstall }}',
                $httpClientSetup . PHP_EOL,
                $pluginFileContent
            );
        }

        return $pluginFileContent;
    }

    protected function getVendorNamespace(): ?string
    {
        return collect([
            $_SERVER['COMPOSER_DEFAULT_VENDOR'] ?? null,
            Git::gitHubUser(),
            $_SERVER['USERNAME'] ?? null,
            $_SERVER['USER'] ?? null,
            get_current_user(),
        ])->filter()->first();
    }
}
