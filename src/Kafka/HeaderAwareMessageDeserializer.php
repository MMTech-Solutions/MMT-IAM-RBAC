<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Kafka;

use Junges\Kafka\Contracts\AvroSchemaRegistry as AvroSchemaRegistryContract;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageDeserializer;
use Junges\Kafka\Message\Deserializers\AvroDeserializer;
use Junges\Kafka\Message\Deserializers\JsonDeserializer;
use RuntimeException;

final class HeaderAwareMessageDeserializer implements MessageDeserializer
{
    public function __construct(
        private readonly JsonDeserializer $jsonDeserializer,
        private readonly ContentTypeSerializationDetector $contentTypeSerializationDetector,
        private readonly ?AvroDeserializer $avroDeserializer,
        private readonly ?AvroSchemaRegistryContract $avroSchemaRegistry,
    ) {}

    public function deserialize(ConsumerMessage $message): ConsumerMessage
    {
        if ($message->getBody() === null) {
            return $this->jsonDeserializer->deserialize($message);
        }

        if (! $this->contentTypeSerializationDetector->isAvro($message->getHeaders())) {
            return $this->jsonDeserializer->deserialize($message);
        }

        if ($this->avroDeserializer === null || $this->avroSchemaRegistry === null) {
            throw new RuntimeException(
                'Kafka message declares content_type application/avro but Schema Registry is not configured. Set rbac.kafka.schema_registry.url and rbac.kafka.serialization.avro.body_schema_by_topic.'
            );
        }

        $topic = $message->getTopicName();
        if (! is_string($topic) || trim($topic) === '' || ! $this->avroSchemaRegistry->hasBodySchemaForTopic($topic)) {
            throw new RuntimeException(sprintf(
                'Kafka AVRO message on topic "%s" has no body schema mapping in rbac.kafka.serialization.avro.body_schema_by_topic.',
                is_string($topic) ? $topic : ''
            ));
        }

        return $this->avroDeserializer->deserialize($message);
    }
}
