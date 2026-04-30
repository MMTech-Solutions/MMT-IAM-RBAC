<?php

declare(strict_types=1);

namespace Mmtech\Rcab\Providers;

use Illuminate\Support\ServiceProvider;
use Mmtech\Rcab\Authorization\Contracts\PermissionCheckerInterface;
use Mmtech\Rcab\Authorization\Contracts\SnapshotStoreInterface;
use Mmtech\Rcab\Authorization\IamFallbackClient;
use Mmtech\Rcab\Authorization\RcabPermissionChecker;
use Mmtech\Rcab\Console\Commands\RcabConsumeSnapshotsCommand;
use Mmtech\Rcab\Kafka\Handlers\RbacSnapshotTopicHandler;
use Mmtech\Rcab\Kafka\KafkaEventPublisher;
use Mmtech\Rcab\Kafka\RbacSnapshotMessageParser;
use Mmtech\Rcab\Kafka\TopicHandlerRegistry;
use Mmtech\Rcab\RcabModule;
use Mmtech\Rcab\Storage\DatabaseSnapshotStore;

final class RcabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/rcab.php', 'rcab');

        $this->app->singleton(RbacSnapshotMessageParser::class);
        $this->app->singleton(KafkaEventPublisher::class);
        $this->app->singleton(RbacSnapshotTopicHandler::class);
        $this->app->singleton(TopicHandlerRegistry::class);
        $this->app->singleton(IamFallbackClient::class);
        $this->app->singleton(SnapshotStoreInterface::class, DatabaseSnapshotStore::class);
        $this->app->singleton(PermissionCheckerInterface::class, RcabPermissionChecker::class);
        $this->app->singleton(RcabPermissionChecker::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/rcab.php' => config_path('rcab.php'),
        ], 'rcab-config');

        $this->publishesMigrations([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'rcab-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RcabConsumeSnapshotsCommand::class,
            ]);
        }

        RcabModule::boot();
    }
}

