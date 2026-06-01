<?php

return [
    'enabled' => env('LOOKOUT_ENABLED', true),

    'dashboard' => [
        'enabled' => env('LOOKOUT_DASHBOARD_ENABLED', ! app()->isProduction()),
        'path' => env('LOOKOUT_PATH', 'lookout'),
        'middleware' => ['web'],
        'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', env('LOOKOUT_ALLOWED_IPS', ''))))),
        'localhost_only' => env('LOOKOUT_LOCALHOST_ONLY', false),
        'basic_auth' => [
            'user' => env('LOOKOUT_BASIC_AUTH_USER'),
            'pass' => env('LOOKOUT_BASIC_AUTH_PASS'),
        ],
        'rate_limit' => 60,
    ],

    'api' => [
        'enabled' => env('LOOKOUT_API_ENABLED', false),
        'token' => env('LOOKOUT_API_TOKEN'),
        'deploy_marker_token' => env('LOOKOUT_DEPLOY_MARKER_TOKEN'),
    ],

    'storage' => [
        'driver' => env('LOOKOUT_STORAGE_DRIVER', 'sqlite'),
        'connection' => env('LOOKOUT_STORAGE_CONNECTION', 'lookout'),
        'path' => env('LOOKOUT_STORAGE_PATH', storage_path('lookout/lookout.sqlite')),
        'pragmas' => [
            'journal_mode' => 'WAL',
            'synchronous' => 'NORMAL',
            'cache_size' => -64000,
            'mmap_size' => 268435456,
            'temp_store' => 'MEMORY',
        ],
    ],

    'ingestion' => [
        'queue' => env('LOOKOUT_QUEUE', 'default'),
        'connection' => env('LOOKOUT_QUEUE_CONNECTION'),
        'batch_size' => 100,
        'sync_exceptions' => env('LOOKOUT_EXCEPTION_SYNC', true),
        'max_request_body_bytes' => env('LOOKOUT_MAX_REQUEST_BODY_BYTES', 16384),
    ],

    'sampling' => [
        'auto' => env('LOOKOUT_AUTO_SAMPLE', true),
        'request' => env('LOOKOUT_REQUEST_SAMPLE_RATE'),
        'command' => env('LOOKOUT_COMMAND_SAMPLE_RATE', 1.0),
        'scheduled_task' => env('LOOKOUT_SCHEDULED_SAMPLE_RATE', 1.0),
        'exception' => env('LOOKOUT_EXCEPTION_SAMPLE_RATE', 1.0),
    ],

    'filters' => [
        'ignore_routes' => [
            env('LOOKOUT_PATH', 'lookout'),
            env('LOOKOUT_PATH', 'lookout').'/*',
            'horizon/*',
            'telescope/*',
            '_debugbar/*',
        ],
        'ignore_commands' => ['schedule:run', 'queue:work', 'lookout:*'],
    ],

    'redaction' => [
        'patterns' => [
            'password', 'token', 'secret', 'api_key',
            'authorization', 'credit_card', 'ssn', 'cvv',
            'cookie', 'laravel_session', 'remember_web', 'xsrf-token',
        ],
        'custom' => [],
    ],

    'recorders' => [
        'request' => true,
        'query' => true,
        'exception' => true,
        'job' => true,
        'scheduled_task' => true,
        'command' => true,
        'cache' => true,
        'mail' => true,
        'notification' => true,
        'log' => true,
        'outgoing_http' => true,
    ],

    'retention' => [
        'days' => env('LOOKOUT_RETENTION_DAYS', 14),
        'prune_chance' => 1000,
    ],

    'alerting' => [
        'enabled' => env('LOOKOUT_ALERTING_ENABLED', false),
        'channels' => [
            'email' => env('LOOKOUT_ALERT_EMAIL'),
            'slack' => env('LOOKOUT_ALERT_SLACK_WEBHOOK'),
            'webhook' => env('LOOKOUT_ALERT_WEBHOOK_URL'),
        ],
    ],
];
