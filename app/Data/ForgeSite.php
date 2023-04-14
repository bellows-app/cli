<?php

namespace Bellows\Data;

use Spatie\LaravelData\Data;

class ForgeSite extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public array $aliases,
        public string $directory,
        public bool $wildcards,
        public string $status,
        public bool $quick_deploy,
        public string $project_type,
        public string $php_version,
        public string $username,
        public string $deployment_url,
        public bool $is_secured,
        public array $tags,
        public ?string $repository_provider,
        public ?string $repository_status,
        public ?string $deployment_status,
        public ?string $app,
        public ?string $app_status,
        public ?string $slack_channel,
        public ?string $telegram_chat_id,
        public ?string $telegram_chat_title,
        public ?string $teams_webhook_url,
        public ?string $discord_webhook_url,
        public ?string $created_at,
        public ?string $telegram_secret,
    ) {
    }
}
