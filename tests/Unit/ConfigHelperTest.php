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

function writeToConfig(string $filename, string $content)
{
    file_put_contents(
        Project::config()->directory . '/config/' . $filename . '.php',
        $content
    );
}

function getConfigContents(string $filename): string
{
    return file_get_contents(
        Project::config()->directory . '/config/' . $filename . '.php'
    );
}

it('can replace a string value in a config', function () {
    writeToConfig(
        'test',
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

    (new ConfigHelper())->update('test.output.routes', 'resources/routes/routes.json');

    expect(getConfigContents('test'))->toBe(<<<'CONFIG'
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
        'array-value',
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

    $result = (new ConfigHelper())->update('array-value.output.routes', "['resources/routes/routes.json']");

    expect(getConfigContents('array-value'))->toBe(<<<'CONFIG'
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
    $helper = new ConfigHelper();

    //     $config = <<<'CONFIG'
    // <?php

    // return [
    //     'output' => [
    //         'routes' => 'scripts/routes/routes.json',
    //         'typescript' => 'scripts/types/routes.d.ts',
    //     ],
    // ];
    // CONFIG;

    $key = 'newthing';
    $value = "here we are";

    $result = $helper->update($key, $value);

    expect($result)->toBe(<<<'CONFIG'
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
    $helper = new ConfigHelper();

    //     $config = <<<'CONFIG'
    // <?php

    // return [
    //     'output' => [
    //         'routes' => 'scripts/routes/routes.json',
    //         'typescript' => 'scripts/types/routes.d.ts',
    //     ],
    // ];
    // CONFIG;

    $key = 'test.output.newthing';
    $value = "here we are";

    $result = $helper->update($key, $value);

    expect($result)->toBe(<<<'CONFIG'
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
    $helper = new ConfigHelper();

    $config = <<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => null,
    ],
];
CONFIG;

    $key = 'test.output.newthing.otherthing';
    $value = "ok sure";

    $result = $helper->update($key, $value);

    expect($result)->toBe(<<<'CONFIG'
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
    $helper = new ConfigHelper();

    $config = <<<'CONFIG'
<?php

return [
    'output' => [
        'routes' => 'scripts/routes/routes.json',
        'typescript' => 'scripts/types/routes.d.ts',
        'newthing' => [],
    ],
];
CONFIG;

    $key = 'test.output.newthing.otherthing';
    $value = "ok sure";

    $result = $helper->update($key, $value);

    expect($result)->toBe(<<<'CONFIG'
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
