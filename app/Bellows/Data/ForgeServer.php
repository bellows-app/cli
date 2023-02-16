<?php

namespace App\Bellows\Data;

use Spatie\LaravelData\Data;

class ForgeServer extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
        public string $ip_address,
        public ?string $provider,
        public ?string $size,
        public ?string $region,
        public ?string $ubuntu_version,
        public ?string $php_version,
        public ?string $php_cli_version,
        public ?string $database_type,
        public ?int $ssh_port,
        public ?string $private_ip_address,
        public ?string $local_public_key,
        public ?bool $revoked,
        public ?string $created_at,
        public ?bool $is_ready,
        public ?array $tags,
        public ?array $network,
        public ?int $credential_id,
        public ?string $provider_id,
        public ?string $db_status = null,
        public ?string $redis_status = null,
        public ?string $blackfire_status = null,
        public ?string $papertrail_status = null,
    ) {
    }
}
