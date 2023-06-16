<?php

namespace Bellows\Config;

enum KickoffConfigKeys: string
{
    case NAME = 'name';
    case EXTENDS = 'extends';
    case COMPOSER = 'composer';
    case NPM = 'npm';
    case COMPOSER_DEV = 'composer-dev';
    case NPM_DEV = 'npm-dev';
    case PLUGINS = 'plugins';
    case RENAME_FILES = 'rename-files';
    case REMOVE_FILES = 'remove-files';
    case SERVICE_PROVIDERS = 'service-providers';
    case GIT_IGNORE = 'git-ignore';
    case DIRECTORIES_TO_COPY = 'directories-to-copy';
    case COMPOSER_SCRIPTS = 'composer-scripts';
    case COMPOSER_ALLOW_PLUGINS = 'composer-allow-plugins';
    case VENDOR_PUBLISH_TAGS = 'vendor-publish-tags';
    case VENDOR_PUBLISH_PROVIDERS = 'vendor-publish-providers';
    case VENDOR_PUBLISH = 'vendor-publish';
    case ENV = 'env';
    case FACADES = 'facades';
    case ALIASES = 'aliases';
    case CONFIG = 'config';
    case COMMANDS = 'commands';

    public static function defaultValue(KickoffConfigKeys $key)
    {
        return match ($key) {
            default => []
        };
    }
}
