<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Storage;

use Illuminate\Support\Facades\DB;
use Mmtech\Rbac\Authorization\Contracts\SnapshotStoreInterface;
use Mmtech\Rbac\Kafka\RbacSnapshotMessage;
use Mmtech\Rbac\Kafka\RbacSnapshotRoleListNormalizer;

final class DatabaseSnapshotStore implements SnapshotStoreInterface
{
    public function getSnapshot(string $sub, string $surface): ?RbacSnapshotMessage
    {
        $row = DB::table($this->table())
            ->where('sub', $sub)
            ->where('surface', $surface)
            ->first();

        if ($row === null) {
            return null;
        }

        $permissions = json_decode((string) $row->permissions, true);
        if (! is_array($permissions)) {
            $permissions = [];
        }

        /** @var list<string> $normalizedPermissions */
        $normalizedPermissions = array_values(array_filter(
            $permissions,
            static fn ($permission): bool => is_string($permission) && trim($permission) !== ''
        ));

        $rolesDecoded = json_decode((string) ($row->roles ?? '[]'), true);
        $normalizedRoles = RbacSnapshotRoleListNormalizer::fromMixed($rolesDecoded);

        $rev = (int) $row->rev;
        if ($rev < 0) {
            return null;
        }

        return RbacSnapshotMessage::snapshot(
            messageKey: (string) $row->message_key,
            sub: (string) $row->sub,
            surface: (string) $row->surface,
            rev: $rev,
            permissions: $normalizedPermissions,
            updatedAt: $row->snapshot_updated_at !== null ? (string) $row->snapshot_updated_at : null,
            roles: $normalizedRoles
        );
    }

    public function upsertSnapshot(RbacSnapshotMessage $snapshot): void
    {
        if ($snapshot->isTombstone || $snapshot->rev === null || $snapshot->permissions === null || $snapshot->roles === null) {
            return;
        }

        DB::transaction(function () use ($snapshot): void {
            $existing = DB::table($this->table())
                ->where('sub', $snapshot->sub)
                ->where('surface', $snapshot->surface)
                ->lockForUpdate()
                ->first();

            $payload = [
                'message_key' => $snapshot->messageKey,
                'sub' => $snapshot->sub,
                'surface' => $snapshot->surface,
                'rev' => $snapshot->rev,
                'permissions' => json_encode($snapshot->permissions, JSON_THROW_ON_ERROR),
                'roles' => json_encode($snapshot->roles, JSON_THROW_ON_ERROR),
                'snapshot_updated_at' => $snapshot->updatedAt,
                'updated_at' => now(),
            ];

            if ($existing === null) {
                DB::table($this->table())->insert(array_merge($payload, [
                    'created_at' => now(),
                ]));

                return;
            }

            $existingRev = (int) $existing->rev;
            if ($snapshot->rev < $existingRev) {
                return;
            }

            DB::table($this->table())
                ->where('id', $existing->id)
                ->update($payload);
        });
    }

    public function deleteSnapshot(string $sub, string $surface): void
    {
        DB::table($this->table())
            ->where('sub', $sub)
            ->where('surface', $surface)
            ->delete();
    }

    private function table(): string
    {
        return (string) config('rbac.store.table', 'rbac_user_permission_snapshots');
    }
}

