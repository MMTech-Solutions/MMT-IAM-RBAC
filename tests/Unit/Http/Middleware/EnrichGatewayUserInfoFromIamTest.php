<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Http\Middleware;

use Illuminate\Support\Facades\Http;
use Mmtech\Rbac\Authorization\IamUserProfileClient;
use Mmtech\Rbac\Http\Middleware\EnrichGatewayUserInfoFromIam;
use Mmtech\Rbac\Tests\Support\RbacConfigTestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class EnrichGatewayUserInfoFromIamTest extends RbacConfigTestCase
{
    public function test_merges_iam_profile_into_gateway_auth_user_info(): void
    {
        $userUuid = 'fb37346e-9b8b-4955-bc84-607c6429632f';
        $client = new IamUserProfileClient;
        $url = $client->userUrl($userUuid);

        Http::fake([
            $url => Http::response([
                'success' => true,
                'data' => [
                    'id' => $userUuid,
                    'country_id' => 840,
                    'name' => 'Gateway User',
                ],
            ], 200),
        ]);

        $request = Request::create('/api/orders', 'GET');
        $request->attributes->set('gateway_auth_user_info', [
            'sub' => $userUuid,
            'email' => 'gateway-user@example.com',
        ]);

        $captured = [];
        $middleware = new EnrichGatewayUserInfoFromIam($client);
        $response = $middleware->handle($request, function (Request $req) use (&$captured): Response {
            $captured = $req->attributes->get('gateway_auth_user_info', []);

            return new JsonResponse('ok');
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($userUuid, $captured['sub']);
        $this->assertSame('gateway-user@example.com', $captured['email']);
        $this->assertSame(840, $captured['country_id']);
        $this->assertSame('Gateway User', $captured['name']);
    }

    public function test_fail_open_continues_with_gateway_only_when_iam_fails(): void
    {
        $this->bootstrapRbacConfig([
            'rbac.iam_user.fail_open' => true,
        ]);

        $userUuid = 'fb37346e-9b8b-4955-bc84-607c6429632f';
        $client = new IamUserProfileClient;
        $url = $client->userUrl($userUuid);

        Http::fake([$url => Http::response([], 500)]);

        $request = Request::create('/api/orders', 'GET');
        $gatewayOnly = [
            'sub' => $userUuid,
            'email' => 'gateway-user@example.com',
        ];
        $request->attributes->set('gateway_auth_user_info', $gatewayOnly);

        $middleware = new EnrichGatewayUserInfoFromIam($client);

        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($gatewayOnly, $request->attributes->get('gateway_auth_user_info'));
    }

    public function test_returns_502_when_fail_open_disabled_and_iam_fails(): void
    {
        $this->bootstrapRbacConfig([
            'rbac.iam_user.fail_open' => false,
        ]);

        $userUuid = 'fb37346e-9b8b-4955-bc84-607c6429632f';
        $client = new IamUserProfileClient;
        Http::fake([$client->userUrl($userUuid) => Http::response([], 500)]);

        $request = Request::create('/api/orders', 'GET');
        $request->attributes->set('gateway_auth_user_info', ['sub' => $userUuid]);

        $middleware = new EnrichGatewayUserInfoFromIam($client);
        $response = $middleware->handle($request, static fn (): Response => new JsonResponse('ok'));

        $this->assertSame(502, $response->getStatusCode());
    }
}
