<?php

namespace App\Providers;

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
        $this->app->scoped(\App\Services\Workspace\WorkspaceProfileService::class);
        $this->app->scoped(\App\Services\Workspace\WorkspaceContextService::class);
        $this->app->bind(PushSubscriptionGateway::class, UserModelPushSubscriptionGateway::class);
    }

    public function boot(): void
    {
        /* EGO_SERIAL_WARRANTY_GATE_START */
        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability) {
            if (request()->is('serial-warranty') || request()->is('serial-warranty/*')) {
                if ((
            (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'warehouse', 'kho'])) ||
            (method_exists($user, 'hasRole') && (
                $user->hasRole('admin') ||
                $user->hasRole('warehouse') ||
                $user->hasRole('kho')
            )) ||
            (isset($user->role) && in_array(strtolower((string) $user->role), ['admin', 'warehouse', 'kho'], true))
        )) {
                    return true;
                }
            }

            return null;
        });
        /* EGO_SERIAL_WARRANTY_GATE_END */

        \Illuminate\Support\Facades\Event::listen('eloquent.creating: *', function ($eventName, array $data) {
            $model = $data[0] ?? null;
            if ($model instanceof \Illuminate\Database\Eloquent\Model) {
                if (
                    array_key_exists('company_id', $model->getAttributes()) || 
                    in_array('company_id', $model->getFillable())
                ) {
                    $model->company_id = \App\Support\EgoCompanyLock::id();
                }
            }
        });

        \Illuminate\Support\Facades\Event::listen('eloquent.updating: *', function ($eventName, array $data) {
            $model = $data[0] ?? null;
            if ($model instanceof \Illuminate\Database\Eloquent\Model) {
                if ($model->isDirty('company_id')) {
                    throw new \RuntimeException('CRITICAL ERROR: Không được phép thay đổi company_id của bản ghi đã tồn tại.');
                }
            }
        });

        // Middleware EgoCompanyContextMiddleware đã bị xóa do chuyển sang cấu hình tĩnh.

        // Map policy cho Task
        Gate::policy(Task::class, TaskPolicy::class);
        Paginator::useBootstrap();
        
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(!app()->isProduction());

    }
}
