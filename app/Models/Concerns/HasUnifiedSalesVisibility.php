<?php

declare(strict_types=1);
namespace App\Models\Concerns;

use App\Services\Projects\UnifiedProjectAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

trait HasUnifiedSalesVisibility
{
    protected static function bootHasUnifiedSalesVisibility(): void
    {
        static::creating(static function ($site): void {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) { return; }
            $user = auth()->user();
            if ($user && app(UnifiedProjectAccess::class)->isSalesScoped($user)) {
                $site->created_by = (int) $user->id;
                $site->request_source = 'sales';
                $site->sales_user_id = (int) $user->id;
            }
        });
        static::updating(static function ($site): void {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) { return; }
            $user = auth()->user();
            if ($user && app(UnifiedProjectAccess::class)->isSalesScoped($user)
                && $site->isDirty(['created_by', 'request_source', 'sales_user_id', 'company_id', 'legacy_source', 'legacy_source_id'])) {
                throw new AuthorizationException('Bạn không được thay đổi người sở hữu hoặc công ty của công trình.');
            }
        });
        static::addGlobalScope('unified_sales_visibility', static function (Builder $query): void {
            // Background maintenance jobs retain their existing system context.
            if (app()->runningInConsole() && ! app()->runningUnitTests()) { return; }
            $user = auth()->user();
            if ($user) { app(UnifiedProjectAccess::class)->applySalesScope($query, $user); }
        });
    }
}
