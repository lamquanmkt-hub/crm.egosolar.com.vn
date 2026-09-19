<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr\Gifts;

use App\Http\Controllers\Controller;
use App\Models\Hr\Gift;
use App\Models\Hr\GiftReceipt;
use App\Support\EgoCompanyLock;
use App\Support\GiftAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class GiftStockController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(GiftAccess::canHandleStock($request->user()), 403);

        $companyId = EgoCompanyLock::id();
        $search = trim((string) $request->query('q'));

        $giftBaseQuery = Gift::query()
            ->where('company_id', $companyId)
            ->where('is_active', true);

        $giftTableQuery = Gift::query()
            ->where('company_id', $companyId);

        if ($search !== '') {
            $giftTableQuery->where(function ($builder) use ($search): void {
                $builder->where('sku', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('gift_type', 'like', '%'.$search.'%');
            });
        }

        $gifts = $giftTableQuery
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $receipts = GiftReceipt::query()
            ->where('company_id', $companyId)
            ->with(['creator', 'approver'])
            ->withCount('items')
            ->latest('id')
            ->limit(8)
            ->get();

        $lowStockGifts = (clone $giftBaseQuery)
            ->whereColumn('current_stock', '<', 'minimum_stock')
            ->orderByRaw('(minimum_stock - current_stock) DESC')
            ->limit(8)
            ->get();

        return view('hr.gifts.stock.index', [
            'gifts' => $gifts,
            'receipts' => $receipts,
            'lowStockGifts' => $lowStockGifts,
            'summary' => [
                'gift_count' => (clone $giftBaseQuery)->count(),
                'stock_quantity' => (float) (clone $giftBaseQuery)->sum('current_stock'),
                'low_stock_count' => (clone $giftBaseQuery)
                    ->whereColumn('current_stock', '<', 'minimum_stock')
                    ->count(),
                'pending_receipts' => GiftReceipt::query()
                    ->where('company_id', $companyId)
                    ->where('status', 'pending')
                    ->count(),
            ],
            'canApprove' => GiftAccess::canApprove($request->user()),
            'canSeeCost' => GiftAccess::canSeeCost($request->user()),
        ]);
    }
}
