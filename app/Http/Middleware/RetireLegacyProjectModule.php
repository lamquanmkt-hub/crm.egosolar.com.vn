<?php

declare(strict_types=1);
namespace App\Http\Middleware;

use App\Models\Projects\Site;
use App\Services\Projects\UnifiedProjectAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Retire legacy project pages/writes; preserve existing warehouse aftercare. */
final class RetireLegacyProjectModule
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = (string) $request->route()->getName();
        $retired = str_starts_with($name, 'sales-projects.')
            || str_starts_with($name, 'technical-projects.')
            || (str_starts_with($name, 'project-test.')
                && ! str_starts_with($name, 'project-test.warehouse.'));
        if (! $retired) { return $next($request); }
        abort_unless($request->user(), 403);

        $legacy = $request->route('project');
        $site = null;
        if ($legacy !== null) {
            $legacyId = is_object($legacy) ? (int) $legacy->id : (int) $legacy;
            // IDs of two different tables must NEVER be treated as interchangeable.
            $matches = Site::query()->where('legacy_source', 'project_test')
                ->where('legacy_source_id', $legacyId)->limit(2)->get();
            abort_unless($matches->count() === 1, 404, 'Không tìm thấy công trình chuẩn hoặc bạn không có quyền truy cập.');
            $site = $matches->first();
            abort_unless(app(UnifiedProjectAccess::class)->ownsSalesSite($site, $request->user()), 403);
        }
        $warehouseContinuation = in_array($name, [
            'project-test.materials.decision', 'project-test.materials.review',
        ], true) || str_starts_with($name, 'project-test.materials.aftercare.');
        if ($warehouseContinuation) {
            abort_if(app(UnifiedProjectAccess::class)->isSalesScoped($request->user()), 403);
            // These existing controllers still enforce their own warehouse/approval
            // permissions. They operate on old stock documents, not a new project.
            return $next($request);
        }
        // Never replay an old form into the new schema, even when its field names look alike.
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            $url = $site ? route('projects-unified.show', $site) : route('projects-unified.create');
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Biểu mẫu cũ đã ngừng sử dụng. Hãy mở công trình chung.', 'url' => $url], 409);
            }
            return response()->view('projects-unified.legacy-retired', ['url' => $url], 409);
        }
        if ($site) {
            return redirect()->route('projects-unified.show', ['site' => $site->id], 302);
        }
        if (str_ends_with($name, '.create')) {
            return redirect()->route('projects-unified.create', [], 302);
        }
        // Only allow filters with identical meanings. Legacy status/phase enums are discarded.
        $filters = $request->only(['q', 'page']);
        if ($name === 'technical-projects.from-sales') { $filters['source'] = 'sales'; }
        if ($name === 'technical-projects.created') { $filters['source'] = 'technical'; }
        return redirect()->route('projects-unified.index', $filters, 302);
    }
}
