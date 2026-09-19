<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr\Gifts;

use App\Http\Controllers\Controller;
use App\Models\Hr\Gift;
use App\Models\Hr\GiftReceipt;
use App\Models\Hr\GiftRequest;
use App\Support\EgoCompanyLock;
use App\Support\GiftAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class GiftDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $companyId = EgoCompanyLock::id();
        $canHandleStock = GiftAccess::canHandleStock($user);
        $canSeeAllRequests = GiftAccess::canSeeAllRequests($user);

        $giftQuery = Gift::query()->where('company_id', $companyId)->where('is_active', true);

        $requestQuery = GiftRequest::query()
            ->where('company_id', $companyId)
            ->with(['creator', 'items.gift'])
            ->latest('id');

        if (! $canSeeAllRequests) {
            $requestQuery->where('created_by', $user->id);
        }

        $recentRequests = (clone $requestQuery)->limit(5)->get();
        $recentTransactions = $this->buildRecentTransactions($companyId, $recentRequests, $canHandleStock);

        return view('hr.gifts.dashboard', [
            'canHandleStock' => $canHandleStock,
            'summary' => [
                'gift_count' => (clone $giftQuery)->count(),
                'stock_quantity' => (float) (clone $giftQuery)->sum('current_stock'),
                'low_stock_count' => (clone $giftQuery)
                    ->whereColumn('current_stock', '<', 'minimum_stock')
                    ->count(),
                'pending_requests' => (clone $requestQuery)->where('status', 'pending')->count(),
            ],
            'lowStockGifts' => $canHandleStock
                ? (clone $giftQuery)
                    ->whereColumn('current_stock', '<', 'minimum_stock')
                    ->orderByRaw('(minimum_stock - current_stock) DESC')
                    ->limit(6)
                    ->get()
                : collect(),
            'recentTransactions' => $recentTransactions,
            'recentActivities' => $recentTransactions->take(4),
        ]);
    }

    private function buildRecentTransactions(int $companyId, Collection $recentRequests, bool $canHandleStock): Collection
    {
        $requestRows = $recentRequests->map(function (GiftRequest $giftRequest): array {
            return [
                'code' => $giftRequest->code,
                'type' => 'Yêu cầu tặng',
                'target' => $giftRequest->customer_name,
                'quantity' => (float) $giftRequest->items->sum('quantity'),
                'status' => $giftRequest->status,
                'created_at' => $giftRequest->created_at,
                'url' => route('hr.gifts.requests.show', $giftRequest),
            ];
        });

        $receiptRows = collect();
        if ($canHandleStock) {
            $receiptRows = GiftReceipt::query()
                ->where('company_id', $companyId)
                ->with(['creator', 'items'])
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(function (GiftReceipt $receipt): array {
                    return [
                        'code' => $receipt->code,
                        'type' => 'Phiếu nhập kho',
                        'target' => $receipt->supplier_name ?: 'Kho quà tặng',
                        'quantity' => (float) $receipt->items->sum('quantity'),
                        'status' => $receipt->status,
                        'created_at' => $receipt->created_at,
                        'url' => route('hr.gifts.receipts.show', $receipt),
                    ];
                });
        }

        return $requestRows
            ->concat($receiptRows)
            ->sortByDesc(fn (array $row) => $row['created_at'])
            ->values()
            ->take(8);
    }
}
