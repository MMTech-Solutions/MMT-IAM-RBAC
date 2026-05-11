<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Kafka;

use Mmtech\Rbac\Kafka\ContentTypeSerializationDetector;
use PHPUnit\Framework\TestCase;

final class ContentTypeSerializationDetectorTest extends TestCase
{
    public function test_it_detects_avro_for_content_type_header(): void
    {
        $detector = new ContentTypeSerializationDetector;

        self::assertTrue($detector->isAvro(['content_type' => 'application/avro']));
        self::assertTrue($detector->isAvro(['Content-Type' => 'APPLICATION/AVRO']));
    }

    public function test_it_treats_missing_or_other_content_type_as_not_avro(): void
    {
        $detector = new ContentTypeSerializationDetector;

        self::assertFalse($detector->isAvro(null));
        self::assertFalse($detector->isAvro([]));
        self::assertFalse($detector->isAvro(['content_type' => 'application/json']));
        self::assertFalse($detector->isAvro(['other' => 'application/avro']));
    }

    public function test_it_reads_first_string_from_array_header_value(): void
    {
        $detector = new ContentTypeSerializationDetector;

        self::assertSame(
            'application/avro',
            $detector->contentTypeHeaderValue(['content_type' => ['application/avro', 'ignored']])
        );
    }
}
