<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr\Gifts;

use App\Http\Controllers\Controller;
use App\Models\Hr\Gift;
use App\Models\Hr\GiftReceipt;
use App\Services\Hr\GiftStockService;
use App\Support\EgoCompanyLock;
use App\Support\GiftAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

final class GiftReceiptController extends Controller
{
    public function __construct(private readonly GiftStockService $stockService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorizeStock($request);
        $companyId = EgoCompanyLock::id();

        $query = GiftReceipt::query()
            ->where('company_id', $companyId)
            ->with(['creator', 'approver'])
            ->withCount('items')
            ->latest('id');

        if ($status = trim((string) $request->query('status'))) {
            $query->where('status', $status);
        }

        return view('hr.gifts.receipts.index', [
            'receipts' => $query->paginate(25)->withQueryString(),
            'canApprove' => GiftAccess::canApprove($request->user()),
            'canSeeCost' => GiftAccess::canSeeCost($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeStock($request);
        $companyId = EgoCompanyLock::id();

        $data = $request->validate([
            'receipt_date' => ['required', 'date'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'gift_name' => ['required', 'array', 'min:1'],
            'gift_name.*' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'array', 'min:1'],
            'quantity.*' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'array'],
            'unit_cost.*' => ['nullable', 'numeric', 'min:0'],
            'item_note' => ['nullable', 'array'],
            'item_note.*' => ['nullable', 'string', 'max:500'],
        ]);

        $receipt = DB::transaction(function () use ($data, $companyId, $request): GiftReceipt {
            $receipt = GiftReceipt::query()->create([
                'company_id' => $companyId,
                'code' => $this->nextCode($companyId),
                'receipt_date' => $data['receipt_date'],
                'status' => 'draft',
                'supplier_name' => $data['supplier_name'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $resolved = [];

            foreach ($data['gift_name'] as $index => $giftNameRaw) {
                $giftName = trim((string) $giftNameRaw);
                if ($giftName === '') {
                    continue;
                }

                $key = mb_strtolower($giftName);

                if (! isset($resolved[$key])) {
                    $gift = Gift::query()
                        ->where('company_id', $companyId)
                        ->whereRaw('LOWER(name) = ?', [$key])
                        ->first();

                    if (! $gift) {
                        $gift = Gift::query()->create([
                            'company_id' => $companyId,
                            'sku' => $this->nextAutoSku($companyId),
                            'name' => $giftName,
                            'gift_type' => null,
                            'unit' => 'Cái',
                            'cost_price' => (float) ($data['unit_cost'][$index] ?? 0),
                            'minimum_stock' => 0,
                            'current_stock' => 0,
                            'notes' => 'Tạo tự động từ phiếu nhập kho quà tặng.',
                            'is_active' => true,
                        ]);
                    } elseif (! $gift->is_active) {
                        $gift->update(['is_active' => true]);
                    }

                    $resolved[$key] = $gift;
                }

                $gift = $resolved[$key];
                $quantity = (float) ($data['quantity'][$index] ?? 0);
                $unitCost = (float) ($data['unit_cost'][$index] ?? $gift->cost_price ?? 0);

                $receipt->items()->create([
                    'gift_id' => $gift->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => round($quantity * $unitCost, 2),
                    'note' => $data['item_note'][$index] ?? null,
                ]);
            }

            return $receipt;
        });

        return redirect()->route('hr.gifts.receipts.show', $receipt)
            ->with('success', 'Đã tạo phiếu nhập '.$receipt->code.'.');
    }

    public function show(Request $request, GiftReceipt $receipt): View
    {
        $this->authorizeStock($request);
        $this->guardCompany($receipt);

        return view('hr.gifts.receipts.show', [
            'receipt' => $receipt->load(['items.gift', 'creator', 'approver']),
            'canApprove' => GiftAccess::canApprove($request->user()),
            'canSeeCost' => GiftAccess::canSeeCost($request->user()),
        ]);
    }

    public function submit(Request $request, GiftReceipt $receipt): RedirectResponse
    {
        $this->authorizeStock($request);
        $this->guardCompany($receipt);
        abort_unless($receipt->status === 'draft', 422, 'Chỉ gửi duyệt phiếu nháp.');
        abort_unless($receipt->created_by === $request->user()->id || GiftAccess::canHandleStock($request->user()), 403);

        if (! $receipt->items()->exists()) {
            return back()->with('error', 'Phiếu nhập chưa có quà tặng.');
        }

        $receipt->update([
            'status' => 'pending',
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Đã gửi phiếu nhập chờ duyệt.');
    }

    public function approve(Request $request, GiftReceipt $receipt): RedirectResponse
    {
        abort_unless(GiftAccess::canApprove($request->user()), 403);
        $this->guardCompany($receipt);

        try {
            $this->stockService->approveReceipt($receipt, $request->user());
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã duyệt phiếu nhập và cộng tồn kho tự động.');
    }

    public function reject(Request $request, GiftReceipt $receipt): RedirectResponse
    {
        abort_unless(GiftAccess::canApprove($request->user()), 403);
        $this->guardCompany($receipt);
        abort_unless($receipt->status === 'pending', 422);

        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);

        $receipt->update([
            'status' => 'rejected',
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return back()->with('success', 'Đã từ chối phiếu nhập.');
    }

    public function cancel(Request $request, GiftReceipt $receipt): RedirectResponse
    {
        $this->authorizeStock($request);
        $this->guardCompany($receipt);
        abort_unless(in_array($receipt->status, ['draft', 'pending', 'rejected'], true), 422);
        abort_unless($receipt->created_by === $request->user()->id || GiftAccess::canManage($request->user()), 403);

        $receipt->update(['status' => 'cancelled']);

        return redirect()->route('hr.gifts.receipts.index')->with('success', 'Đã hủy phiếu nhập.');
    }

    private function nextCode(int $companyId): string
    {
        $prefix = 'NKQ-'.now()->format('Y').'-';
        $max = GiftReceipt::query()
            ->where('company_id', $companyId)
            ->where('code', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(code, '.(strlen($prefix) + 1).') AS UNSIGNED)) AS max_no')
            ->value('max_no');

        return $prefix.str_pad((string) (((int) $max) + 1), 5, '0', STR_PAD_LEFT);
    }

    private function nextAutoSku(int $companyId): string
    {
        do {
            $sku = 'QT-'.now()->format('ymdHis').'-'.str_pad((string) random_int(1, 99), 2, '0', STR_PAD_LEFT);
        } while (
            Gift::query()
                ->where('company_id', $companyId)
                ->where('sku', $sku)
                ->exists()
        );

        return $sku;
    }

    private function authorizeStock(Request $request): void
    {
        abort_unless(GiftAccess::canHandleStock($request->user()), 403);
    }

    private function guardCompany(GiftReceipt $receipt): void
    {
        abort_unless((int) $receipt->company_id === EgoCompanyLock::id(), 404);
    }
}
