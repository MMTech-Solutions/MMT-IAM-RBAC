<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Authorization;

use Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface;
use Mmtech\Rbac\Authorization\Contracts\SnapshotStoreInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

final class RbacPermissionChecker implements PermissionCheckerInterface
{
    /**
     * @var array<string, array{permissions: list<string>, rev: int}>
     */
    private array $cache = [];

    public function __construct(
        private readonly SnapshotStoreInterface $snapshotStore,
        private readonly IamFallbackClient $iamFallbackClient
    ) {}

    public function userCan(string $sub, string $ability, ?string $surface = null): bool
    {
        $normalizedSurface = $this->normalizeSurface($surface);
        $cacheKey = $sub.'|'.$normalizedSurface;

        if (isset($this->cache[$cacheKey])) {
            return in_array($ability, $this->cache[$cacheKey]['permissions'], true);
        }

        try {
            $snapshot = $this->snapshotStore->getSnapshot($sub, $normalizedSurface);
            if ($snapshot === null) {
                $snapshot = $this->iamFallbackClient->fetchSnapshot($sub, $normalizedSurface);
                if ($snapshot !== null) {
                    $this->snapshotStore->upsertSnapshot($snapshot);
                }
            }

            if ($snapshot === null || $snapshot->permissions === null || $snapshot->isTombstone) {
                return false;
            }

            $this->cache[$cacheKey] = [
                'permissions' => $snapshot->permissions,
                'rev' => $snapshot->rev ?? 0,
            ];

            return in_array($ability, $snapshot->permissions, true);
        } catch (Throwable $e) {
            $failMode = (string) config('rbac.auth.fail_mode', 'deny');
            if ($failMode === 'service_unavailable') {
                throw new ServiceUnavailableHttpException(null, 'RBAC service unavailable', $e);
            }

            return false;
        }
    }

    private function normalizeSurface(?string $surface): string
    {
        if ($surface !== null && trim($surface) !== '') {
            return trim($surface);
        }

        $configured = config('rbac.surface.default');
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return 'customer_app';
    }
}

