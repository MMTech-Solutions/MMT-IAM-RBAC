<?php

declare(strict_types=1);

return [

    'kafka' => [
        'enabled' => env('RCAB_KAFKA_ENABLED', true),
        'brokers' => env('RCAB_KAFKA_BROKERS', env('KAFKA_BROKERS', env('KAFKA_BOOTSTRAP_SERVERS', '127.0.0.1:9092'))),
        // Reserved fixed topic for RBAC snapshots; always consumed by rcab:consume-snapshots.
        'topic' => 'iam.rbac.snapshots.v1',
        'group_id' => env('RCAB_KAFKA_GROUP_ID', 'rcab-materializer'),
        // Optional: app-specific topics to consume in the same worker.
        'handlers' => [
            // 'auth.events.v1' => \App\Kafka\Handlers\AuthEventsTopicHandler::class,
        ],
        // skip|fail: behavior when a message arrives for a topic without registered handler.
        'on_unhandled_topic' => env('RCAB_KAFKA_ON_UNHANDLED_TOPIC', 'skip'),
    ],

    'store' => [
        'driver' => 'database',
        'table' => env('RCAB_STORE_TABLE', 'rcab_user_permission_snapshots'),
    ],

    'fallback' => [
        'enabled' => env('RCAB_IAM_FALLBACK_ENABLED', true),
        'base_url' => env('RCAB_IAM_BASE_URL', env('APP_URL', 'http://localhost:8000')),
        'internal_token' => env('RCAB_IAM_INTERNAL_TOKEN', env('RBAC_INTERNAL_TOKEN')),
        'timeout_ms' => (int) env('RCAB_IAM_TIMEOUT_MS', 1500),
    ],

    'auth' => [
        'guard' => env('RCAB_AUTH_GUARD', 'web'),
        'strict_deny' => env('RCAB_STRICT_DENY', true),
        'fail_mode' => env('RCAB_FAIL_MODE', 'deny'),
    ],

    'surface' => [
        'default' => env('RCAB_DEFAULT_SURFACE'),
    ],

    'gateway' => [
        'internal_header' => env('RCAB_GATEWAY_INTERNAL_HEADER', 'X-Internal-Gateway'),
        'internal_secret' => env('RCAB_GATEWAY_INTERNAL_SECRET', 'apisix'),
        'userinfo_header' => env('RCAB_GATEWAY_USERINFO_HEADER', 'X-Userinfo'),
        'log_missing_headers' => env('RCAB_GATEWAY_LOG_MISSING_HEADERS', false),
    ],
];

