<?php

declare(strict_types=1);

/**
 * Normalize comma-separated broker list and remove tcp:// prefixes.
 */
$normalizeBrokers = static function (string $raw): string {
    $parts = array_filter(array_map(static function (string $broker): string {
        $clean = trim($broker);
        if (str_starts_with($clean, 'tcp://')) {
            $clean = substr($clean, 6);
        }

        return $clean;
    }, explode(',', $raw)), static fn (string $broker): bool => $broker !== '');

    return implode(',', array_values($parts));
};

$rawBrokers = (string) env('KAFKA_BROKERS', env('KAFKA_BOOTSTRAP_SERVERS', '127.0.0.1:9092'));
$brokerList = $normalizeBrokers($rawBrokers);

return [
    /*
    |--------------------------------------------------------------------------
    | laravel-kafka base settings
    |--------------------------------------------------------------------------
    */
    'enabled' => env('KAFKA_ENABLED', true),

    // mateusjunges/laravel-kafka expects broker list without tcp:// scheme.
    'brokers' => $brokerList !== '' ? $brokerList : '127.0.0.1:9092',

    'securityProtocol' => env('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),

    'sasl' => [
        'mechanisms' => env('KAFKA_MECHANISMS', 'PLAIN'),
        'username' => env('KAFKA_USERNAME'),
        'password' => env('KAFKA_PASSWORD'),
    ],

    'consumer_group_id' => env('KAFKA_CONSUMER_GROUP_ID', 'group'),
    'consumer_timeout_ms' => env('KAFKA_CONSUMER_DEFAULT_TIMEOUT', 2000),
    'offset_reset' => env('KAFKA_OFFSET_RESET', 'latest'),
    'auto_commit' => env('KAFKA_AUTO_COMMIT', true),
    'sleep_on_error' => env('KAFKA_ERROR_SLEEP', 5),
    'partition' => env('KAFKA_PARTITION', 0),
    'compression' => env('KAFKA_COMPRESSION_TYPE', 'snappy'),
    'debug' => env('KAFKA_DEBUG', false),
    'flush_retry_sleep_in_ms' => 100,
    'flush_retries' => 10,
    'flush_timeout_in_ms' => 1000,
    'cache_driver' => env('KAFKA_CACHE_DRIVER', env('CACHE_DRIVER', env('CACHE_STORE', 'database'))),
    'message_id_key' => env('MESSAGE_ID_KEY', 'laravel-kafka::message-id'),
    'client_id' => env('KAFKA_CLIENT_ID', env('APP_NAME', 'mmt-service')),
    'auto_create_topic' => env('KAFKA_AUTO_CREATE_TOPIC', false),

    'topics' => [
        'auth_events' => env('KAFKA_TOPIC_AUTH_EVENTS', 'auth.events.v1'),
        'rbac_snapshots' => env('KAFKA_TOPIC_RBAC_SNAPSHOTS', 'iam.rbac.snapshots.v1'),
    ],

    'producer' => [
        'acks' => (int) env('KAFKA_PRODUCER_ACKS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | RBAC module settings
    |--------------------------------------------------------------------------
    */
    'rbac' => [
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
    ],
];

