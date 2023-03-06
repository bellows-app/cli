cd /home/test/testproject.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader=

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force

    ! $FORGE_PHP artisan octane:status || $FORGE_PHP artisan octane:reload
fi