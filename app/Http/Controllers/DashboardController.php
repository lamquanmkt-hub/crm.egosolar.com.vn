<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $dashboard;

    public function __construct(DashboardService $dashboard)
    {
        $this->middleware('auth');
        $this->dashboard = $dashboard;
    }

    public function index(Request $request)
{
    $filters = [
        // ưu tiên from/to (view đang dùng), fallback from_date/to_date
        'from'   => $request->input('from') ?: $request->input('from_date'),
        'to'     => $request->input('to') ?: $request->input('to_date'),
        'period' => $request->input('period'),
    ];

    $data = $this->dashboard->getDashboardData($filters);
    $data['filters'] = $filters;

    return view('dashboard.index', $data);
}
}
