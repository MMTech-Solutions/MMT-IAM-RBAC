<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Kafka;

final class ContentTypeSerializationDetector
{
    private const AVRO_CONTENT_TYPE = 'application/avro';

    /**
     * @param  array<string, mixed>|null  $headers
     */
    public function isAvro(?array $headers): bool
    {
        $value = $this->contentTypeHeaderValue($headers);
        if ($value === null || $value === '') {
            return false;
        }

        return strcasecmp(trim($value), self::AVRO_CONTENT_TYPE) === 0;
    }

    /**
     * @param  array<string, mixed>|null  $headers
     */
    public function contentTypeHeaderValue(?array $headers): ?string
    {
        if ($headers === null || $headers === []) {
            return null;
        }

        foreach ($headers as $name => $value) {
            $lower = strtolower((string) $name);
            if ($lower !== 'content_type' && $lower !== 'content-type') {
                continue;
            }

            return $this->normalizeHeaderScalar($value);
        }

        return null;
    }

    private function normalizeHeaderScalar(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value) && $value !== []) {
            $first = reset($value);

            return is_string($first) ? $first : null;
        }

        return null;
    }
}
