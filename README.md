# MMT IAM RBAC package

Portable RBAC package for Laravel microservices.

## What it provides

- Permission checks by gateway `sub` with `request()->user()->can('permission.slug')`
- Kafka snapshot consumer (`iam.rbac.snapshots.v1`)
- Local materialized store in database (`rcab_user_permission_snapshots`)
- IAM fallback endpoint support when local snapshot is missing

## Installation in a Laravel microservice

### 1) Require package (private repository)

In the microservice `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:mmtech/MMT-IAM-RBAC.git"
    }
  ]
}
```

Then install:

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
RCAB_KAFKA_TOPIC=iam.rbac.snapshots.v1
RCAB_KAFKA_GROUP_ID=rcab-materializer

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

## Route usage

```php
Route::middleware(['rcab.auth.user', 'rcab.bind.gateway.user', 'can:orders.read'])
    ->get('/orders', OrdersController::class);
```

