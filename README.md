# MMT IAM RBAC package

Portable RBAC package for Laravel microservices.

## What it provides

- Permission checks by gateway `sub` with `request()->user()->can('permission.slug')`
- Kafka snapshot consumer (`iam.rbac.snapshots.v1`) always enabled in the command worker
- Reusable Kafka publisher service to emit events to any topic
- Multi-topic consumer with per-topic handlers (class-map)
- Local materialized store in database (`rcab_user_permission_snapshots`)
- IAM fallback endpoint support when local snapshot is missing

## Installation in a Laravel microservice

### 1) Require package (private repository)

In the microservice install:

```bash
composer require mmtech/iam-rbac:^1.0
```

### 2) Publish package files

```bash
php artisan vendor:publish --tag=rcab-config
php artisan vendor:publish --tag=rcab-migrations
php artisan migrate --no-interaction
```

### 3) Register middleware aliases

In `bootstrap/app.php`:

```php
$middleware->alias([
    'rcab.auth.user' => \Mmtech\Rcab\Http\Middleware\ResolveGatewayUserInfo::class,
    'rcab.bind.gateway.user' => \Mmtech\Rcab\Http\Middleware\BindGatewayUserToAuth::class,
]);
```

### 4) Configure env

```dotenv
RCAB_KAFKA_ENABLED=true
RCAB_KAFKA_BROKERS=kafka.mmtech-solutions.com:9092
RCAB_KAFKA_GROUP_ID=rcab-materializer
RCAB_KAFKA_ON_UNHANDLED_TOPIC=skip

RCAB_IAM_FALLBACK_ENABLED=true
RCAB_IAM_BASE_URL=http://iam-service
RCAB_IAM_INTERNAL_TOKEN=secret
RCAB_IAM_TIMEOUT_MS=1500

RCAB_FAIL_MODE=deny
RCAB_STRICT_DENY=true
RCAB_GATEWAY_INTERNAL_SECRET=apisix
```

### 5) Run consumer

```bash
php artisan rcab:consume-snapshots
```

This command always subscribes `iam.rbac.snapshots.v1` and will additionally subscribe
to any topics configured in `rcab.kafka.handlers`.

## Multi-topic handlers (custom microservice logic)

In your microservice, implement handlers that process business logic for a topic:

```php
<?php

namespace App\Kafka\Handlers;

use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rcab\Kafka\Contracts\TopicMessageHandlerInterface;

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

Register topic => handler class in published `config/rcab.php`:

```php
'kafka' => [
    // ...
    'handlers' => [
        'auth.events.v1' => \App\Kafka\Handlers\AuthEventsTopicHandler::class,
    ],
],
```

## Publish events from business logic

Inject `Mmtech\Rcab\Kafka\KafkaEventPublisher` and publish to any topic:

```php
$publisher->publish(
    topic: 'notifications.email.v1',
    payload: ['event' => 'welcome-email', 'user_id' => $userId],
    key: $userId
);
```

## Route usage

```php
Route::middleware(['rcab.auth.user', 'rcab.bind.gateway.user', 'can:orders.read'])
    ->get('/orders', OrdersController::class);
```

