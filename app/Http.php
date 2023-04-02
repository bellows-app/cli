<?php

namespace Bellows;

use Bellows\Data\AddApiCredentialsPrompt;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http as HttpFacade;

class Http
{
    use InteractsWithConfig;

    protected $clients = [];

    public function __construct(
        protected Config $config,
        protected Console $console,
    ) {
    }

    public function createClient(
        string $baseUrl,
        callable $factory,
        AddApiCredentialsPrompt $addCredentialsPrompt = null,
        callable $test,
    ): void {
        $name = 'default';

        if (array_key_exists($name, $this->clients)) {
            throw new Exception("Client {$name} already exists");
        }

        $host = parse_url($baseUrl, PHP_URL_HOST);

        $credentials = $this->getApiCredentials($host, $addCredentialsPrompt);

        try {
            $test($factory(HttpFacade::baseUrl($baseUrl), $credentials)->throw());
        } catch (RequestException $e) {
            $this->console->warn('Could not connect with the provided credentials:');

            $data = json_decode((string) $e->response->body(), true);
            $message = Arr::get($data ?? [], 'message', (string) $e->response->body());

            $this->console->warn($e->response->status() . ': ' . $message);

            $this->console->warn('Please select a different account or add a new one.');

            $this->createClient($baseUrl, $factory, $addCredentialsPrompt, $test);

            return;
        } catch (Exception $e) {
            // Something else happened, just give a generic error message.
            $this->console->warn('Could not connect with the provided credentials!');
            $this->console->warn('Please select a different account or add a new one.');

            $this->createClient($baseUrl, $factory, $addCredentialsPrompt, $test);

            return;
        }

        $this->clients[$name] = fn () => $factory(
            HttpFacade::baseUrl($baseUrl),
            $credentials,
        );
    }

    // TODO: This is starting to feel more like a builder object that params.
    public function createJsonClient(
        string $baseUrl,
        callable $factory,
        AddApiCredentialsPrompt $addCredentialsPrompt = null,
        callable $test,
    ): void {
        $this->createClient(
            $baseUrl,
            fn ($request, $credentials) => $factory($request, $credentials)->acceptJson()->asJson(),
            $addCredentialsPrompt,
            $test,
        );
    }

    public function extendClient(
        string $baseUrl,
        string $name,
        string $toExtend = 'default',
    ): void {
        if (!array_key_exists($toExtend, $this->clients)) {
            throw new Exception("Client {$toExtend} does not exist to extend");
        }

        if (array_key_exists($name, $this->clients)) {
            throw new Exception("Client {$name} already exists");
        }

        $this->clients[$name] = fn () => $this->clients[$toExtend]()->baseUrl($baseUrl);
    }

    public function client(string $name = 'default'): PendingRequest
    {
        if (!array_key_exists($name, $this->clients)) {
            throw new Exception("Client {$name} does not exist");
        }

        return $this->clients[$name]();
    }

    protected function getApiCredentials(string $host, AddApiCredentialsPrompt $addCredentialsPrompt): array
    {
        if (!$this->getAllConfigsForApi($host)) {
            $this->console->miniTask('No credentials found for', $addCredentialsPrompt->displayName, false);
            $this->console->newLine();

            return $this->addNewCredentials($host, $addCredentialsPrompt);
        }

        $choices = collect(array_keys($this->getAllConfigsForApi($host)));

        $addNewAccountText = 'Add new account';

        $choices->push($addNewAccountText);

        $result = $this->console->choice(
            'Select account',
            $choices->toArray(),
            count($choices) === 2 ? array_key_first($choices->toArray()) : null,
        );

        if ($result === $addNewAccountText) {
            return $this->addNewCredentials($host, $addCredentialsPrompt);
        }

        if ($value = $this->getApiConfigValue($host, $result)) {
            return $value;
        }

        throw new Exception("Could not find credentials for {$host} {$result}");
    }

    protected function addNewCredentials(string $host, ?AddApiCredentialsPrompt $addCredentialsPrompt = null): array
    {
        // TODO: Can we make this whole method less repetitive?

        if (!$addCredentialsPrompt) {
            // We'll just assume they need a personal access token and proceed accordingly
            $token = $this->console->secret('Token');
            $accountName = $this->console->ask('Account Name (for your own reference)');
            // TODO: Re-implement this, it was useful I think (maybe it's not common enough)
            // $accountName = $this->ask('Account Name (for your own reference)', $this->getDefaultNewAccountName($token));

            $value = ['token' => $token];

            $this->setApiConfigValue($host, $accountName, $value);

            return $value;
        }

        $this->console->info($addCredentialsPrompt->helpText ?? 'Retrieve your token here:');
        $this->console->comment($addCredentialsPrompt->url);

        $value = collect($addCredentialsPrompt->credentials)->mapWithKeys(
            fn ($value) => [$value => $this->console->secret(ucwords($value))]
        )->toArray();

        do {
            $accountName = $this->console->ask('Account Name (for your own reference)');
        } while (
            $this->getApiConfigValue($host, $accountName)
            && !$this->console->confirm('Overwrite existing account?')
        );

        $this->setApiConfigValue($host, $accountName, $value);

        return $value;
    }
}
