<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Kafka;

use Junges\Kafka\Message\Deserializers\JsonDeserializer;
use Mmtech\Rbac\Kafka\ContentTypeSerializationDetector;
use Mmtech\Rbac\Kafka\HeaderAwareMessageDeserializer;
use Mmtech\Rbac\Kafka\RbacKafkaAvroCodec;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HeaderAwareMessageDeserializerTest extends TestCase
{
    public function test_null_body_is_routed_through_json_deserializer(): void
    {
        $deserializer = new HeaderAwareMessageDeserializer(
            new JsonDeserializer,
            new ContentTypeSerializationDetector,
            null,
        );

        $message = new FakeConsumerMessage('t1', null, ['content_type' => 'application/avro']);
        $out = $deserializer->deserialize($message);

        self::assertNull($out->getBody());
    }

    public function test_json_payload_without_avro_header_is_json_decoded(): void
    {
        $deserializer = new HeaderAwareMessageDeserializer(
            new JsonDeserializer,
            new ContentTypeSerializationDetector,
            null,
        );

        $message = new FakeConsumerMessage('t1', '{"a":1}', []);
        $out = $deserializer->deserialize($message);

        self::assertSame(['a' => 1], $out->getBody());
    }

    public function test_avro_header_without_registry_throws(): void
    {
        $deserializer = new HeaderAwareMessageDeserializer(
            new JsonDeserializer,
            new ContentTypeSerializationDetector,
            null,
        );

        $message = new FakeConsumerMessage('t1', 'binary', ['content_type' => 'application/avro']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('rbac.kafka.schema_registry.url');
        $deserializer->deserialize($message);
    }

    public function test_avro_header_with_registry_but_invalid_wire_throws(): void
    {
        $codec = RbacKafkaAvroCodec::fromConfig([
            'schema_registry' => ['url' => 'http://127.0.0.1:1'],
            'serialization' => [
                'avro' => [
                    'body_schema_by_topic' => [],
                ],
            ],
        ]);

        $deserializer = new HeaderAwareMessageDeserializer(
            new JsonDeserializer,
            new ContentTypeSerializationDetector,
            $codec,
        );

        $message = new FakeConsumerMessage('any.topic', "\x00", ['content_type' => 'application/avro']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kafka AVRO wire decode failed');
        $deserializer->deserialize($message);
    }

    public function test_avro_header_non_string_body_throws(): void
    {
        $codec = RbacKafkaAvroCodec::fromConfig([
            'schema_registry' => ['url' => 'http://127.0.0.1:1'],
            'serialization' => [
                'avro' => [
                    'body_schema_by_topic' => [],
                ],
            ],
        ]);

        $deserializer = new HeaderAwareMessageDeserializer(
            new JsonDeserializer,
            new ContentTypeSerializationDetector,
            $codec,
        );

        $message = new FakeConsumerMessage('any.topic', ['not' => 'binary'], ['content_type' => 'application/avro']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('binary string');
        $deserializer->deserialize($message);
    }
}
