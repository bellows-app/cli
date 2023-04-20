<?php

namespace Bellows\Dns;

use Bellows\Enums\DnsRecordType;
use Bellows\Facades\Console;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GoDaddy extends DnsProvider
{
    protected string $apiBaseUrl = 'https://api.godaddy.com/v1/';

    public static function getNameServerDomain(): string
    {
        return 'domaincontrol.com';
    }

    public function addCNAMERecord(string $name, string $value, int $ttl): bool
    {
        return $this->addRecord(
            DnsRecordType::CNAME,
            $name,
            $this->withTrailingDot($value),
            $ttl,
        );
    }

    protected function accountHasDomain(array $credentials): bool
    {
        try {
            $this->getClient($credentials)->get("domains/{$this->baseDomain}")->throw();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function addNewCredentials(): array
    {
        Console::info('Looks like you need GoDaddy keys. You can retrieve them here:');
        Console::comment('https://developer.godaddy.com/keys');
        $key = Console::secret('Your GoDaddy key');
        $secret = Console::secret('Your GoDaddy secret');

        return ['key' => $key, 'secret' => $secret];
    }

    protected function testApiCall(array $credentials): bool
    {
        try {
            $this->getClient($credentials)->get('domains')->throw()->json();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function getClient(array $credentials): PendingRequest
    {
        return Http::baseUrl($this->apiBaseUrl)
            ->withToken("{$credentials['key']}:{$credentials['secret']}", 'sso-key')
            ->acceptJson()
            ->asJson();
    }

    protected function addRecord(DnsRecordType $type, string $name, string $value, int $ttl): bool
    {
        try {
            if ($this->getFullRecord($type, $name)) {
                Console::miniTask("Updating existing {$type->value} record for {$name} to", $value);

                Http::dnsProvider()->put("domains/{$this->baseDomain}/records/{$type->value}/{$name}", [
                    [
                        'data' => $value,
                        'ttl'  => $ttl,
                    ],
                ]);

                return true;
            }

            Console::miniTask("Adding new {$type->value} record for {$name} to", $value);

            Http::dnsProvider()->patch("domains/{$this->baseDomain}/records", [
                [
                    'data' => $value,
                    'name' => $name,
                    'ttl'  => $ttl,
                    'type' => $type->value,
                ],
            ]);

            return true;
        } catch (Exception $e) {
            Console::error($e->getMessage());

            return false;
        }
    }

    protected function getRecord(DnsRecordType $type, string $name): ?string
    {
        return $this->getFullRecord($type, $name)['data'] ?? null;
    }

    protected function getFullRecord(DnsRecordType $type, string $name): ?array
    {
        try {
            $response = Http::dnsProvider()->get("domains/{$this->baseDomain}/records/{$type->value}/{$name}")->throw()->json();

            return $response[0] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}
