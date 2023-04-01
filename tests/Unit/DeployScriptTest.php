<?php

use Bellows\DeployScript;

uses(Tests\TestCase::class);

it('can add a string after the composer install command', function () {
    $script = app(DeployScript::class);

    $current = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    $result = $script->addAfterComposerInstall(
        $current,
        'npm run production'
    );

    $expected = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm run production

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    expect($result)->toBe($expected);
});

it('can add an array of strings after the composer install command', function () {
    $script = app(DeployScript::class);

    $current = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    $result = $script->addAfterComposerInstall(
        $current,
        ['npm run production', 'npm run another-thing']
    );

    $expected = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm run production
npm run another-thing

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    expect($result)->toBe($expected);
});

it('can add a string before the php reload command', function () {
    $script = app(DeployScript::class);

    $current = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm run something-else

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    $result = $script->addBeforePHPReload(
        $current,
        'npm run production'
    );

    $expected = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm run something-else

npm run production

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    expect($result)->toBe($expected);
});

it('can add an array of strings before the php reload command', function () {
    $script = app(DeployScript::class);

    $current = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm run something-else

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    $result = $script->addBeforePHPReload(
        $current,
        ['npm run production', 'npm run another-thing']
    );

    $expected = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm run something-else

npm run production
npm run another-thing

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    expect($result)->toBe($expected);
});

it('can add a string before the php reload command in an octane script', function () {
    $script = app(DeployScript::class);
    $current = <<<'SCRIPT'
    cd /home/test/testproject.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader=

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force

    ! $FORGE_PHP artisan octane:status || $FORGE_PHP artisan octane:reload
fi
SCRIPT;

    $result = $script->addBeforePHPReload(
        $current,
        'npm run production'
    );

    $injected = "\tnpm run production";

    $expected = <<<SCRIPT
    cd /home/test/testproject.com
git pull origin \$FORGE_SITE_BRANCH

\$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader=

if [ -f artisan ]; then
    \$FORGE_PHP artisan migrate --force

$injected
    ! \$FORGE_PHP artisan octane:status || \$FORGE_PHP artisan octane:reload
fi
SCRIPT;

    expect($result)->toBe($expected);
});

it('can add a string after an arbitrary line', function () {
    $script = app(DeployScript::class);

    $current = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    $result = $script->addAfterLine(
        $current,
        'fi',
        'npm run production'
    );

    $expected = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi

npm run production

SCRIPT;

    expect($result)->toBe($expected);
});

it('can add an array of strings after an arbitrary line', function () {
    $script = app(DeployScript::class);

    $current = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    $result = $script->addAfterLine(
        $current,
        'fi',
        ['npm run production', 'npm run another-thing']
    );

    $expected = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi

npm run production
npm run another-thing

SCRIPT;

    expect($result)->toBe($expected);
});

it('can add a string before an arbitrary line', function () {
    $script = app(DeployScript::class);

    $current = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    $result = $script->addBeforeLine($current, 'if [ -f artisan ]; then', 'npm run production');

    $expected = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

npm run production

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    expect($result)->toBe($expected);
});

it('can add an array of strings before an arbitrary line', function () {
    $script = app(DeployScript::class);

    $current = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    $result = $script->addBeforeLine(
        $current,
        'if [ -f artisan ]; then',
        ['npm run production', 'npm run another-thing']
    );

    $expected = <<<'SCRIPT'
cd /home/forgeittest/forgeittest.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

npm run production
npm run another-thing

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
SCRIPT;

    expect($result)->toBe($expected);
});
