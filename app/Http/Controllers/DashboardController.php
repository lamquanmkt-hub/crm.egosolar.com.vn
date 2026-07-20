<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ExecutiveDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Trang dashboard điều hành: tổng hợp số liệu theo bộ lọc kỳ, ngày, sales, nguồn.
 */
final class DashboardController extends Controller
{
    /**
     * Khởi tạo controller với service dashboard và yêu cầu đăng nhập.
     */
    public function __construct(
        private readonly ExecutiveDashboardService $dashboard
    ) {
        $this->middleware('auth');
    }

    /**
     * Hiển thị dashboard với dữ liệu tổng hợp theo bộ lọc của người dùng.
     */
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
