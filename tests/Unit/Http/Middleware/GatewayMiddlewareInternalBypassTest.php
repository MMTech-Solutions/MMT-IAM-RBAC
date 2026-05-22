<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Mmtech\Rbac\Http\Middleware\BindGatewayUserToAuth;
use Mmtech\Rbac\Http\Middleware\ResolveGatewayUserInfo;
use Mmtech\Rbac\Support\InternalServiceRequest;
use Mmtech\Rbac\Tests\Support\RbacConfigTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class GatewayMiddlewareInternalBypassTest extends RbacConfigTestCase
{
    public function test_resolve_gateway_user_info_skips_gateway_validation_when_trusted(): void
    {
        $middleware = new ResolveGatewayUserInfo;
        $request = Request::create('/api/admin', 'GET');
        InternalServiceRequest::markTrusted($request, 'mmt-orders-service');

        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($request->attributes->get('gateway_auth_user_info'));
    }

    public function test_bind_gateway_user_skips_auth_when_trusted(): void
    {
        $middleware = new BindGatewayUserToAuth;
        $request = Request::create('/api/admin', 'GET');
        InternalServiceRequest::markTrusted($request, 'mmt-orders-service');

        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_bind_gateway_user_returns_unauthenticated_without_gateway_user(): void
    {
        $middleware = new BindGatewayUserToAuth;
        $request = Request::create('/api/admin', 'GET');

        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'));

        $this->assertSame(401, $response->getStatusCode());
    }
}
