<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Support;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class RbacConfigTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapRbacConfig();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Container::setInstance(null);
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function bootstrapRbacConfig(array $overrides = []): void
    {
        $defaults = [
            'rbac.internal.token' => 'test-internal-token',
            'rbac.internal.token_header' => 'X-Internal-Token',
            'rbac.internal.source_header' => 'X-Internal-Source',
            'rbac.internal.caller_source' => 'mmt-test-service',
            'rbac.internal.log_trusted_requests' => false,
            'rbac.gateway.internal_header' => 'X-Internal-Gateway',
            'rbac.gateway.internal_secret' => 'apisix',
            'rbac.gateway.userinfo_header' => 'X-Userinfo',
            'rbac.gateway.log_missing_headers' => false,
            'rbac.auth.guard' => 'web',
            'rbac.fallback.base_url' => 'http://iam-service.test',
            'rbac.iam_user.enabled' => true,
            'rbac.iam_user.base_url' => 'http://iam-service.test',
            'rbac.iam_user.path' => '/api/iam/v1/rbac/admin/users',
            'rbac.iam_user.timeout_ms' => 1500,
            'rbac.iam_user.fail_open' => true,
            'rbac.iam_user.log_failures' => false,
        ];

        $config = new Repository(array_merge($defaults, $overrides));
        $app = new Container;
        $app->instance('config', $config);
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Config::setFacadeApplication($app);

        $app->singleton('Illuminate\Contracts\Routing\ResponseFactory', static function () {
            return new class
            {
                public function json(mixed $data = [], int $status = 200, array $headers = [], int $options = 0): JsonResponse
                {
                    return new JsonResponse($data, $status, $headers, $options);
                }
            };
        });

        Gate::setFacadeApplication($app);
        Http::setFacadeApplication($app);
    }
}
