<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr\Gifts;

use App\Http\Controllers\Controller;
use App\Models\CRM\Customers\Customer;
use App\Models\Hr\Gift;
use App\Models\Hr\GiftRequest as GiftRequestModel;
use App\Services\Hr\GiftStockService;
use App\Support\EgoCompanyLock;
use App\Support\GiftAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class GiftRequestController extends Controller
{
    public function __construct(private readonly GiftStockService $stockService)
    {
    }

    public function index(Request $request): View
    {
        $companyId = EgoCompanyLock::id();
        $query = GiftRequestModel::query()
            ->where('company_id', $companyId)
            ->with(['creator', 'items.gift'])
            ->latest('id');

        if (! GiftAccess::canSeeAllRequests($request->user())) {
            $query->where('created_by', $request->user()->id);
        }

        if ($status = trim((string) $request->query('status'))) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('code', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('customer_phone', 'like', '%'.$search.'%');
            });
        }

        return view('hr.gifts.requests.index', [
            'requests' => $query->paginate(25)->withQueryString(),
            'canApprove' => GiftAccess::canApprove($request->user()),
            'canHandleStock' => GiftAccess::canHandleStock($request->user()),
            'canSeeCost' => GiftAccess::canSeeCost($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless(GiftAccess::canRequest($request->user()), 403);

        return view('hr.gifts.requests.create', [
            'gifts' => Gift::query()
                ->where('company_id', EgoCompanyLock::id())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(GiftAccess::canRequest($request->user()), 403);
        $companyId = EgoCompanyLock::id();

        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'delivery_address' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:255'],
            'expected_delivery_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'gift_id' => ['required', 'array', 'min:1'],
            'gift_id.*' => ['required', 'integer'],
            'quantity' => ['required', 'array', 'min:1'],
            'quantity.*' => ['required', 'numeric', 'gt:0'],
            'item_note' => ['nullable', 'array'],
            'item_note.*' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = Customer::query()
            ->visibleToUser($request->user())
            ->whereKey($data['customer_id'])
            ->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                'customer_id' => 'Khách hàng không tồn tại hoặc không thuộc quyền xem của bạn.',
            ]);
        }

        $giftIds = array_values(array_unique(array_map('intval', $data['gift_id'])));
        $gifts = Gift::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', $giftIds)
            ->get()
            ->keyBy('id');

        if ($gifts->count() !== count($giftIds)) {
            throw ValidationException::withMessages(['gift_id' => 'Có quà tặng không hợp lệ hoặc đã ngừng sử dụng.']);
        }

        $giftRequest = DB::transaction(function () use ($data, $customer, $gifts, $companyId, $request): GiftRequestModel {
            $giftRequest = GiftRequestModel::query()->create([
                'company_id' => $companyId,
                'code' => $this->nextCode($companyId),
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'delivery_address' => $data['delivery_address'] ?: $customer->address,
                'reason' => $data['reason'] ?? null,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => 'draft',
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['gift_id'] as $index => $giftId) {
                $gift = $gifts->get((int) $giftId);
                $giftRequest->items()->create([
                    'gift_id' => $gift->id,
                    'quantity' => (float) ($data['quantity'][$index] ?? 0),
                    'unit_cost_snapshot' => (float) $gift->cost_price,
                    'note' => $data['item_note'][$index] ?? null,
                ]);
            }

            return $giftRequest;
        });

        return redirect()->route('hr.gifts.requests.show', $giftRequest)
            ->with('success', 'Đã tạo yêu cầu '.$giftRequest->code.'.');
    }

    public function show(Request $request, GiftRequestModel $giftRequest): View
    {
        $this->guardCompany($giftRequest);
        $this->authorizeView($request, $giftRequest);

        return view('hr.gifts.requests.show', [
            'giftRequest' => $giftRequest->load(['items.gift', 'customer', 'creator', 'approver']),
            'canApprove' => GiftAccess::canApprove($request->user()),
            'canHandleStock' => GiftAccess::canHandleStock($request->user()),
            'canSeeCost' => GiftAccess::canSeeCost($request->user()),
        ]);
    }

    public function submit(Request $request, GiftRequestModel $giftRequest): RedirectResponse
    {
        $this->guardCompany($giftRequest);
        $this->authorizeOwnerOrManager($request, $giftRequest);
        abort_unless($giftRequest->status === 'draft', 422, 'Chỉ gửi duyệt yêu cầu nháp.');

        if (! $giftRequest->items()->exists()) {
            return back()->with('error', 'Yêu cầu chưa có quà tặng.');
        }

        $giftRequest->update([
            'status' => 'pending',
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Đã gửi yêu cầu tặng quà chờ duyệt.');
    }

    public function approve(Request $request, GiftRequestModel $giftRequest): RedirectResponse
    {
        abort_unless(GiftAccess::canApprove($request->user()), 403);
        $this->guardCompany($giftRequest);

        try {
            $this->stockService->approveRequest($giftRequest, $request->user());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã duyệt yêu cầu và trừ tồn kho tự động.');
    }

    public function reject(Request $request, GiftRequestModel $giftRequest): RedirectResponse
    {
        abort_unless(GiftAccess::canApprove($request->user()), 403);
        $this->guardCompany($giftRequest);
        abort_unless($giftRequest->status === 'pending', 422);

        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);

        $giftRequest->update([
            'status' => 'rejected',
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return back()->with('success', 'Đã từ chối yêu cầu tặng quà.');
    }

    public function updateStatus(Request $request, GiftRequestModel $giftRequest): RedirectResponse
    {
        abort_unless(GiftAccess::canHandleStock($request->user()), 403);
        $this->guardCompany($giftRequest);

        $data = $request->validate([
            'status' => ['required', 'in:preparing,delivering,delivered,failed,returned'],
            'delivery_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['status'] === 'returned') {
            try {
                $this->stockService->returnRequest($giftRequest, $request->user(), $data['delivery_note'] ?? null);
            } catch (Throwable $e) {
                report($e);

                return back()->with('error', $e->getMessage());
            }

            return back()->with('success', 'Đã hoàn quà về kho và cộng lại tồn kho.');
        }

        $allowedTransitions = [
            'approved' => ['preparing', 'delivering', 'delivered', 'failed'],
            'preparing' => ['delivering', 'delivered', 'failed'],
            'delivering' => ['delivered', 'failed'],
            'failed' => ['delivering'],
        ];

        if (! in_array($data['status'], $allowedTransitions[$giftRequest->status] ?? [], true)) {
            return back()->with('error', 'Không thể chuyển trạng thái từ '.$giftRequest->status.' sang '.$data['status'].'.');
        }

        $giftRequest->update([
            'status' => $data['status'],
            'delivery_note' => $data['delivery_note'] ?? $giftRequest->delivery_note,
            'delivery_status_updated_by' => $request->user()->id,
            'delivery_status_updated_at' => now(),
            'delivered_at' => $data['status'] === 'delivered' ? now() : $giftRequest->delivered_at,
        ]);

        return back()->with('success', 'Đã cập nhật trạng thái giao quà.');
    }

    public function cancel(Request $request, GiftRequestModel $giftRequest): RedirectResponse
    {
        $this->guardCompany($giftRequest);
        $this->authorizeOwnerOrManager($request, $giftRequest);
        abort_unless(in_array($giftRequest->status, ['draft', 'pending', 'rejected'], true), 422);

        $giftRequest->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return redirect()->route('hr.gifts.requests.index')->with('success', 'Đã hủy yêu cầu tặng quà.');
    }

    public function customerSearch(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q'));

        $customers = Customer::query()
            ->visibleToUser($request->user())
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'phone', 'address']);

        return response()->json([
            'results' => $customers->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'text' => trim($customer->name.' · '.($customer->phone ?: 'Chưa có SĐT')),
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
            ])->values(),
        ]);
    }

    private function nextCode(int $companyId): string
    {
        $prefix = 'YTQ-'.now()->format('Y').'-';
        $max = GiftRequestModel::query()
            ->where('company_id', $companyId)
            ->where('code', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(code, '.(strlen($prefix) + 1).') AS UNSIGNED)) AS max_no')
            ->value('max_no');

        return $prefix.str_pad((string) (((int) $max) + 1), 5, '0', STR_PAD_LEFT);
    }

    private function guardCompany(GiftRequestModel $giftRequest): void
    {
        abort_unless((int) $giftRequest->company_id === EgoCompanyLock::id(), 404);
    }

    private function authorizeView(Request $request, GiftRequestModel $giftRequest): void
    {
        abort_unless(
            GiftAccess::canSeeAllRequests($request->user()) || $giftRequest->created_by === $request->user()->id,
            403
        );
    }

    private function authorizeOwnerOrManager(Request $request, GiftRequestModel $giftRequest): void
    {
        abort_unless(
            GiftAccess::canManage($request->user()) || $giftRequest->created_by === $request->user()->id,
            403
        );
    }
}
