<?php

use Bellows\Dns\DnsFactory;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->plugin()->setup();
});

it('can match a domain by its nameserver', function ($provider, $domain, $ns) {
    expect($provider::matchByNameserver($ns))->toBe(true);
    expect($provider::matchByNameserver('somethingelse.com'))->toBe(false);
})->with('dnsproviders');

it('can match a provider based on its domain', function ($provider, $domain) {
    expect(DnsFactory::fromDomain($domain))->toBeInstanceOf($provider);
})->with('dnsproviders');

it('can handle A records on a domain', function ($provider, $domain) {
    $record = Str::random(10);

    $inst = DnsFactory::fromDomain($domain);

    $inst->setCredentials();

    expect($inst->getARecord($record))->toBeNull();

    $inst->addARecord($record, '127.0.0.1', 600);

    expect($inst->getARecord($record))->toBe('127.0.0.1');
})->with('dnsproviders');

it('can handle TXT records on a domain', function ($provider, $domain) {
    $record = Str::random(10);

    $inst = DnsFactory::fromDomain($domain);

    $inst->setCredentials();

    expect($inst->getTXTRecord($record))->toBeNull();

    $inst->addTXTRecord($record, '127.0.0.2', 600);

    expect($inst->getTXTRecord($record))->toBe('127.0.0.2');
})->with('dnsproviders');

it('can handle CNAME records on a domain', function ($provider, $domain) {
    $record = Str::random(10);

    $inst = DnsFactory::fromDomain($domain);

    $inst->setCredentials();

    expect($inst->getCNAMERecord($record))->toBeNull();

    $inst->addCNAMERecord($record, 'bellowstesting.joe.codes', 600);

    expect($inst->getCNAMERecord($record))->toBe('bellowstesting.joe.codes');
})->with('dnsproviders');
