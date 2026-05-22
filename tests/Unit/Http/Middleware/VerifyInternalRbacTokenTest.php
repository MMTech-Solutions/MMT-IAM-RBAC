<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Mmtech\Rbac\Http\Middleware\VerifyInternalRbacToken;
use Mmtech\Rbac\Support\InternalServiceRequest;
use Mmtech\Rbac\Tests\Support\RbacConfigTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class VerifyInternalRbacTokenTest extends RbacConfigTestCase
{
    public function test_passes_when_already_trusted(): void
    {
        $middleware = new VerifyInternalRbacToken;
        $request = Request::create('/api/internal', 'POST');
        InternalServiceRequest::markTrusted($request, 'mmt-auth-service');

        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_requires_valid_credentials_when_not_trusted(): void
    {
        $middleware = new VerifyInternalRbacToken;
        $request = Request::create('/api/internal', 'POST', server: [
            'HTTP_X_INTERNAL_TOKEN' => 'test-internal-token',
            'HTTP_X_INTERNAL_SOURCE' => 'mmt-orders-service',
        ]);

        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(InternalServiceRequest::isTrusted($request));
    }

    public function test_returns_forbidden_without_credentials(): void
    {
        $middleware = new VerifyInternalRbacToken;
        $request = Request::create('/api/internal', 'POST');

        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'));

        $this->assertSame(403, $response->getStatusCode());
    }
}
