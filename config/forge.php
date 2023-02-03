<?php

return [
    'plugins' => [
        \App\Plugins\Optimize::class,
        \App\Plugins\Octane::class,
        \App\Plugins\InertiaServerSideRendering::class,
        \App\Plugins\QuickDeploy::class,
        \App\Plugins\CompileAssets::class,
        \App\Plugins\MomentumTrail::class,
        \App\Plugins\DigitalOceanDatabase::class,
        \App\Plugins\BugsnagPHP::class,
        \App\Plugins\BugsnagJS::class,
        \App\Plugins\LetsEncryptSSL::class,
        \App\Plugins\Hashids::class,
        \App\Plugins\FathomAnalytics::class,
        \App\Plugins\Postmark::class,
        \App\Plugins\RunSchedule::class,
        \App\Plugins\DatabaseWorker::class,
    ],
    'digitalocean' => [
        'joe'    => env('DIGITAL_OCEAN_TOKEN_JOE'),
        'batzen' => env('DIGITAL_OCEAN_TOKEN_BATZEN'),
    ],
    'postmark' => [
        'joe'    => env('POSTMARK_API_TOKEN_JOE'),
        'batzen' => env('POSTMARK_API_TOKEN_BATZEN'),
    ],
];

// 'SUPPORT_EMAIL'               => 'joe@joe.codes',
// 'STRIPE_KEY'                  => '',
// 'STRIPE_SECRET'               => '',
// 'STRIPE_WEBHOOK_SECRET'       => '',
// 'QUEUE_CONNECTION'            => 'database',
// 'MAILCOACH_API_TOKEN'         => '',
// 'OH_DEAR_HEALTH_CHECK_SECRET' => '',
// 'SLACK_ALERT_WEBHOOK'         => '',