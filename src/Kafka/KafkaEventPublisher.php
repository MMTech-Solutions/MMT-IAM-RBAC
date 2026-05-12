<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Kafka;

use InvalidArgumentException;
use Junges\Kafka\Facades\Kafka;
use RuntimeException;
use Throwable;

final class KafkaEventPublisher
{
    public function __construct(private readonly RbacKafkaAvroCodec $avroCodec) {}

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>|string|null  $payload
     */
    public function publish(
        string $topic,
        array|string|null $payload,
        ?string $key = null,
        array $headers = [],
        SerializationFormat $format = SerializationFormat::Json,
    ): void {
        $normalizedTopic = trim($topic);
        if ($normalizedTopic === '') {
            throw new InvalidArgumentException('Topic must not be empty.');
        }

        $avroSerializer = null;
        if ($format === SerializationFormat::Avro) {
            if (! is_array($payload)) {
                throw new InvalidArgumentException('AVRO publish requires an array payload.');
            }

            $avroSerializer = $this->avroCodec->avroSerializer();
            $registry = $this->avroCodec->registry();
            if ($avroSerializer === null || $registry === null) {
                throw new RuntimeException(
                    'AVRO publish requires rbac.kafka.schema_registry.url (and optional rbac.kafka.serialization.avro.body_schema_by_topic for subject mapping per topic).'
                );
            }

            if (! $registry->hasBodySchemaForTopic($normalizedTopic)) {
                throw new InvalidArgumentException(sprintf(
                    'AVRO publish has no body schema mapping for topic "%s" in rbac.kafka.serialization.avro.body_schema_by_topic.',
                    $normalizedTopic
                ));
            }
        }

        try {
            $brokers = (string) config('kafka.brokers', '127.0.0.1:9092');

            $mergedHeaders = $headers;
            if ($format === SerializationFormat::Avro) {
                $mergedHeaders['content_type'] = 'application/avro';
            } elseif ((bool) config('rbac.kafka.serialization.emit_json_content_type_header', false)) {
                $mergedHeaders['content_type'] = 'application/json';
            }

            if ($format === SerializationFormat::Avro) {
                $builder = Kafka::publish($brokers)
                    ->onTopic($normalizedTopic)
                    ->usingSerializer($avroSerializer)
                    ->withBody($payload);
            } else {
                $builder = Kafka::publish($brokers)
                    ->onTopic($normalizedTopic)
                    ->withBody($payload);
            }

            if ($key !== null && trim($key) !== '') {
                $builder->withKafkaKey($key);
            }

            if ($mergedHeaders !== []) {
                $builder->withHeaders($this->normalizeStringHeaders($mergedHeaders));
            }

            $builder->send();
        } catch (Throwable $e) {
            throw new RuntimeException('Kafka publish failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    private function normalizeStringHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            if (is_string($value)) {
                $out[$name] = $value;

                continue;
            }

            if (is_int($value) || is_float($value)) {
                $out[$name] = (string) $value;
            }
        }

        return $out;
    }
}

