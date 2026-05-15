<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Authorization;

use Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface;
use Mmtech\Rbac\Authorization\Contracts\SnapshotFallbackInterface;
use Mmtech\Rbac\Authorization\Contracts\SnapshotStoreInterface;
use Mmtech\Rbac\Kafka\RbacSnapshotMessage;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

final class RbacPermissionChecker implements PermissionCheckerInterface
{
    /**
     * @var array<string, RbacSnapshotMessage>
     */
    private array $snapshotCache = [];

    public function __construct(
        private readonly SnapshotStoreInterface $snapshotStore,
        private readonly SnapshotFallbackInterface $snapshotFallback
    ) {}

    public function userCan(string $sub, string $ability, ?string $surface = null): bool
    {
        $normalizedSurface = $this->normalizeSurface($surface);

        try {
            $snapshot = $this->resolveSnapshot($sub, $normalizedSurface);
            if ($snapshot === null) {
                return false;
            }

            return in_array($ability, $snapshot->permissions, true);
        } catch (Throwable $e) {
            $this->throwServiceUnavailableIfConfigured($e);

            return false;
        }
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function userRoles(string $sub, ?string $surface = null): array
    {
        $normalizedSurface = $this->normalizeSurface($surface);

        try {
            $snapshot = $this->resolveSnapshot($sub, $normalizedSurface);
            if ($snapshot === null || $snapshot->roles === null) {
                return [];
            }

            return $snapshot->roles;
        } catch (Throwable $e) {
            $this->throwServiceUnavailableIfConfigured($e);

            return [];
        }
    }

    private function resolveSnapshot(string $sub, string $normalizedSurface): ?RbacSnapshotMessage
    {
        $cacheKey = $sub.'|'.$normalizedSurface;

        if (isset($this->snapshotCache[$cacheKey])) {
            return $this->snapshotCache[$cacheKey];
        }

        $snapshot = $this->snapshotStore->getSnapshot($sub, $normalizedSurface);
        if ($snapshot === null) {
            $snapshot = $this->snapshotFallback->fetchSnapshot($sub, $normalizedSurface);
            if ($snapshot !== null) {
                $this->snapshotStore->upsertSnapshot($snapshot);
            }
        }

        if ($snapshot === null || $snapshot->isTombstone || $snapshot->permissions === null) {
            return null;
        }

        $this->snapshotCache[$cacheKey] = $snapshot;

        return $snapshot;
    }

    private function throwServiceUnavailableIfConfigured(Throwable $e): void
    {
        $failMode = (string) config('rbac.auth.fail_mode', 'deny');
        if ($failMode === 'service_unavailable') {
            throw new ServiceUnavailableHttpException(null, 'RBAC service unavailable', $e);
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
