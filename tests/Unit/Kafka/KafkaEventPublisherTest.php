<?php

declare(strict_types=1);

namespace Mmtech\Rcab\Tests\Unit\Kafka;

use InvalidArgumentException;
use Mmtech\Rcab\Kafka\KafkaEventPublisher;
use PHPUnit\Framework\TestCase;

final class KafkaEventPublisherTest extends TestCase
{
    public function test_it_rejects_empty_topic(): void
    {
        $publisher = new KafkaEventPublisher();

        $this->expectException(InvalidArgumentException::class);
        $publisher->publish('   ', ['event' => 'ignored']);
    }
}

