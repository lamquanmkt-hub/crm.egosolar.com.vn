<?php

namespace App\Providers;

use App\Models\CRM\Orders\Order;
use App\Models\Projects\MaterialRequest;
use App\Models\Tasks\Task;
use App\Policies\MaterialRequestPolicy;
use App\Policies\OrderPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

// ✅ Orders (CRM)

// Existing

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Order::class => OrderPolicy::class,
        Task::class => TaskPolicy::class,
        MaterialRequest::class => MaterialRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
