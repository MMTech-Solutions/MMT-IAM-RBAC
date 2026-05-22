<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Mmtech\Rbac\Http\Middleware\ResolveTrustedInternalServiceRequest;
use Mmtech\Rbac\Support\InternalServiceRequest;
use Mmtech\Rbac\Tests\Support\RbacConfigTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTrustedInternalServiceRequestTest extends RbacConfigTestCase
{
    public function test_passes_through_without_internal_headers(): void
    {
        $middleware = new ResolveTrustedInternalServiceRequest;
        $request = Request::create('/api/test', 'GET');

        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse(InternalServiceRequest::isTrusted($request));
    }

    public function test_marks_trusted_when_credentials_valid(): void
    {
        $middleware = new ResolveTrustedInternalServiceRequest;
        $request = Request::create('/api/test', 'GET', server: [
            'HTTP_X_INTERNAL_TOKEN' => 'test-internal-token',
            'HTTP_X_INTERNAL_SOURCE' => 'mmt-billing-service',
        ]);

        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(InternalServiceRequest::isTrusted($request));
        $this->assertSame('mmt-billing-service', InternalServiceRequest::source($request));
    }

    public function test_returns_forbidden_when_source_missing(): void
    {
        $middleware = new ResolveTrustedInternalServiceRequest;
        $request = Request::create('/api/test', 'GET', server: [
            'HTTP_X_INTERNAL_TOKEN' => 'test-internal-token',
        ]);

        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'));

        $this->assertSame(403, $response->getStatusCode());
    }
}
