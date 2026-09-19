<?php

namespace App\Http\Middleware;

use App\Support\EgoCompanyLock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class EgoCompanyContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return $next($request);
        }

        EgoCompanyLock::apply($request);

        View::share('activeCompanyId', EgoCompanyLock::id());
        View::share('activeCompanyName', EgoCompanyLock::name());

        return $next($request);
    }
}
