<?php

use Bellows\Util\Domain;

uses(Tests\TestCase::class);

it('can detect if the string is the base domain', function () {
    expect(Domain::isBaseDomain('example.com'))->toBeTrue();
});

it('can detect if the string is not the base domain', function () {
    expect(Domain::isBaseDomain('subdomain.example.com'))->toBeFalse();
});

it('can get the base domain', function () {
    expect(Domain::getBaseDomain('subdomain.example.com'))->toBe('example.com');
});

it('can get the subdomain', function () {
    expect(Domain::getSubdomain('subdomain.example.com'))->toBe('subdomain');
});

it('can get the full domain', function () {
    expect(Domain::getFullDomain('subdomain', 'example.com'))->toBe('subdomain.example.com');
});
