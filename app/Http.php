<?php

namespace Bellows;

use Bellows\Config\InteractsWithConfig;
use Bellows\Facades\Console;
use Bellows\PluginSdk\Contracts\HttpClient;
use Bellows\PluginSdk\Data\AddApiCredentialsPrompt;
use Bellows\Util\SharedAccount;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http as HttpFacade;
use Illuminate\Support\Str;

class Http implements HttpClient
{
    use InteractsWithConfig;

    protected $clients = [];

    public function __construct(
        protected Config $config,
    ) {
    }

    public function clearClients(): void
    {
        $this->clients = [];
    }

    public function createClient(
        string $baseUrl,
        callable $factory,
        AddApiCredentialsPrompt $addCredentialsPrompt,
        callable $test,
        bool $shared = false,
    ): void {
        $name = 'default';

        if (array_key_exists($name, $this->clients)) {
            throw new Exception("Client {$name} already exists");
        }

        $host = parse_url($baseUrl, PHP_URL_HOST);

        $accounts = SharedAccount::getInstance();

        if ($shared && $accounts->has($host)) {
            $this->clients[$name] = $accounts->get($host);

            return;
        }

        $credentials = $this->getApiCredentials($host, $addCredentialsPrompt);

        try {
            $test($factory(HttpFacade::baseUrl($baseUrl), $credentials)->throw());
        } catch (RequestException $e) {
            Console::warn('Could not connect with the provided credentials:');

            $data = json_decode((string) $e->response->body(), true);
            $message = Arr::get($data ?? [], 'message', (string) $e->response->body());

            Console::warn($e->response->status() . ': ' . $message);

            Console::warn('Please select a different account or add a new one.');

            $this->createClient($baseUrl, $factory, $addCredentialsPrompt, $test);

            return;
        } catch (Exception $e) {
            // Something else happened, just give a generic error message.
            Console::warn('Could not connect with the provided credentials!');
            Console::warn('Please select a different account or add a new one.');

            $this->createClient($baseUrl, $factory, $addCredentialsPrompt, $test);

            return;
        }

        $client = fn () => $factory(
            HttpFacade::baseUrl($baseUrl),
            $credentials,
        );

        if ($shared) {
            $accounts->set($host, $client);
        }

        $this->clients[$name] = $client;
    }

    // TODO: This is starting to feel more like a builder object than params.
    public function createJsonClient(
        string $baseUrl,
        callable $factory,
        AddApiCredentialsPrompt $addCredentialsPrompt,
        callable $test,
        bool $shared = false,
    ): void {
        $this->createClient(
            $baseUrl,
            fn ($request, $credentials) => $factory($request, $credentials)->acceptJson()->asJson(),
            $addCredentialsPrompt,
            $test,
            $shared,
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
            Console::miniTask('No credentials found for', $addCredentialsPrompt->displayName, false);
            Console::newLine();

            return $this->addNewCredentials($host, $addCredentialsPrompt);
        }

        $choices = collect(array_keys($this->getAllConfigsForApi($host)));

        $addNewAccountText = 'Add new account';

        $choices->push($addNewAccountText);

        $result = Console::choice(
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

    protected function addNewCredentials(string $host, AddApiCredentialsPrompt $addCredentialsPrompt): array
    {
        Console::info($addCredentialsPrompt->helpText ?? 'Retrieve your token here:');
        Console::comment($addCredentialsPrompt->url);

        if (count($addCredentialsPrompt->requiredScopes)) {
            Console::newLine();
            Console::info(
                'Required scopes: '
                    . implode(
                        ', ',
                        array_map(fn ($s) => "<comment>{$s}</comment>", $addCredentialsPrompt->requiredScopes)
                    )
            );
        }

        if (count($addCredentialsPrompt->optionalScopes)) {
            Console::newLine();
            Console::info(
                'Optional scopes (include them if you want Bellows to handle these actions): '
                    . implode(
                        ', ',
                        array_map(fn ($s) => "<comment>{$s}</comment>", $addCredentialsPrompt->optionalScopes)
                    )
            );
        }

        $value = collect($addCredentialsPrompt->credentials)->mapWithKeys(
            fn ($value) => [
                $value => Console::secret(
                    Str::of($value)->replace('_', ' ')->title()->replace(' Id', ' ID')->toString()
                ),
            ]
        )->toArray();

        do {
            $accountName = Console::ask('Account Name (for your own reference)');
        } while (
            $this->getApiConfigValue($host, $accountName)
            && !Console::confirm('Overwrite existing account?')
        );

        $this->setApiConfigValue($host, $accountName, $value);

        return $value;
    }
}
