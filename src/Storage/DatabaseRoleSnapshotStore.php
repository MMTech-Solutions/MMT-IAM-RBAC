<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Storage;

use Illuminate\Support\Facades\DB;
use Mmtech\Rbac\Authorization\Contracts\RoleSnapshotStoreInterface;
use Mmtech\Rbac\Kafka\RbacRoleSnapshotMessage;

final class DatabaseRoleSnapshotStore implements RoleSnapshotStoreInterface
{
    public function getPermissions(string $roleId, string $surface): ?array
    {
        $row = DB::table($this->table())
            ->where('role_id', $roleId)
            ->where('surface', $surface)
            ->first();

        if ($row === null) {
            return null;
        }

        $rev = (int) $row->rev;
        if ($rev < 0) {
            return null;
        }

        $permissions = json_decode((string) $row->permissions, true);
        if (! is_array($permissions)) {
            return [];
        }

        /** @var list<string> $normalized */
        $normalized = array_values(array_filter(
            $permissions,
            static fn ($permission): bool => is_string($permission) && trim($permission) !== ''
        ));

        return $normalized;
    }

    public function upsertSnapshot(RbacRoleSnapshotMessage $snapshot): void
    {
        if ($snapshot->isTombstone || $snapshot->rev === null || $snapshot->permissions === null) {
            return;
        }

        DB::transaction(function () use ($snapshot): void {
            $existing = DB::table($this->table())
                ->where('role_id', $snapshot->roleId)
                ->where('surface', $snapshot->surface)
                ->lockForUpdate()
                ->first();

            $payload = [
                'message_key' => $snapshot->messageKey,
                'role_id' => $snapshot->roleId,
                'surface' => $snapshot->surface,
                'rev' => $snapshot->rev,
                'permissions' => json_encode($snapshot->permissions, JSON_THROW_ON_ERROR),
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

    public function deleteSnapshot(string $roleId, string $surface): void
    {
        DB::table($this->table())
            ->where('role_id', $roleId)
            ->where('surface', $surface)
            ->delete();
    }

    private function table(): string
    {
        return (string) config('rbac.store.role_table', 'rbac_role_permission_snapshots');
    }
}
