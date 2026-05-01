<?php

declare(strict_types=1);

return [
    'consumer' => [
        'enabled' => env('RBAC_KAFKA_ENABLED', true),
        // Reserved fixed topic for RBAC snapshots; always consumed by rbac:consume-snapshots.
        'topic' => env('RBAC_KAFKA_TOPIC', env('KAFKA_TOPIC_RBAC_SNAPSHOTS', 'iam.rbac.snapshots.v1')),
        'group_id' => env('RBAC_KAFKA_GROUP_ID', 'rbac-materializer'),
        // Optional: app-specific topics to consume in the same worker.
        'handlers' => [
            // 'auth.events.v1' => \App\Kafka\Handlers\AuthEventsTopicHandler::class,
        ],
        // skip|fail: behavior when a message arrives for a topic without registered handler.
        'on_unhandled_topic' => env('RBAC_KAFKA_ON_UNHANDLED_TOPIC', 'skip'),
    ],

    'store' => [
        'driver' => 'database',
        'table' => env('RBAC_STORE_TABLE', 'rbac_user_permission_snapshots'),
    ],

    'fallback' => [
        'enabled' => env('RBAC_IAM_FALLBACK_ENABLED', true),
        'base_url' => env('RBAC_IAM_BASE_URL', env('APP_URL', 'http://localhost:8000')),
        'internal_token' => env('RBAC_IAM_INTERNAL_TOKEN', env('RBAC_INTERNAL_TOKEN')),
        'timeout_ms' => (int) env('RBAC_IAM_TIMEOUT_MS', 1500),
    ],

    'auth' => [
        'guard' => env('RBAC_AUTH_GUARD', 'web'),
        'strict_deny' => env('RBAC_STRICT_DENY', true),
        'fail_mode' => env('RBAC_FAIL_MODE', 'deny'),
    ],

    'surface' => [
        'default' => env('RBAC_DEFAULT_SURFACE'),
    ],

    'gateway' => [
        'internal_header' => env('RBAC_GATEWAY_INTERNAL_HEADER', 'X-Internal-Gateway'),
        'internal_secret' => env('RBAC_GATEWAY_INTERNAL_SECRET', 'apisix'),
        'userinfo_header' => env('RBAC_GATEWAY_USERINFO_HEADER', 'X-Userinfo'),
        'log_missing_headers' => env('RBAC_GATEWAY_LOG_MISSING_HEADERS', false),
    ],
];

