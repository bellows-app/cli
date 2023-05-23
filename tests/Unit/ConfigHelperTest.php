<?php

use Bellows\Facades\Project;
use Bellows\Util\ConfigHelper;

uses(Tests\TestCase::class);

beforeEach(function () {
    Project::config()->directory = __DIR__ . '/../stubs/test-app';
    collect(glob(Project::config()->directory . '/config/*.php'))->each(fn ($file) => unlink($file));
});

afterEach(function () {
    collect(glob(Project::config()->directory . '/config/*.php'))->each(fn ($file) => unlink($file));
});

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
    $value = "ok sure";

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

    $result = (new ConfigHelper())->update('test.output.newthing.otherthing', 'ok sure');

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
