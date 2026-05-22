<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Http\Middleware;

use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Http\Request;
use Mmtech\Rbac\Http\Middleware\AuthorizeAbilityOrInternal;
use Mmtech\Rbac\Support\InternalServiceRequest;
use Mmtech\Rbac\Tests\Support\RbacConfigTestCase;
use Mmtech\Rbac\Tests\Support\StubGate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeAbilityOrInternalTest extends RbacConfigTestCase
{
    public function test_allows_trusted_internal_request_without_gate_check(): void
    {
        $middleware = new AuthorizeAbilityOrInternal;
        $request = Request::create('/api/admin', 'GET');
        InternalServiceRequest::markTrusted($request, 'mmt-orders-service');

        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'), 'rbac.manage');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_allows_when_gate_allows_ability(): void
    {
        $this->bindGateStub(allows: true);

        $middleware = new AuthorizeAbilityOrInternal;
        $request = Request::create('/api/orders', 'GET');

        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'), 'orders.read');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_not_trusted_and_gate_denies(): void
    {
        $this->bindGateStub(allows: false);

        $middleware = new AuthorizeAbilityOrInternal;
        $request = Request::create('/api/orders', 'GET');

        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'), 'orders.read');

        $this->assertSame(403, $response->getStatusCode());
    }

    private function bindGateStub(bool $allows): void
    {
        $this->getContainer()->instance(
            GateContract::class,
            new StubGate($allows, 'orders.read')
        );
    }

    private function getContainer(): \Illuminate\Container\Container
    {
        /** @var \Illuminate\Container\Container $container */
        $container = \Illuminate\Container\Container::getInstance();

        return $container;
    }
}
