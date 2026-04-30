<?php

declare(strict_types=1);

namespace Mmtech\Rcab\Kafka;

use InvalidArgumentException;
use Junges\Kafka\Facades\Kafka;
use RuntimeException;
use Throwable;

final class KafkaEventPublisher
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|string|null  $payload
     */
    public function publish(string $topic, array|string|null $payload, ?string $key = null, array $headers = []): void
    {
        $normalizedTopic = trim($topic);
        if ($normalizedTopic === '') {
            throw new InvalidArgumentException('Topic must not be empty.');
        }

        try {
            $builder = Kafka::publish()
                ->onTopic($normalizedTopic)
                ->withBody($payload);

            if ($key !== null && trim($key) !== '') {
                $builder->withKafkaKey($key);
            }

            if ($headers !== []) {
                $builder->withHeaders($headers);
            }

            $builder->send();
        } catch (Throwable $e) {
            throw new RuntimeException('Kafka publish failed: '.$e->getMessage(), 0, $e);
        }
    }
}

