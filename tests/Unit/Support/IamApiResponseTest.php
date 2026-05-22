<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Support;

use Mmtech\Rbac\Support\IamApiResponse;
use PHPUnit\Framework\TestCase;

final class IamApiResponseTest extends TestCase
{
    public function test_extract_data_payload_from_envelope(): void
    {
        $payload = IamApiResponse::extractDataPayload([
            'success' => true,
            'message' => 'ok',
            'data' => ['id' => 'uuid-1', 'name' => 'Jane'],
        ]);

        $this->assertSame(['id' => 'uuid-1', 'name' => 'Jane'], $payload);
    }

    public function test_extract_data_payload_returns_empty_for_invalid_input(): void
    {
        $this->assertSame([], IamApiResponse::extractDataPayload(null));
    }
}
