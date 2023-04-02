<?php

use Illuminate\Http\Client\Request;

dataset('http_client_types', function () {
    return [
        ['createClient', fn () => true],
        [
            'createJsonClient',
            fn (Request $request) => $request->hasHeader('Accept', 'application/json')
                && $request->hasHeader('Content-Type', 'application/json')
        ],
    ];
});
