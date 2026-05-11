<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;
use Mmtech\Rbac\Kafka\HeaderAwareMessageDeserializer;
use Mmtech\Rbac\Kafka\TopicHandlerRegistry;

final class RbacConsumeSnapshotsCommand extends Command
{
    protected $signature = 'rbac:consume-snapshots
        {--skip-initial-sync : Start directly in continuous consume mode}
        {--stop-after-last-message : Stop after consuming available messages}
        {--max-messages=0 : Maximum number of messages to consume (0 = unlimited)}';

    protected $description = 'Consume fixed RBAC snapshots topic plus configured topics and dispatch handlers.';

    public function __construct(
        private readonly TopicHandlerRegistry $topicHandlerRegistry,
        private readonly HeaderAwareMessageDeserializer $headerAwareMessageDeserializer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! (bool) config('rbac.consumer.enabled', true)) {
            $this->warn('RBAC Kafka consumer is disabled (rbac.consumer.enabled=false).');

            return self::SUCCESS;
        }

        $groupId = (string) config('rbac.consumer.group_id', 'rbac-materializer');
        $brokers = $this->resolveBrokers();
        $topics = $this->topicHandlerRegistry->topicsToSubscribe();

        $skipInitialSync = (bool) $this->option('skip-initial-sync');
        $stopAfterLastMessage = (bool) $this->option('stop-after-last-message');

        if (! $skipInitialSync && ! $stopAfterLastMessage) {
            $this->components->info('Running initial Kafka snapshot sync (catch-up)...');
            $this->consume(
                topics: $topics,
                groupId: $groupId,
                brokers: $brokers,
                stopAfterLastMessage: true
            );
            $this->components->info('Initial sync completed. Waiting for new Kafka events...');
        }

        $this->consume(
            topics: $topics,
            groupId: $groupId,
            brokers: $brokers,
            stopAfterLastMessage: $stopAfterLastMessage
        );

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $topics
     */
    private function consume(
        array $topics,
        string $groupId,
        string $brokers,
        bool $stopAfterLastMessage
    ): void {
        $consumerBuilder = Kafka::consumer(
            topics: $topics,
            groupId: $groupId,
            brokers: $brokers
        )->usingDeserializer($this->headerAwareMessageDeserializer)
            ->withHandler(function (ConsumerMessage $message): void {
                $this->topicHandlerRegistry->handle($message);
            });

        $maxMessages = (int) $this->option('max-messages');
        if ($maxMessages > 0) {
            $consumerBuilder->withMaxMessages($maxMessages);
        }

        if ($stopAfterLastMessage) {
            $consumerBuilder->stopAfterLastMessage();
        }

        $consumerBuilder->build()->consume();
    }

    private function resolveBrokers(): string
    {
        $brokers = config('kafka.brokers', '127.0.0.1:9092');

        if (is_array($brokers)) {
            return implode(',', array_values(array_filter(
                $brokers,
                static fn (mixed $broker): bool => is_string($broker) && trim($broker) !== ''
            )));
        }

        if (is_string($brokers) && trim($brokers) !== '') {
            return trim($brokers);
        }

        return '127.0.0.1:9092';
    }
}

