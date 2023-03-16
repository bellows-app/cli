<?php

use Bellows\Dns\Cloudflare;
use Bellows\Dns\DigitalOcean;
use Bellows\Dns\GoDaddy;

dataset('dnsproviders', function () {
    return [
        [Cloudflare::class, 'ipghost.app', 'ns.cloudflare.com'],
        [GoDaddy::class, 'deploymate.app', 'domaincontrol.com'],
        [DigitalOcean::class, 'bellows.app', 'digitalocean.com'],
    ];
});
