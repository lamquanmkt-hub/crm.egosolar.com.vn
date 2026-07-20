<?php

namespace App\Providers;

use App\Http\Middleware\EgoCompanyContextMiddleware;
use App\Models\Tasks\Task;
use App\Policies\TaskPolicy;
use App\Services\Debug\SchemaInspector;
use App\Services\Debug\TableFilter;
use App\Services\Debug\TableMetadataReader;
use App\Services\Push\PushSubscriptionGateway;
use App\Services\Push\UserModelPushSubscriptionGateway;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TableFilter::class);
        $this->app->singleton(TableMetadataReader::class);
        $this->app->singleton(SchemaInspector::class);
        $this->app->bind(PushSubscriptionGateway::class, UserModelPushSubscriptionGateway::class);
    }

    public function boot(): void
    {
        try {
            app('router')->pushMiddlewareToGroup('web', EgoCompanyContextMiddleware::class);
        } catch (\Throwable $e) {
            // Ignore middleware registration issues during artisan optimize/package discovery.
        }

        // Map policy cho Task
        Gate::policy(Task::class, TaskPolicy::class);
        Paginator::useBootstrap();

    }
}
