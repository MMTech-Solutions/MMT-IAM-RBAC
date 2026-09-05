<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Kafka\Handlers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rbac\Authorization\Contracts\SnapshotStoreInterface;
use Mmtech\Rbac\Kafka\Contracts\TopicMessageHandlerInterface;
use Mmtech\Rbac\Kafka\RbacSnapshotMessageParser;

final class RbacSnapshotTopicHandler implements TopicMessageHandlerInterface
{
    /** Default when rbac.consumer.topic is empty / unset. */
    public const TOPIC = 'iam.rbac.snapshots.v1';

    public function __construct(
        private readonly SnapshotStoreInterface $snapshotStore,
        private readonly RbacSnapshotMessageParser $messageParser,
        private readonly ConfigRepository $config,
    ) {}

    public function topic(): string
    {
        $configured = trim((string) $this->config->get('rbac.consumer.topic', self::TOPIC));

        return $configured !== '' ? $configured : self::TOPIC;
    }

    public function handle(ConsumerMessage $message): void
    {
        $parsed = $this->messageParser->parse($message);
        if ($parsed === null) {
            return;
        }

        if ($parsed->isTombstone) {
            $this->snapshotStore->deleteSnapshot($parsed->sub, $parsed->surface);

            return;
        }

        $this->snapshotStore->upsertSnapshot($parsed);
    }
}
