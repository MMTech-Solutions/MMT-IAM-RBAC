<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Unit\Kafka;

use InvalidArgumentException;
use Mmtech\Rbac\Kafka\KafkaEventPublisher;
use Mmtech\Rbac\Kafka\RbacKafkaAvroCodec;
use Mmtech\Rbac\Kafka\SerializationFormat;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class KafkaEventPublisherTest extends TestCase
{
    public function test_it_rejects_empty_topic(): void
    {
        $publisher = new KafkaEventPublisher(RbacKafkaAvroCodec::fromConfig([]));

        $this->expectException(InvalidArgumentException::class);
        $publisher->publish('   ', ['event' => 'ignored']);
    }

    public function test_avro_publish_without_codec_throws(): void
    {
        $publisher = new KafkaEventPublisher(RbacKafkaAvroCodec::fromConfig([]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AVRO publish requires rbac.kafka.schema_registry.url');
        $publisher->publish('some.topic', ['x' => 1], null, [], SerializationFormat::Avro);
    }

    public function test_avro_publish_with_string_payload_throws_invalid_argument(): void
    {
        $codec = RbacKafkaAvroCodec::fromConfig([
            'schema_registry' => ['url' => 'http://127.0.0.1:1'],
            'serialization' => [
                'avro' => [
                    'body_schema_by_topic' => [
                        'mapped.topic' => ['schema_name' => 'subject'],
                    ],
                ],
            ],
        ]);
        $publisher = new KafkaEventPublisher($codec);

        $this->expectException(InvalidArgumentException::class);
        $publisher->publish('mapped.topic', 'not-array', null, [], SerializationFormat::Avro);
    }
}

