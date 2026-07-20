<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class EgoCompanyContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        $excluded = [
            'login',
            'logout',
            'register',
            'chon-cong-ty',
            'doi-cong-ty',
            'company-context/current',
        ];

        foreach ($excluded as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $next($request);
            }
        }

        $companyId = (int) session('active_company_id', 0);
        $companyName = (string) session('active_company_name', '');

        if ($companyId <= 0) {
            return redirect('/chon-cong-ty');
        }

        if ($companyName === '' && Schema::hasTable('companies')) {
            $companyName = (string) DB::table('companies')->where('id', $companyId)->value('name');
            if ($companyName !== '') {
                session(['active_company_name' => $companyName]);
            }
        }

        if (! $request->has('company_id')) {
            $request->merge(['company_id' => $companyId]);
        }

        if (! $request->has('company') && $companyName !== '') {
            $request->merge(['company' => $companyName]);
        }

        View::share('activeCompanyId', $companyId);
        View::share('activeCompanyName', $companyName);

        return $next($request);
    }
}
