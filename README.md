# MMT IAM RBAC package

Portable RBAC package for Laravel microservices.

## What it provides

- Permission checks by gateway `sub` with `request()->user()->can('permission.slug')`
- Kafka snapshot consumer (`iam.rbac.snapshots.v1`) always enabled in the command worker
- Reusable Kafka publisher service to emit events to any topic
- Multi-topic consumer with per-topic handlers (class-map)
- Local materialized store in database (`rbac_user_permission_snapshots`)
- IAM fallback endpoint support when local snapshot is missing

## Installation in a Laravel microservice

### 1) Require package (private repository)

In the microservice install:

```bash
composer require mmtech/iam-rbac:^1.0
```

### 2) Publish package files

```bash
php artisan vendor:publish --tag=rbac-config
php artisan vendor:publish --tag=rbac-migrations
php artisan migrate --no-interaction
```

### 3) Register middleware aliases

In `bootstrap/app.php`:

```php
$middleware->alias([
    'rbac.auth.user' => \Mmtech\Rbac\Http\Middleware\ResolveGatewayUserInfo::class,
    'rbac.bind.gateway.user' => \Mmtech\Rbac\Http\Middleware\BindGatewayUserToAuth::class,
]);
```

### 4) Configure env

```dotenv
RBAC_KAFKA_ENABLED=true
KAFKA_BROKERS=kafka.mmtech-solutions.com:9092
KAFKA_SECURITY_PROTOCOL=PLAINTEXT
RBAC_KAFKA_GROUP_ID=rbac-materializer
RBAC_KAFKA_ON_UNHANDLED_TOPIC=skip

RBAC_IAM_FALLBACK_ENABLED=true
RBAC_IAM_BASE_URL=http://iam-service
RBAC_IAM_INTERNAL_TOKEN=secret
RBAC_IAM_TIMEOUT_MS=1500

RBAC_FAIL_MODE=deny
RBAC_STRICT_DENY=true
RBAC_GATEWAY_INTERNAL_SECRET=apisix
```

The package publishes a full `config/kafkammt.php` (laravel-kafka compatible keys + `kafkammt.rbac.*`),
so Kafka connectivity and RBAC consumer behavior are managed from the same file.

### 5) Run consumer

```bash
php artisan rbac:consume-snapshots
```

This command always subscribes `iam.rbac.snapshots.v1` and will additionally subscribe
to any topics configured in `kafkammt.rbac.consumer.handlers`.

## Multi-topic handlers (custom microservice logic)

In your microservice, implement handlers that process business logic for a topic:

```php
<?php

namespace App\Kafka\Handlers;

use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rbac\Kafka\Contracts\TopicMessageHandlerInterface;

final class AuthEventsTopicHandler implements TopicMessageHandlerInterface
{
    public function topic(): string
    {
        return 'auth.events.v1';
    }

    public function handle(ConsumerMessage $message): void
    {
        // Your business logic here.
    }
}
```

Register topic => handler class in published `config/kafkammt.php`:

```php
'rbac' => [
    'consumer' => [
        // ...
        'handlers' => [
            'auth.events.v1' => \App\Kafka\Handlers\AuthEventsTopicHandler::class,
        ],
    ],
],
```

## Publish events from business logic

Inject `Mmtech\Rbac\Kafka\KafkaEventPublisher` and publish to any topic:

```php
$publisher->publish(
    topic: 'notifications.email.v1',
    payload: ['event' => 'welcome-email', 'user_id' => $userId],
    key: $userId
);
```

## Route usage

```php
Route::middleware(['rbac.auth.user', 'rbac.bind.gateway.user', 'can:orders.read'])
    ->get('/orders', OrdersController::class);
```

