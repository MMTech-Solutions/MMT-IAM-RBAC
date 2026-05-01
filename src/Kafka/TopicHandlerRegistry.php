<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Kafka;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rbac\Kafka\Contracts\TopicMessageHandlerInterface;
use Mmtech\Rbac\Kafka\Handlers\RbacSnapshotTopicHandler;
use RuntimeException;

final class TopicHandlerRegistry
{
    /**
     * @var array<string, TopicMessageHandlerInterface>
     */
    private array $handlersByTopic = [];

    public function __construct(private readonly Container $container)
    {
        $config = $this->container->make('config');

        $this->register($this->container->make(RbacSnapshotTopicHandler::class));

        /** @var array<string, class-string<TopicMessageHandlerInterface>> $customMap */
        $customMap = $config->get('rbac.consumer.handlers', []);
        foreach ($customMap as $topic => $handlerClass) {
            if (! is_string($topic) || ! is_string($handlerClass) || trim($topic) === '') {
                continue;
            }

            $handler = $this->container->make($handlerClass);
            if (! $handler instanceof TopicMessageHandlerInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Handler "%s" must implement %s.',
                    $handlerClass,
                    TopicMessageHandlerInterface::class
                ));
            }

            if ($handler->topic() !== trim($topic)) {
                throw new InvalidArgumentException(sprintf(
                    'Configured topic "%s" does not match handler topic "%s" (%s).',
                    $topic,
                    $handler->topic(),
                    $handlerClass
                ));
            }

            if ($handler->topic() === RbacSnapshotTopicHandler::TOPIC) {
                continue;
            }

            $this->register($handler);
        }
    }

    /**
     * @return list<string>
     */
    public function topicsToSubscribe(): array
    {
        return array_keys($this->handlersByTopic);
    }

    public function handle(ConsumerMessage $message): void
    {
        $topic = trim((string) $message->getTopicName());
        if ($topic === '') {
            return;
        }

        $handler = $this->handlersByTopic[$topic] ?? null;
        if ($handler === null) {
            $mode = (string) $this->container->make('config')->get('rbac.consumer.on_unhandled_topic', 'skip');
            if ($mode === 'fail') {
                throw new RuntimeException(sprintf('No Kafka handler configured for topic "%s".', $topic));
            }

            return;
        }

        $handler->handle($message);
    }

    private function register(TopicMessageHandlerInterface $handler): void
    {
        $this->handlersByTopic[$handler->topic()] = $handler;
    }
}

