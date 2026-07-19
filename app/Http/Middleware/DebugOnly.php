<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
final class DebugOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_if(app()->environment('production'), 404);
        // Nếu bạn dùng spatie/permission:
        abort_unless($request->user()?->hasAnyRole(['admin', 'dev']) ?? false, 403);
        return $next($request);
    }
}
