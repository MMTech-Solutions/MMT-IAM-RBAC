<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Auth;

use Mmtech\Rbac\Auth\GatewayUser;
use PHPUnit\Framework\TestCase;

final class GatewayUserProfileAccessTest extends TestCase
{
    public function test_magic_get_reads_merged_profile_fields(): void
    {
        $user = new GatewayUser('uuid-1', [
            'sub' => 'uuid-1',
            'email' => 'a@example.com',
            'country_id' => 840,
        ]);

        $this->assertSame('a@example.com', $user->email);
        $this->assertSame(840, $user->country_id);
        $this->assertNull($user->missing_key);
        $this->assertSame(840, $user->profileValue('country_id'));
    }
}
