<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Kafka;

use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageDeserializer;
use Junges\Kafka\Message\ConsumedMessage;
use Junges\Kafka\Message\Deserializers\JsonDeserializer;
use RuntimeException;
use Throwable;

final class HeaderAwareMessageDeserializer implements MessageDeserializer
{
    public function __construct(
        private readonly JsonDeserializer $jsonDeserializer,
        private readonly ContentTypeSerializationDetector $contentTypeSerializationDetector,
        private readonly ?RbacKafkaAvroCodec $avroCodec,
    ) {}

    public function deserialize(ConsumerMessage $message): ConsumerMessage
    {
        if ($message->getBody() === null) {
            return $this->jsonDeserializer->deserialize($message);
        }

        if (! $this->contentTypeSerializationDetector->isAvro($message->getHeaders())) {
            return $this->jsonDeserializer->deserialize($message);
        }

        $recordSerializer = $this->avroCodec?->recordSerializer();
        if ($recordSerializer === null) {
            throw new RuntimeException(
                'Kafka message declares content_type application/avro but Schema Registry is not configured. Set rbac.kafka.schema_registry.url.'
            );
        }

        $rawBody = $message->getBody();
        if (! is_string($rawBody)) {
            throw new RuntimeException(
                'Kafka AVRO message body must be a binary string in Confluent wire format (expected raw bytes from broker).'
            );
        }

        try {
            $decodedBody = $recordSerializer->decodeMessage($rawBody, null);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Kafka AVRO wire decode failed: '.$e->getMessage(),
                0,
                $e
            );
        }

        return new ConsumedMessage(
            topicName: $message->getTopicName(),
            partition: $message->getPartition(),
            headers: $message->getHeaders(),
            body: $decodedBody,
            key: $message->getKey(),
            offset: $message->getOffset(),
            timestamp: $message->getTimestamp(),
        );
    }
}
