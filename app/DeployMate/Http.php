<?php

namespace App\DeployMate;

use App\DeployMate\Data\AddApiCredentialsPrompt;
use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http as HttpFacade;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Http
{
    use InteractsWithIO;
    use InteractsWithConfig;

    protected $clients = [];

    public function __construct(
        protected Config $config,
        InputInterface $input,
        OutputInterface $output,
    ) {
        $this->input = $input;
        $this->output = $output;
    }

    public function createClient(
        string $baseUrl,
        callable $factory,
        AddApiCredentialsPrompt $addCredentialsPrompt = null,
        string $name = 'default',
    ): void {
        if (array_key_exists($name, $this->clients)) {
            throw new \Exception("Client {$name} already exists");
        }

        $host = parse_url($baseUrl, PHP_URL_HOST);

        $credentials = $this->getApiCredentials($host, $addCredentialsPrompt);

        $this->clients[$name] = fn () => $factory(
            HttpFacade::baseUrl($baseUrl),
            $credentials,
        );
    }

    public function createJsonClient(
        string $baseUrl,
        callable $factory,
        AddApiCredentialsPrompt $addCredentialsPrompt = null,
        string $name = 'default',
    ): void {
        $this->createClient(
            $baseUrl,
            fn ($request, $credentials) => $factory($request, $credentials)->acceptJson()->asJson(),
            $addCredentialsPrompt,
            $name,
        );
    }

    public function extendClient(
        string $baseUrl,
        string $name,
        string $toExtend = 'default',
    ): void {
        if (!array_key_exists($toExtend, $this->clients)) {
            throw new \Exception("Client {$toExtend} does not exist to extend");
        }

        if (array_key_exists($name, $this->clients)) {
            throw new \Exception("Client {$name} already exists");
        }

        $this->clients[$name] = fn () => $this->clients[$toExtend]()->baseUrl($baseUrl);
    }

    protected function getApiCredentials(string $host, AddApiCredentialsPrompt $addCredentialsPrompt): array
    {
        if (!$this->getApiConfig($host)) {
            $this->info("No config found for {$host}");
            return $this->addNewCredentials($host, $addCredentialsPrompt);
        }

        $choices = collect(array_keys($this->getApiConfig($host)));

        $addNewAccountText = 'Add new account';

        $choices->push($addNewAccountText);

        $result = $this->choice(
            'Select account',
            $choices->toArray(),
            $default ?? array_key_first($choices->toArray()),
        );

        if ($result === $addNewAccountText) {
            return $this->addNewCredentials($host, $addCredentialsPrompt);
        }

        if ($value = $this->getApiConfigValue($host, $result)) {
            return $value;
        }

        throw new \Exception("Could not find credentials for {$host} {$result}");
    }

    protected function addNewCredentials(string $host, ?AddApiCredentialsPrompt $addCredentialsPrompt = null): array
    {
        // TODO: Can we make this whole method less repetitive?

        if (!$addCredentialsPrompt) {
            // We'll just assume they need a personal access token and proceed accordingly
            $token = $this->secret('Token');
            $accountName = $this->ask('Name (for your reference)');
            // TODO: Re-implement this, it was useful I think (maybe it's not common enough)
            // $accountName = $this->ask('Name (for your reference)', $this->getDefaultNewAccountName($token));

            $value = compact('token');

            $this->setApiConfig($host, $accountName, $value);

            return $value;
        }

        if ($addCredentialsPrompt->helpText) {
            $this->info($addCredentialsPrompt->helpText);
            $this->info($addCredentialsPrompt->url);
        } else {
            $this->info('You can get your token from ' . $addCredentialsPrompt->url);
        }

        $value = collect($addCredentialsPrompt->credentials)->mapWithKeys(
            fn ($value) => [$value => $this->secret(ucwords($value))]
        )->toArray();

        $accountName = $this->ask('Name (for your reference)');

        $this->setApiConfig($host, $accountName, $value);

        return $value;
    }

    public function client(string $name = 'default'): PendingRequest
    {
        if (!array_key_exists($name, $this->clients)) {
            throw new \Exception("Client {$name} does not exist");
        }

        return $this->clients[$name]();
    }
}
