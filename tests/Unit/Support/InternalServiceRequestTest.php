<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Support;

use Illuminate\Http\Request;
use Mmtech\Rbac\Support\InternalServiceRequest;
use Mmtech\Rbac\Tests\Support\RbacConfigTestCase;

final class InternalServiceRequestTest extends RbacConfigTestCase
{
    public function test_resolve_trusted_optional_skips_when_token_header_missing(): void
    {
        $request = Request::create('/api/test', 'GET');

        $this->assertNull(InternalServiceRequest::resolveTrusted($request, optional: true));
        $this->assertFalse(InternalServiceRequest::isTrusted($request));
    }

    public function test_resolve_trusted_marks_request_when_token_and_source_valid(): void
    {
        $request = Request::create('/api/test', 'GET', server: [
            'HTTP_X_INTERNAL_TOKEN' => 'test-internal-token',
            'HTTP_X_INTERNAL_SOURCE' => 'mmt-orders-service',
        ]);

        $this->assertNull(InternalServiceRequest::resolveTrusted($request, optional: true));
        $this->assertTrue(InternalServiceRequest::isTrusted($request));
        $this->assertSame('mmt-orders-service', InternalServiceRequest::source($request));
    }

    public function test_resolve_trusted_returns_forbidden_when_source_missing(): void
    {
        $request = Request::create('/api/test', 'GET', server: [
            'HTTP_X_INTERNAL_TOKEN' => 'test-internal-token',
        ]);

        $response = InternalServiceRequest::resolveTrusted($request, optional: true);

        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse(InternalServiceRequest::isTrusted($request));
    }

    public function test_resolve_trusted_returns_forbidden_when_token_invalid(): void
    {
        $request = Request::create('/api/test', 'GET', server: [
            'HTTP_X_INTERNAL_TOKEN' => 'wrong-token',
            'HTTP_X_INTERNAL_SOURCE' => 'mmt-orders-service',
        ]);

        $response = InternalServiceRequest::resolveTrusted($request, optional: true);

        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_resolve_trusted_required_returns_forbidden_when_token_missing(): void
    {
        $request = Request::create('/api/test', 'GET');

        $response = InternalServiceRequest::resolveTrusted($request, optional: false);

        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_resolve_trusted_returns_service_unavailable_when_token_not_configured(): void
    {
        $this->bootstrapRbacConfig(['rbac.internal.token' => '']);

        $request = Request::create('/api/test', 'GET', server: [
            'HTTP_X_INTERNAL_TOKEN' => 'any',
            'HTTP_X_INTERNAL_SOURCE' => 'mmt-orders-service',
        ]);

        $response = InternalServiceRequest::resolveTrusted($request, optional: true);

        $this->assertNotNull($response);
        $this->assertSame(503, $response->getStatusCode());
    }
}
