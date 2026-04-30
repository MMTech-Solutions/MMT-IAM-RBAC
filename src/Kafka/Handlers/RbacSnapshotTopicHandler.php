<?php

declare(strict_types=1);

namespace Mmtech\Rcab\Kafka\Handlers;

use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rcab\Authorization\Contracts\SnapshotStoreInterface;
use Mmtech\Rcab\Kafka\Contracts\TopicMessageHandlerInterface;
use Mmtech\Rcab\Kafka\RbacSnapshotMessageParser;

final class RbacSnapshotTopicHandler implements TopicMessageHandlerInterface
{
    public const TOPIC = 'iam.rbac.snapshots.v1';

    public function __construct(
        private readonly SnapshotStoreInterface $snapshotStore,
        private readonly RbacSnapshotMessageParser $messageParser
    ) {}

    public function topic(): string
    {
        return self::TOPIC;
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

