<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Kafka;

use FlixTech\AvroSerializer\Objects\RecordSerializer;
use FlixTech\SchemaRegistryApi\Registry\BlockingRegistry;
use FlixTech\SchemaRegistryApi\Registry\Cache\AvroObjectCacheAdapter;
use FlixTech\SchemaRegistryApi\Registry\CachedRegistry;
use FlixTech\SchemaRegistryApi\Registry\PromisingRegistry;
use GuzzleHttp\Client;
use Junges\Kafka\Contracts\AvroSchemaRegistry as AvroSchemaRegistryContract;
use Junges\Kafka\Contracts\KafkaAvroSchemaRegistry;
use Junges\Kafka\Message\Deserializers\AvroDeserializer;
use Junges\Kafka\Message\KafkaAvroSchema;
use Junges\Kafka\Message\Registry\AvroSchemaRegistry;
use Junges\Kafka\Message\Serializers\AvroSerializer;

final class RbacKafkaAvroCodec
{
    private function __construct(
        private readonly ?AvroSchemaRegistryContract $registry,
        private readonly ?AvroDeserializer $avroDeserializer,
        private readonly ?AvroSerializer $avroSerializer,
    ) {}

    /**
     * @param  array<string, mixed>  $kafkaConfig
     */
    public static function fromConfig(array $kafkaConfig): self
    {
        $url = $kafkaConfig['schema_registry']['url'] ?? null;
        /** @var array<string, mixed> $schemas */
        $schemas = $kafkaConfig['serialization']['avro']['body_schema_by_topic'] ?? [];

        if (! is_string($url) || trim($url) === '' || ! is_array($schemas) || $schemas === []) {
            return new self(null, null, null);
        }

        $client = new Client(['base_uri' => self::normalizeRegistryBaseUrl($url)]);
        $cachedRegistry = new CachedRegistry(
            new BlockingRegistry(new PromisingRegistry($client)),
            new AvroObjectCacheAdapter()
        );

        $registry = new AvroSchemaRegistry($cachedRegistry);

        foreach ($schemas as $topic => $meta) {
            if (! is_string($topic) || trim($topic) === '') {
                continue;
            }

            $topic = trim($topic);
            $schemaName = null;
            $version = KafkaAvroSchemaRegistry::LATEST_VERSION;

            if (is_array($meta)) {
                $schemaName = $meta['schema_name'] ?? $meta['name'] ?? null;
                $versionCandidate = $meta['version'] ?? null;
                if (is_int($versionCandidate)) {
                    $version = $versionCandidate;
                }
            } elseif (is_string($meta) && trim($meta) !== '') {
                $schemaName = trim($meta);
            }

            if (! is_string($schemaName) || trim($schemaName) === '') {
                continue;
            }

            $registry->addBodySchemaMappingForTopic($topic, new KafkaAvroSchema(trim($schemaName), $version));
        }

        $recordSerializer = new RecordSerializer($cachedRegistry);
        $avroDeserializer = new AvroDeserializer($registry, $recordSerializer);
        $avroSerializer = new AvroSerializer($registry, $recordSerializer);

        return new self($registry, $avroDeserializer, $avroSerializer);
    }

    public function registry(): ?AvroSchemaRegistryContract
    {
        return $this->registry;
    }

    public function avroDeserializer(): ?AvroDeserializer
    {
        return $this->avroDeserializer;
    }

    public function avroSerializer(): ?AvroSerializer
    {
        return $this->avroSerializer;
    }

    private static function normalizeRegistryBaseUrl(string $url): string
    {
        $trimmed = trim($url);

        return str_ends_with($trimmed, '/') ? $trimmed : $trimmed.'/';
    }
}
