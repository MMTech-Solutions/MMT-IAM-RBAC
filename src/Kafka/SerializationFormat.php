<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Kafka;

enum SerializationFormat
{
    case Json;
    case Avro;
}
