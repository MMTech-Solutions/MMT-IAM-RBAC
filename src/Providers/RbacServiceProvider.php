<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Providers;

use Illuminate\Support\ServiceProvider;
use Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface;
use Mmtech\Rbac\Authorization\Contracts\SnapshotStoreInterface;
use Mmtech\Rbac\Authorization\IamFallbackClient;
use Mmtech\Rbac\Authorization\RbacPermissionChecker;
use Junges\Kafka\Message\Deserializers\JsonDeserializer;
use Mmtech\Rbac\Console\Commands\RbacConsumeSnapshotsCommand;
use Mmtech\Rbac\Kafka\ContentTypeSerializationDetector;
use Mmtech\Rbac\Kafka\Handlers\RbacSnapshotTopicHandler;
use Mmtech\Rbac\Kafka\HeaderAwareMessageDeserializer;
use Mmtech\Rbac\Kafka\KafkaEventPublisher;
use Mmtech\Rbac\Kafka\RbacKafkaAvroCodec;
use Mmtech\Rbac\Kafka\RbacSnapshotMessageParser;
use Mmtech\Rbac\Kafka\TopicHandlerRegistry;
use Mmtech\Rbac\RbacModule;
use Mmtech\Rbac\Storage\DatabaseSnapshotStore;

final class RbacServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/rbac.php', 'rbac');

        $this->app->singleton(JsonDeserializer::class, static fn (): JsonDeserializer => new JsonDeserializer);
        $this->app->singleton(ContentTypeSerializationDetector::class, static fn (): ContentTypeSerializationDetector => new ContentTypeSerializationDetector);
        $this->app->singleton(RbacKafkaAvroCodec::class, function ($app): RbacKafkaAvroCodec {
            /** @var array<string, mixed> $kafka */
            $kafka = $app->make('config')->get('rbac.kafka', []);

            return RbacKafkaAvroCodec::fromConfig($kafka);
        });
        $this->app->singleton(HeaderAwareMessageDeserializer::class, function ($app): HeaderAwareMessageDeserializer {
            return new HeaderAwareMessageDeserializer(
                $app->make(JsonDeserializer::class),
                $app->make(ContentTypeSerializationDetector::class),
                $app->make(RbacKafkaAvroCodec::class),
            );
        });

        $this->app->singleton(RbacSnapshotMessageParser::class);
        $this->app->singleton(KafkaEventPublisher::class);
        $this->app->singleton(RbacSnapshotTopicHandler::class);
        $this->app->singleton(TopicHandlerRegistry::class);
        $this->app->singleton(IamFallbackClient::class);
        $this->app->singleton(SnapshotStoreInterface::class, DatabaseSnapshotStore::class);
        $this->app->singleton(PermissionCheckerInterface::class, RbacPermissionChecker::class);
        $this->app->singleton(RbacPermissionChecker::class);
    }

    public function boot(): void
    {
        $publishables = [
            __DIR__.'/../../config/rbac.php' => config_path('rbac.php'),
        ];

        $kafkaConfigFromVendor = base_path('vendor/mateusjunges/laravel-kafka/config/kafka.php');
        if (file_exists($kafkaConfigFromVendor)) {
            $publishables[$kafkaConfigFromVendor] = config_path('kafka.php');
        }

        $this->publishes($publishables, 'rbac-config');

        $this->publishesMigrations([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'rbac-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RbacConsumeSnapshotsCommand::class,
            ]);
        }

        RbacModule::boot();
    }
}

