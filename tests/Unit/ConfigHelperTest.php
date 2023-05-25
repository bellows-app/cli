<?php

use Bellows\Facades\Project;
use Bellows\Util\ConfigHelper;
use Bellows\Util\Value;

uses(Tests\TestCase::class);

beforeEach(function () {
    Project::config()->directory = __DIR__ . '/../stubs/test-app';
    cleanUpConfigs();
});

afterEach(function () {
    cleanUpConfigs();
});

function cleanUpConfigs()
{
    collect(glob(Project::config()->directory . '/config/*.php'))->each(fn ($file) => unlink($file));
}

function writeToConfig(string $content, string $filename = 'test')
{
    file_put_contents(
        Project::config()->directory . '/config/' . $filename . '.php',
        $content
    );
}

function getConfigContents(string $filename = 'test'): string
{
    return file_get_contents(
        Project::config()->directory . '/config/' . $filename . '.php'
    );
}

it('can replace a string value in a config', function () {
    writeToConfig(
        <<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
    ],
];
CONFIG,
    );

    (new ConfigHelper())->update('test.output.routes', 'resources/routes/routes.json');

    expect(getConfigContents())->toBe(<<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'resources/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
    ],
];
CONFIG);
});

it('can replace an array value in a config', function () {
    writeToConfig(
        <<<'CONFIG'
        <?php

return [
    'output' => [
        'routes' => ['resources/routes/routes.json'],
        'typescript' => 'scripts/types/routes.d.ts',
    ],
];
CONFIG
    );

    $result = (new ConfigHelper())->update('test.output.routes', "['resources/routes/routes.json']");

    expect(getConfigContents())->toBe(<<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => ['resources/routes/routes.json'],
        'typescript' => 'scripts/types/routes.d.ts',
    ],
];
CONFIG);
});

it('can add a new top level value in a config', function () {
    writeToConfig(
        <<<'CONFIG'
    <?php

    return [
        'output' => [
            'routes' => 'scripts/routes/routes.json',
            'typescript' => 'scripts/types/routes.d.ts',
        ],
    ];
    CONFIG
    );

    (new ConfigHelper())->update('test.newthing', 'here we are');

    expect(getConfigContents())->toBe(<<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
    ],
    'newthing' => 'here we are',
];
CONFIG);
})->group('newvalue');

it('can add a nested value in a config', function () {
    writeToConfig(
        <<<'CONFIG'
    <?php

    return [
        'output' => [
            'routes' => 'scripts/routes/routes.json',
            'typescript' => 'scripts/types/routes.d.ts',
        ],
    ];
    CONFIG
    );

    (new ConfigHelper())->update('test.output.newthing', 'here we are');

    expect(getConfigContents())->toBe(<<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => 'here we are',
    ],
];
CONFIG);
})->group('newvalue');

it('can add a deeply nested value in a config', function () {
    writeToConfig(
        <<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => null,
    ],
];
CONFIG
    );

    $key = 'test.output.newthing.otherthing';
    $value = 'ok sure';

    (new ConfigHelper())->update($key, $value);

    expect(getConfigContents())->toBe(<<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => [
            'otherthing' => 'ok sure',
        ],
    ],
];
CONFIG);
})->group('newvalue');

it('can add a deeply nested value in a config to an array', function () {
    writeToConfig(
        <<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => [],
    ],
];
CONFIG
    );

    (new ConfigHelper())->update('test.output.newthing.otherthing', 'ok sure');

    expect(getConfigContents())->toBe(<<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => [
            'otherthing' => 'ok sure',
        ],
    ],
];
CONFIG);
})->group('newvalue');

it('will not quote a function when it is a value', function () {
    writeToConfig(
        <<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => null,
    ],
];
CONFIG
    );

    (new ConfigHelper())->update('test.output.newthing', 'resource_path("ok sure")');

    expect(getConfigContents())->toBe(<<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => resource_path("ok sure"),
    ],
];
CONFIG);
})->group('newvalue');

it('will not quote pre determined values', function ($value, $expected) {
    writeToConfig(
        <<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => null,
    ],
];
CONFIG
    );

    (new ConfigHelper())->update('test.output.newthing', $value);

    expect(getConfigContents())->toBe(<<<CONFIG
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => $expected,
    ],
];
CONFIG);
})->group('newvalue')->with([
    [true, 'true'],
    [false, 'false'],
    ['true', 'true'],
    ['false', 'false'],
    [null, 'null'],
    ['null', 'null'],
    [1, '1'],
    ['1', '1'],
]);

it('will not quote a number when it is a value', function () {
    writeToConfig(
        <<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => null,
    ],
];
CONFIG
    );

    (new ConfigHelper())->update('test.output.newthing', 1);

    expect(getConfigContents())->toBe(<<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => 1,
    ],
];
CONFIG);
})->group('newvalue');

it('will not quote a class referenced statically', function () {
    writeToConfig(
        <<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => null,
    ],
];
CONFIG
    );

    (new ConfigHelper())->update('test.output.newthing', 'SomeClass::class');

    expect(getConfigContents())->toBe(<<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => SomeClass::class,
    ],
];
CONFIG);
})->group('newvalue');

it('will fill in a deeply nested value that is missing', function () {
    writeToConfig(
        <<<'CONFIG'
<?php

return [
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'bugsnag'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],
    ],
];
CONFIG
    );

    (new ConfigHelper())->update('test.channels.bugsnag.driver', 'bugsnag');

    expect(getConfigContents())->toBe(<<<'CONFIG'
<?php

return [
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'bugsnag'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],
        'bugsnag' => [
            'driver' => 'bugsnag',
        ],
    ],
];
CONFIG);
})->group('newvalue');

it('will ignore a raw value', function () {
    writeToConfig(
        <<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
    ],
];
CONFIG
    );

    (new ConfigHelper())->update('test.routes', Value::raw('this should be quoted but it will not be'));

    expect(getConfigContents())->toBe(<<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => this should be quoted but it will not be,
        'typescript' => 'scripts/types/routes.d.ts',
    ],
];
CONFIG);
})->group('newvalue');

it('will create a new file if the file is not found and add the config', function () {
    (new ConfigHelper())->update('nada.thing', 'val val val');

    expect(getConfigContents('nada'))->toBe(<<<'CONFIG'
<?php

return [
    'thing' => 'val val val',
];
CONFIG);
})->group('newvalue');

it('can append a value to an existing config array', function () {
    writeToConfig(
        <<<'CONFIG'
<?php

return [
    'providers' => [
        MyProvider::class,
    ],
];
CONFIG
    );

    (new ConfigHelper())->append('test.providers', 'MyNewProvider::class');

    expect(getConfigContents())->toBe(<<<'CONFIG'
<?php

return [
    'providers' => [
        MyProvider::class,
        MyNewProvider::class,
    ],
];
CONFIG);
})->group('newvalue');


it('can append a value to an empty config array', function () {
    writeToConfig(
        <<<'CONFIG'
<?php

return [
    'providers' => [],
];
CONFIG
    );

    (new ConfigHelper())->append('test.providers', 'MyNewProvider::class');

    expect(getConfigContents())->toBe(<<<'CONFIG'
<?php

return [
    'providers' => [
        MyNewProvider::class,
    ],
];
CONFIG);
})->group('newvalue');
