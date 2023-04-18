<?php

namespace Bellows\ServerProviders\Forge;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class Client
{
    protected static ?Client $instance = null;

    const API_URL = 'https://forge.laravel.com/api/v1';

    protected string $token;

    private function __construct()
    {
    }

    public static function getInstance(): Client
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function setToken(string $token)
    {
        $this->token = $token;
    }

    public function http()
    {
        return Http::baseUrl(self::API_URL)
            ->withToken($this->token)
            ->acceptJson()
            ->asJson()
            ->retry(
                3,
                100,
                function (
                    Exception $exception,
                    PendingRequest $request
                ) {
                    if ($exception instanceof RequestException && $exception->response->status() === 429) {
                        sleep($exception->response->header('retry-after') + 1);

                        return true;
                    }

                    return false;
                }
            );
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }
}
