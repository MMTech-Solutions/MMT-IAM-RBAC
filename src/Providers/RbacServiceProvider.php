<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Mmtech\Rbac\Authorization\IamUserProfileClient;
use Mmtech\Rbac\Http\Middleware\AuthorizeAbilityOrInternal;
use Mmtech\Rbac\Http\Middleware\BindGatewayUserToAuth;
use Mmtech\Rbac\Http\Middleware\EnrichGatewayUserInfoFromIam;
use Mmtech\Rbac\Http\Middleware\ResolveGatewayUserInfo;
use Mmtech\Rbac\Http\Middleware\ResolveTrustedInternalServiceRequest;
use Mmtech\Rbac\Http\Middleware\VerifyInternalRbacToken;
use Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface;
use Mmtech\Rbac\Authorization\Contracts\SnapshotFallbackInterface;
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
        $this->app->singleton(IamUserProfileClient::class);
        $this->app->singleton(SnapshotFallbackInterface::class, static fn ($app): IamFallbackClient => $app->make(IamFallbackClient::class));
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

        $this->registerMiddlewareAliases();

        RbacModule::boot();
    }

    private function registerMiddlewareAliases(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        foreach (self::middlewareAliases() as $alias => $class) {
            $router->aliasMiddleware($alias, $class);
        }
    }

    /**
     * @return array<string, class-string>
     */
    public static function middlewareAliases(): array
    {
        return [
            'rbac.trusted.internal' => ResolveTrustedInternalServiceRequest::class,
            'rbac.internal.token' => VerifyInternalRbacToken::class,
            'rbac.auth.user' => ResolveGatewayUserInfo::class,
            'rbac.auth.user.info' => EnrichGatewayUserInfoFromIam::class,
            'rbac.bind.gateway.user' => BindGatewayUserToAuth::class,
            'rbac.authorize.or.internal' => AuthorizeAbilityOrInternal::class,
        ];
    }
}

