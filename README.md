# MMT IAM RBAC package

Portable RBAC package for Laravel microservices.

## What it provides

- Permission checks by gateway `sub` with `request()->user()->can('permission.slug')`
- Effective roles from the same snapshot with `request()->user()->rbacRoles()` / `rbacRole()` (or `request()->rbacRoles()`)
- Kafka snapshot consumer (`iam.rbac.snapshots.v1`) always enabled in the command worker
- Reusable Kafka publisher service to emit events to any topic
- Multi-topic consumer with per-topic handlers (class-map)
- Local materialized store in database (`rbac_user_permission_snapshots`) with permissions and per-surface roles (`id` + `name`)
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

### 3) Middleware aliases

The package registers these aliases automatically when `RbacServiceProvider` boots:

| Alias | Purpose |
|-------|---------|
| `rbac.trusted.internal` | Validate `X-Internal-Token` + `X-Internal-Source` when present |
| `rbac.internal.token` | Require valid internal credentials (internal-only routes) |
| `rbac.auth.user` | Validate gateway headers (skipped when trusted internal) |
| `rbac.auth.user.info` | Fetch full IAM user profile and merge into `gateway_auth_user_info` |
| `rbac.bind.gateway.user` | Bind `GatewayUser` (skipped when trusted internal) |
| `rbac.authorize.or.internal` | `Gate` check or bypass for trusted internal (`:ability`) |

You may still declare custom names in `bootstrap/app.php` if needed (e.g. map `auth.user` to `ResolveGatewayUserInfo`).

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

RBAC_INTERNAL_TOKEN=shared-secret-between-ms
RBAC_INTERNAL_CALLER_SOURCE=mmt-orders-service
# RBAC_INTERNAL_LOG_TRUSTED=true

