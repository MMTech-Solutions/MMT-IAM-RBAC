<?php

declare(strict_types=1);

namespace Mmtech\Rcab\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;
use Mmtech\Rcab\Kafka\TopicHandlerRegistry;

final class RcabConsumeSnapshotsCommand extends Command
{
    protected $signature = 'rcab:consume-snapshots
        {--stop-after-last-message : Stop after consuming available messages}
        {--max-messages=0 : Maximum number of messages to consume (0 = unlimited)}';

    protected $description = 'Consume fixed RBAC snapshots topic plus configured topics and dispatch handlers.';

    public function __construct(
        private readonly TopicHandlerRegistry $topicHandlerRegistry
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! (bool) config('rcab.kafka.enabled', true)) {
            $this->warn('RCAB Kafka consumer is disabled (rcab.kafka.enabled=false).');

            return self::SUCCESS;
        }

        $groupId = (string) config('rcab.kafka.group_id', 'rcab-materializer');
        $brokers = (string) config('rcab.kafka.brokers', (string) config('kafka.brokers', '127.0.0.1:9092'));
        $topics = $this->topicHandlerRegistry->topicsToSubscribe();

        $consumerBuilder = Kafka::consumer(
            topics: $topics,
            groupId: $groupId,
            brokers: $brokers
        )->withHandler(function (ConsumerMessage $message): void {
            $this->topicHandlerRegistry->handle($message);
        });

        $maxMessages = (int) $this->option('max-messages');
        if ($maxMessages > 0) {
            $consumerBuilder->withMaxMessages($maxMessages);
        }

        if ((bool) $this->option('stop-after-last-message')) {
            $consumerBuilder->stopAfterLastMessage();
        }

        $consumerBuilder->build()->consume();

        return self::SUCCESS;
    }
}

