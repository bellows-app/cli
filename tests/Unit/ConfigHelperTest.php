<?php

use Bellows\Util\ConfigHelper;

uses(Tests\TestCase::class);

it('can replace a string value in a config', function () {
    $helper = new ConfigHelper();

    $config = <<<CONFIG
<?php

return [
    'output' => [
        'routes' => resource_path('scripts/routes/routes.json'),
        'typescript' => resource_path('scripts/types/routes.d.ts'),
    ],
];
CONFIG;

    $key = 'output.routes';
    $value = "resource_path('resources/routes/routes.json')";

    $result = $helper->replace($config, $key, $value);

    expect($result)->toBe(<<<CONFIG
<?php

return [
    'output' => [
        'routes' => resource_path('resources/routes/routes.json'),
        'typescript' => resource_path('scripts/types/routes.d.ts'),
    ],
];
CONFIG);
});

it('can replace an array value in a config', function () {
    $helper = new ConfigHelper();

    $config = <<<CONFIG
<?php

return [
    'output' => [
        'routes' => [
            resource_path('scripts/routes/routes.json'),
            resource_path('scripts/routes/routes2.json'),
        ],
        'typescript' => resource_path('scripts/types/routes.d.ts'),
    ],
];
CONFIG;

    $key = 'output.routes';
    $value = "[resource_path('resources/routes/routes.json')]";

    $result = $helper->replace($config, $key, $value);

    expect($result)->toBe(<<<CONFIG
<?php

return [
    'output' => [
        'routes' => [resource_path('resources/routes/routes.json')],
        'typescript' => resource_path('scripts/types/routes.d.ts'),
    ],
];
CONFIG);
});

it('can add a new top level value in a config', function () {
    $helper = new ConfigHelper();

    $config = <<<CONFIG
<?php

return [
    'output' => [
        'routes' => resource_path('scripts/routes/routes.json'),
        'typescript' => resource_path('scripts/types/routes.d.ts'),
    ],
];
CONFIG;

    $key = 'newthing';
    $value = "'here we are'";

    $result = $helper->replace($config, $key, $value);

    expect($result)->toBe(<<<CONFIG
<?php

return [
    'output' => [
        'routes' => resource_path('scripts/routes/routes.json'),
        'typescript' => resource_path('scripts/types/routes.d.ts'),
    ],
'newthing' => 'here we are',
];
CONFIG);
})->group('newvalue');

it('can add a nested value in a config', function () {
    $helper = new ConfigHelper();

    $config = <<<CONFIG
<?php

return [
    'output' => [
        'routes' => resource_path('scripts/routes/routes.json'),
        'typescript' => resource_path('scripts/types/routes.d.ts'),
    ],
];
CONFIG;

    $key = 'output.newthing';
    $value = "'here we are'";

    $result = $helper->replace($config, $key, $value);

    expect($result)->toBe(<<<CONFIG
<?php

return [
    'output' => [
        'routes' => resource_path('scripts/routes/routes.json'),
        'typescript' => resource_path('scripts/types/routes.d.ts'),
'newthing' => 'here we are',
    ],
];
CONFIG);
})->group('newvalue');

it('can add a deeply nested value in a config', function () {
    $helper = new ConfigHelper();

    $config = <<<CONFIG
<?php

return [
    'output' => [
        'routes' => resource_path('scripts/routes/routes.json'),
        'typescript' => resource_path('scripts/types/routes.d.ts'),
        'newthing' => null,
    ],
];
CONFIG;

    $key = 'output.newthing.otherthing';
    $value = "'ok sure'";

    $result = $helper->replace($config, $key, $value);

    expect($result)->toBe(<<<CONFIG
<?php

return [
    'output' => [
        'routes' => resource_path('scripts/routes/routes.json'),
        'typescript' => resource_path('scripts/types/routes.d.ts'),
        'newthing' => [
'otherthing' => 'ok sure',
],
    ],
];
CONFIG);
})->group('newvalue');

it('can add a deeply nested value in a config to an array', function () {
    $helper = new ConfigHelper();

    $config = <<<CONFIG
<?php

return [
    'output' => [
        'routes' => resource_path('scripts/routes/routes.json'),
        'typescript' => resource_path('scripts/types/routes.d.ts'),
        'newthing' => [],
    ],
];
CONFIG;

    $key = 'output.newthing.otherthing';
    $value = "'ok sure'";

    $result = $helper->replace($config, $key, $value);

    expect($result)->toBe(<<<CONFIG
<?php

return [
    'output' => [
        'routes' => resource_path('scripts/routes/routes.json'),
        'typescript' => resource_path('scripts/types/routes.d.ts'),
        'newthing' => [
'otherthing' => 'ok sure',
],
    ],
];
CONFIG);
})->group('newvalue');
