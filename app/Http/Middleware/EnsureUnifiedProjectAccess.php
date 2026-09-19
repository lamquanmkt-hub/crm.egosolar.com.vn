<?php

declare(strict_types=1);
namespace App\Http\Middleware;

use App\Models\Projects\Site;
use App\Services\Projects\UnifiedProjectAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUnifiedProjectAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Maintenance retains its dedicated approval and attachment policies.
        if ($request->routeIs('projects-unified.maintenance.*')) { return $next($request); }
        $user = $request->user();
        abort_unless($user, 403);
        $access = app(UnifiedProjectAccess::class);
        if ($access->isSalesScoped($user)) {
            $site = $request->route('site');
            if ($site !== null) {
                $site = $site instanceof Site ? $site : Site::query()->findOrFail((int) $site);
                abort_unless($access->ownsSalesSite($site, $user), 403);
            }
            // Sales may create a canonical project and read its progress/revenue.
            // Creating does not grant technical assignment or finance-write rights.
            if (! in_array($request->method(), ['GET', 'HEAD'], true)
                && ! $request->routeIs('projects-unified.store')) {
                abort(403, 'Sales chỉ được tạo và theo dõi công trình trong phạm vi được cấp quyền.');
            }
        }
        return $next($request);
    }
}
