<?php

declare(strict_types=1);

namespace Mmtech\Rcab\Kafka\Contracts;

use Junges\Kafka\Contracts\ConsumerMessage;

interface TopicMessageHandlerInterface
{
    public function topic(): string;

    public function handle(ConsumerMessage $message): void;
}

