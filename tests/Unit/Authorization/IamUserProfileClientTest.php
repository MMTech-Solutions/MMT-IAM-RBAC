<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Authorization;

use Illuminate\Support\Facades\Http;
use Mmtech\Rbac\Authorization\IamUserProfileClient;
use Mmtech\Rbac\Tests\Support\RbacConfigTestCase;

final class IamUserProfileClientTest extends RbacConfigTestCase
{
    public function test_fetch_by_sub_returns_merged_data_payload(): void
    {
        $userUuid = 'fb37346e-9b8b-4955-bc84-607c6429632f';
        $url = (new IamUserProfileClient)->userUrl($userUuid);

        Http::fake([
            $url => Http::response([
                'success' => true,
                'message' => 'ok',
                'data' => [
                    'id' => $userUuid,
                    'country_id' => 840,
                    'name' => 'Gateway User',
                ],
            ], 200),
        ]);

        $profile = (new IamUserProfileClient)->fetchBySub($userUuid);

        $this->assertIsArray($profile);
        $this->assertSame($userUuid, $profile['id']);
        $this->assertSame(840, $profile['country_id']);

        Http::assertSent(function ($request) use ($url): bool {
            return $request->url() === $url
                && $request->method() === 'GET'
                && $request->hasHeader('X-Internal-Token', 'test-internal-token')
                && $request->hasHeader('X-Internal-Source', 'mmt-test-service');
        });
    }

    public function test_fetch_by_sub_returns_null_on_404(): void
    {
        $userUuid = '00000000-0000-0000-0000-000000000000';
        $url = (new IamUserProfileClient)->userUrl($userUuid);

        Http::fake([$url => Http::response([], 404)]);

        $this->assertNull((new IamUserProfileClient)->fetchBySub($userUuid));
    }
}
