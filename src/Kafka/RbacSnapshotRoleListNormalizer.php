<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Kafka;

/**
 * Normalizes the Kafka / HTTP `roles` payload into a stable list of id + name.
 *
 * Accepts legacy string-only entries (schema v2) and object entries (schema v3+).
 */
final class RbacSnapshotRoleListNormalizer
{
    /**
     * @return list<array{id: string, name: string}>
     */
    public static function fromMixed(mixed $rolesRaw): array
    {
        if (! is_array($rolesRaw)) {
            return [];
        }

        $out = [];
        foreach ($rolesRaw as $item) {
            if (is_string($item)) {
                $name = trim($item);
                if ($name !== '') {
                    $out[] = ['id' => '', 'name' => $name];
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $id = isset($item['id']) && is_string($item['id']) ? trim($item['id']) : '';
            $name = isset($item['name']) && is_string($item['name']) ? trim($item['name']) : '';
            if ($id === '' && $name === '') {
                continue;
            }

            $out[] = ['id' => $id, 'name' => $name];
        }

        return array_values($out);
    }
}
