<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ExecutiveDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ExecutiveDashboardService $dashboard
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $filters = $request->only([
            'period',
            'from',
            'to',
            'from_date',
            'to_date',
            'sales_id',
            'source',
        ]);

        return view(
            'dashboard.index',
            $this->dashboard->build($request->user(), $filters)
        );
    }
}