RBAC_IAM_USER_ENRICH_ENABLED=true
RBAC_IAM_BASE_URL=http://iam-service
# RBAC_IAM_USER_FAIL_OPEN=true
# RBAC_IAM_USER_LOG_FAILURES=false
```

The package publishes `config/rbac.php` and also publishes `config/kafka.php`
from `mateusjunges/laravel-kafka` in the same `rbac-config` tag.
This keeps Kafka connection config and RBAC module config clearly separated.

### 5) Run consumer

```bash
php artisan rbac:consume-snapshots
```

By default, the command first performs an **initial sync** (consume until last available
message in Kafka for the consumer group) and then stays running to process future events.
It always subscribes `iam.rbac.snapshots.v1` and will additionally subscribe to any topics
configured in `rbac.consumer.handlers`.

Optional flags:

- `--skip-initial-sync`: starts directly in continuous consume mode.
- `--stop-after-last-message`: run one catch-up pass and stop.

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

Register topic => handler class in published `config/rbac.php`:

```php
'consumer' => [
    // ...
    'handlers' => [
        'auth.events.v1' => \App\Kafka\Handlers\AuthEventsTopicHandler::class,
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

## IAM user profile enrichment (`rbac.auth.user.info`)

After `rbac.auth.user` decodes gateway `X-Userinfo`, this middleware calls MMT-AUTH-SERVICE
(`GET /api/iam/v1/rbac/admin/users/{uuid}` by default) using internal MS headers
(`X-Internal-Token`, `X-Internal-Source`), merges the IAM `data` payload into
`gateway_auth_user_info`, and `rbac.bind.gateway.user` exposes it on `auth()->user()`
as `GatewayUser` (`gatewayUserInfo`, magic properties like `country_id`, `email`).

**Middleware order:**

```php
Route::middleware([
    'rbac.auth.user',
    'rbac.auth.user.info',
    'rbac.bind.gateway.user',
    'can:orders.read',
])->group(...);
```

```php
$user = auth()->user(); // Mmtech\Rbac\Auth\GatewayUser
$countryId = $user->country_id; // from IAM profile
$profile = $user->gatewayUserInfo; // gateway JWT + IAM fields
```

If IAM is unreachable, `RBAC_IAM_USER_FAIL_OPEN=true` (default) keeps gateway-only claims;
when `false`, the middleware responds with **502**.

## Internal service-to-service auth

Microservices can call each other **without gateway user headers** using a shared secret and a caller identity for access logs.

**Headers (outbound from this MS):**

```http
X-Internal-Token: <RBAC_INTERNAL_TOKEN>
X-Internal-Source: <RBAC_INTERNAL_CALLER_SOURCE>
```

`IamFallbackClient` sends the same headers when calling IAM internal RBAC endpoints.

**Route patterns** — always put `rbac.trusted.internal` first:

```php
// Gateway user OR trusted internal MS (e.g. admin APIs)
Route::middleware([
    'rbac.trusted.internal',
    'rbac.auth.user',
    'rbac.bind.gateway.user',
    'rbac.authorize.or.internal:rbac.manage',
])->group(...);

// Internal-only (rebuild, effective permissions, etc.)
Route::middleware(['rbac.trusted.internal', 'rbac.internal.token'])->group(...);
```

When `X-Internal-Token` is sent, `X-Internal-Source` is **required** (empty or missing → 403).

**Request helpers for logging:**

```php
if (request()->isTrustedInternalServiceRequest()) {
    $caller = request()->internalServiceSource(); // e.g. mmt-orders-service
}
```

Laravel’s built-in `can:foo` middleware does **not** bypass for internal calls. Use `rbac.authorize.or.internal:foo` instead.

## Checking permissions with `can()`

The package registers a **global `Gate::before`** (`RbacModule`) so any `can('permission.slug')` call is resolved against the **materialized snapshot** (and IAM fallback when configured), not against Spatie models in this service.

**Requirements**

1. Run `rbac:consume-snapshots` (or otherwise have rows in `rbac_user_permission_snapshots`) so permissions exist for the user’s `sub` and surface.
2. On HTTP routes, use the gateway stack **in order** (and `rbac.trusted.internal` first when supporting MS-to-MS): validate gateway headers, enrich IAM profile when needed (`rbac.auth.user.info`), bind the user, then authorize.

**Surface** is chosen the same way for every check: `SurfaceResolver` uses `config('rbac.surface.default')` when set; otherwise URLs whose path contains `/admin` use `admin_panel`, everything else `customer_app`.

### Route middleware

Apply the middleware aliases, then Laravel’s `can:` middleware. The user must be a `GatewayUser` (after `rbac.bind.gateway.user`).

```php
use Illuminate\Support\Facades\Route;

Route::middleware(['rbac.auth.user', 'rbac.auth.user.info', 'rbac.bind.gateway.user', 'can:orders.read'])
    ->get('/orders', [OrdersController::class, 'index']);
```

If the snapshot does not include `orders.read` for that user and surface, Laravel returns **403**. With `rbac.auth.strict_deny` enabled (default), unknown abilities are denied here instead of falling through to other gates.

### In a controller or action

Use the authenticated user (or `Gate`) like any Laravel app; the package intercepts the ability name:

```php
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

public function index(Request $request): JsonResponse
{
    abort_unless($request->user()->can('orders.read'), 403);

    return response()->json(['ok' => true]);
}
```

Equivalent checks:

```php
auth()->user()->can('orders.read');
Gate::forUser($request->user())->allows('orders.read');
$this->authorize('orders.read'); // in a `Controller` using `AuthorizesRequests`
```

### Programmatic check by `sub` (no HTTP user)

```php
use Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface;

$allowed = app(PermissionCheckerInterface::class)->userCan(
    $sub,
    'orders.read',
    'customer_app'
);
```

If you omit the third argument, the checker uses `config('rbac.surface.default')` or falls back to `customer_app`; it does **not** inspect the URL path (unlike `Gate` during an HTTP request, which uses `SurfaceResolver`). Pass the surface explicitly when mirroring HTTP behavior from jobs or CLI.

```php
use Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface;
use Mmtech\Rbac\Support\SurfaceResolver;

$allowed = app(PermissionCheckerInterface::class)->userCan(
    $sub,
    'orders.read',
    SurfaceResolver::resolve($request)
);
```

## Reading effective roles

After `rbac.bind.gateway.user`, the authenticated user is a `Mmtech\Rbac\Auth\GatewayUser`. Roles come from the same materialized snapshot (and IAM fallback) as `can()`, using the current request surface (`SurfaceResolver`).

```php
$roles = auth()->user()->rbacRoles(); // list<array{id: string, name: string}>
$first = auth()->user()->rbacRole();  // first entry or null

// Optional explicit surface (otherwise same as Gate / SurfaceResolver for this request):
$rolesAdmin = auth()->user()->rbacRoles('admin_panel');

// Request helpers (when user is GatewayUser):
$roles = request()->rbacRoles();
$first = request()->rbacRole();
```

You can also resolve roles by `sub` without a gateway user: `app(\Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface::class)->userRoles($sub, $surface)`.
