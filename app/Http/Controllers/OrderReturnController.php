<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\OrderReturnServiceInterface;
use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderRefund;
use App\Models\CRM\Orders\OrderReturn;
use App\Models\CRM\Orders\OrderReturnAttachment;
use App\Models\User;
use App\Services\OrderReturnFinancialService;
use App\Services\OrderReturnInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Controller quản lý quy trình hoàn trả, đổi hàng, thu hồi và hủy đơn.
 */
class OrderReturnController extends Controller
{
    /**
     * Khởi tạo controller với các service xử lý hoàn trả.
     */
    public function __construct(
        private readonly OrderReturnServiceInterface $service,
        private readonly OrderReturnInventoryService $inventoryService,
        private readonly OrderReturnFinancialService $financialService,
    ) {}

    /**
     * Hiển thị dashboard phiếu hoàn trả kèm thống kê theo quyền người dùng.
     */
    public function dashboard(Request $request): View
    {
        $this->ensureAny(['orders.return.view'], ['admin', 'management', 'accounting', 'warehouse', 'kho', 'sales_manager', 'sales']);
        $query = OrderReturn::query()->with(['order.creator', 'requester', 'receivingWarehouse'])->latest('id');
        $this->scopeForUser($query, $request->user());
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where(function ($builder) use ($q) {
                $builder->where('return_code', 'like', "%{$q}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_code', 'like', "%{$q}%"));
            });
        }
        $statsQuery = OrderReturn::query();
        $this->scopeForUser($statsQuery, $request->user());
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'pending' => (clone $statsQuery)->whereIn('status', ['pending_sales_manager', 'pending_accounting', 'pending_management'])->count(),
            'warehouse' => (clone $statsQuery)->whereIn('status', ['approved_waiting_return', 'return_in_transit', 'received', 'inspecting', 'inspected'])->count(),
            'refund' => (clone $statsQuery)->where('status', 'pending_refund')->count(),
        ];

        return view('order_returns.dashboard', [
            'returns' => $query->paginate(20)->withQueryString(),
            'stats' => $stats,
        ]);
    }

    /**
     * Hiển thị danh sách phiếu hoàn trả của một đơn hàng.
     */
    public function index(Order $order): View
    {
        $this->ensureCanViewOrder($order);

        return view('order_returns.index', [
            'order' => $order->load(['items.product', 'lead.customer', 'payments']),
            'returns' => OrderReturn::with(['items.product', 'requester'])->where('order_id', $order->id)->latest('id')->get(),
        ]);
    }

    /**
     * Hiển thị form tạo phiếu hoàn trả với số lượng khả dụng và serial từng dòng hàng.
     */
    public function create(Order $order): View
    {
        $this->ensureCanViewOrder($order);
        $order->load(['items.product', 'lead.customer', 'payments']);
        $available = [];
        $serials = [];
        foreach ($order->items as $item) {
            $used = (int) DB::table('order_return_items as ri')
                ->join('order_returns as r', 'r.id', '=', 'ri.order_return_id')
                ->where('ri.order_item_id', $item->id)
                ->whereNotIn('r.status', ['rejected', 'cancelled'])
                ->sum('ri.accepted_quantity');
            $pending = (int) DB::table('order_return_items as ri')
                ->join('order_returns as r', 'r.id', '=', 'ri.order_return_id')
                ->where('ri.order_item_id', $item->id)
                ->whereNotIn('r.status', ['completed', 'rejected', 'cancelled'])
                ->sum('ri.requested_quantity');
            $available[$item->id] = max(0, (int) $item->quantity - $used - $pending);
            $serials[$item->id] = DB::table('crm_order_item_serial_units as oi')
                ->join('crm_serial_units as su', 'su.id', '=', 'oi.serial_unit_id')
                ->leftJoin('crm_serial_unit_identifiers as sui', 'sui.serial_unit_id', '=', 'su.id')
                ->leftJoin('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
                ->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'su.id')
                ->where('oi.order_item_id', $item->id)
                ->select('su.id', DB::raw("COALESCE(si.code, CONCAT('#', su.id)) as code"), 'st.state')
                ->get();
        }

        return view('order_returns.create', [
            'order' => $order,
            'available' => $available,
            'serialsByItem' => $serials,
            'warehouses' => DB::table('crm_warehouses')->when($order->company_id, fn ($q) => $q->where('company_id', $order->company_id))->orderBy('name')->get(),
        ]);
    }

    /**
     * Tạo phiếu hoàn trả mới; loại trả hàng chỉ áp dụng cho đơn đã hoàn thành và đã xuất kho.
     */
    public function store(Request $request, Order $order): RedirectResponse
    {
        $this->ensureAny(['orders.return.create'], ['admin', 'management', 'sales_manager', 'sales']);
        $data = $request->validate([
            'type' => ['required', 'in:return,exchange,recall,cancel'],
            'reason_code' => ['required', 'string', 'max:50'],
            'reason_detail' => ['required', 'string', 'min:5', 'max:5000'],
            'receiving_warehouse_id' => ['nullable', 'integer', 'exists:crm_warehouses,id'],
            'refund_method' => ['nullable', 'in:bank,cash,debt_credit,exchange_credit,none'],
            'restocking_fee' => ['nullable', 'numeric', 'min:0'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:5000'],
            'items' => ['nullable', 'array'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.condition' => ['nullable', 'string', 'max:40'],
            'items.*.resolution' => ['nullable', 'string', 'max:40'],
            'items.*.note' => ['nullable', 'string', 'max:2000'],
            'items.*.serial_ids' => ['nullable', 'array'],
            'items.*.serial_ids.*' => ['integer'],
            'attachments.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,mp4', 'max:20480'],
        ]);
        /*
        |--------------------------------------------------------------------------
        | EGO_COMPLETED_ORDER_RETURN_GUARD_V1
        |--------------------------------------------------------------------------
        | Phiếu hoàn trả hàng chỉ áp dụng cho đơn đã hoàn thành,
        | đã xuất kho và đã trừ tồn.
        */
        if (($data['type'] ?? null) === 'return') {
            $order->refresh();

            $isCompleted =
                (string) ($order->current_department ?? '')
                === 'completed';

            $inventoryIssued =
                (bool) ($order->inventory_issued ?? false);

            if (! $isCompleted || ! $inventoryIssued) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'type' => 'Chỉ có thể trả hàng đối với đơn đã hoàn thành '
                        .'và đã xuất kho.',
                ]);
            }
        }

        $return = $this->service->create($order, $data, $request->user());
        $this->storeAttachments($request, $return);
        if ($request->boolean('from_order_page')) {
            return redirect()
                ->route('orders.show', ['id' => $order->id, 'tab' => 'returns'])
                ->with('success', 'Đã tạo yêu cầu '.$return->return_code.'.');
        }

        return redirect()->route('order-returns.show', $return)->with('success', 'Đã tạo yêu cầu '.$return->return_code.'.');
    }

    /**
     * Hiển thị chi tiết phiếu hoàn trả.
     */
    public function show(OrderReturn $orderReturn): View
    {
        $this->ensureCanViewReturn($orderReturn);
        $orderReturn->load([
            'order.items.product', 'order.lead.customer', 'items.product', 'items.orderItem', 'items.serials.serialUnit.identifiers.serialIdentifier',
            'attachments', 'approvals.approver', 'histories.user', 'refunds', 'requester', 'receivingWarehouse',
        ]);

        return view('order_returns.show', [
            'return' => $orderReturn,
            'warehouses' => DB::table('crm_warehouses')->when($orderReturn->company_id, fn ($q) => $q->where('company_id', $orderReturn->company_id))->orderBy('name')->get(),
        ]);
    }

    /**
     * Gửi phiếu hoàn trả đi phê duyệt.
     */
    public function submit(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $this->ensureCanEdit($orderReturn);
        $this->service->submit($orderReturn, $request->user());

        return back()->with('success', 'Đã gửi yêu cầu phê duyệt.');
    }

    /**
     * Phê duyệt phiếu; nếu là loại hủy đơn chưa xuất kho thì hủy đơn và hoàn tất luôn.
     */
    public function approve(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $this->ensureApprovalRole($orderReturn, $request->user());
        $updated = $this->service->approve($orderReturn, $request->user(), $request->input('comment'));
        if ($updated->type === 'cancel' && $updated->status === 'approved_waiting_return') {
            DB::transaction(function () use ($updated, $request) {
                $order = Order::query()->lockForUpdate()->findOrFail($updated->order_id);
                if ($order->inventory_issued) {
                    abort(422, 'Đơn đã xuất kho, không thể hủy trực tiếp.');
                }
                $order->update(['current_department' => 'cancelled', 'shipping_status' => 'not_shipped']);
                $this->service->transition($updated, 'completed', 'cancel_order', 'Đã hủy đơn trước xuất kho', $request->user(), [
                    'completed_by' => $request->user()->id,
                    'completed_at' => now(),
                    'financial_status' => 'not_required',
                    'inventory_status' => 'not_required',
                ]);
            });
        }

        return back()->with('success', 'Đã phê duyệt.');
    }

    /**
     * Từ chối phiếu hoàn trả kèm lý do.
     */
    public function reject(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $this->ensureApprovalRole($orderReturn, $request->user());
        $data = $request->validate(['comment' => ['required', 'string', 'min:3', 'max:3000']]);
        $this->service->reject($orderReturn, $request->user(), $data['comment']);

        return back()->with('success', 'Đã từ chối yêu cầu.');
    }

    /**
     * Yêu cầu chỉnh sửa phiếu hoàn trả kèm ghi chú.
     */
    public function requestRevision(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $this->ensureApprovalRole($orderReturn, $request->user());
        $data = $request->validate(['comment' => ['required', 'string', 'min:3', 'max:3000']]);
        $this->service->requestRevision($orderReturn, $request->user(), $data['comment']);

        return back()->with('success', 'Đã yêu cầu chỉnh sửa.');
    }

    /**
     * Đánh dấu hàng hoàn đang vận chuyển về kho.
     */
    public function markInTransit(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $this->ensureAny(['orders.return.update'], ['admin', 'sales', 'sales_manager', 'warehouse', 'kho']);
        $this->service->transition($orderReturn, 'return_in_transit', 'in_transit', 'Hàng đang vận chuyển về kho', $request->user());

        return back()->with('success', 'Đã cập nhật hàng đang vận chuyển về.');
    }

    /**
     * Kho xác nhận đã nhận hàng hoàn về theo số lượng thực nhận.
     */
    public function receive(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $this->ensureAny(['orders.return.receive'], ['admin', 'warehouse', 'kho']);
        $data = $request->validate([
            'receiving_warehouse_id' => ['required', 'integer', 'exists:crm_warehouses,id'],
            'received' => ['required', 'array'],
            'received.*' => ['integer', 'min:0'],
        ]);
        $orderReturn->update(['receiving_warehouse_id' => $data['receiving_warehouse_id']]);
        $this->service->receive($orderReturn, $request->user(), $data['received']);

        return back()->with('success', 'Kho đã xác nhận nhận hàng.');
    }

    /**
     * Lưu kết quả kiểm tra chất lượng hàng hoàn.
     */
    public function inspect(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $this->ensureAny(['orders.return.inspect'], ['admin', 'warehouse', 'kho']);
        $data = $request->validate([
            'inspect' => ['required', 'array'],
            'inspect.*.accepted_quantity' => ['required', 'integer', 'min:0'],
            'inspect.*.rejected_quantity' => ['nullable', 'integer', 'min:0'],
            'inspect.*.condition' => ['required', 'in:sellable,opened_box,defective,warranty_pending,damaged,scrap'],
            'inspect.*.resolution' => ['nullable', 'string', 'max:40'],
            'inspect.*.note' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->service->inspect($orderReturn, $request->user(), $data['inspect']);

        return back()->with('success', 'Đã lưu kết quả kiểm tra hàng hoàn.');
    }

    /**
     * Xử lý nhập kho hàng hoàn; chỉ hàng đạt chuẩn bán lại được cộng tồn.
     */
    public function stockIn(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $this->ensureAny(['orders.return.stock_in'], ['admin', 'warehouse', 'kho']);
        $this->inventoryService->stockIn($orderReturn, $request->user());

        return back()->with('success', 'Đã xử lý kho. Chỉ hàng đạt chuẩn bán lại được cộng tồn.');
    }

    /**
     * Tạo phiếu hoàn tiền/cấn trừ cho phiếu hoàn trả.
     */
    public function createRefund(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $this->ensureAny(['orders.refund.create'], ['admin', 'accounting', 'sales_manager']);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', 'in:bank,cash,debt_credit,exchange_credit'],
            'bank_information' => ['nullable', 'array'],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);
        $this->financialService->createRefund($orderReturn, $request->user(), $data);

        return back()->with('success', 'Đã tạo phiếu hoàn tiền/cấn trừ.');
    }

    /**
     * Duyệt phiếu hoàn tiền.
     */
    public function approveRefund(Request $request, OrderRefund $refund): RedirectResponse
    {
        $this->ensureAny(['orders.refund.approve'], ['admin', 'management', 'accounting']);
        $this->financialService->approve($refund, $request->user());

        return back()->with('success', 'Đã duyệt phiếu hoàn tiền.');
    }

    /**
     * Ghi nhận đã hoàn tiền/cấn trừ thành công, kèm chứng từ nếu có.
     */
    public function processRefund(Request $request, OrderRefund $refund): RedirectResponse
    {
        $this->ensureAny(['orders.refund.process'], ['admin', 'accounting']);
        $request->validate(['attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240']]);
        $path = $request->hasFile('attachment') ? $request->file('attachment')->store('order-returns/refunds', 'local') : null;
        $this->financialService->process($refund, $request->user(), $path);

        return back()->with('success', 'Đã ghi nhận hoàn tiền/cấn trừ thành công.');
    }

    /**
     * Tải hồ sơ đính kèm cho phiếu hoàn trả.
     */
    public function upload(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $this->ensureCanEdit($orderReturn);
        $request->validate([
            'category' => ['required', 'in:evidence,shipping,inspection,invoice,refund,other'],
            'files' => ['required', 'array'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,mp4', 'max:20480'],
        ]);
        $this->storeAttachments($request, $orderReturn);

        return back()->with('success', 'Đã tải hồ sơ lên.');
    }

    /**
     * Tải về file đính kèm của phiếu hoàn trả.
     */
    public function download(OrderReturnAttachment $attachment)
    {
        $this->ensureCanViewReturn($attachment->orderReturn()->firstOrFail());
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }

    /**
     * Lưu các file đính kèm của phiếu hoàn trả vào storage.
     */
    private function storeAttachments(Request $request, OrderReturn $return): void
    {
        foreach ($request->file('attachments', $request->file('files', [])) as $file) {
            if (! $file) {
                continue;
            }
            $path = $file->store('order-returns/'.$return->id, 'local');
            OrderReturnAttachment::create([
                'order_return_id' => $return->id,
                'category' => $request->input('category', 'evidence'),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    /**
     * Chặn 403 nếu người dùng không có quyền xem đơn hàng.
     */
    private function ensureCanViewOrder(Order $order): void
    {
        $user = request()->user();
        if ($this->hasAnyRole($user, ['admin', 'management', 'accounting', 'warehouse', 'kho', 'sales_manager'])) {
            return;
        }
        abort_unless($this->hasAnyRole($user, ['sales']) && (int) $order->created_by === (int) $user->id, 403);
    }

    /**
     * Chặn 403 nếu người dùng không có quyền xem phiếu hoàn trả.
     */
    private function ensureCanViewReturn(OrderReturn $return): void
    {
        $this->ensureCanViewOrder($return->order()->firstOrFail());
    }

    /**
     * Chặn 403 nếu người dùng không có quyền chỉnh sửa phiếu hoàn trả.
     */
    private function ensureCanEdit(OrderReturn $return): void
    {
        $user = request()->user();
        if ($this->hasAnyRole($user, ['admin', 'management', 'sales_manager'])) {
            return;
        }
        abort_unless((int) $return->requested_by === (int) $user->id && in_array($return->status, ['draft', 'revision_requested'], true), 403);
    }

    /**
     * Kiểm tra vai trò phê duyệt tương ứng với trạng thái phiếu.
     */
    private function ensureApprovalRole(OrderReturn $return, User $user): void
    {
        $roles = match ($return->status) {
            'pending_sales_manager' => ['admin', 'sales_manager'],
            'pending_accounting' => ['admin', 'accounting', 'ke_toan'],
            'pending_management' => ['admin', 'management'],
            default => [],
        };
        abort_unless($this->hasAnyRole($user, $roles), 403);
    }

    /**
     * Chặn 403 nếu người dùng không có bất kỳ quyền hoặc vai trò nào được yêu cầu.
     */
    private function ensureAny(array $permissions, array $roles): void
    {
        $user = request()->user();
        $allowed = false;
        foreach ($permissions as $permission) {
            if (method_exists($user, 'can') && $user->can($permission)) {
                $allowed = true;
                break;
            }
        }
        if (! $allowed) {
            $allowed = $this->hasAnyRole($user, $roles);
        }
        abort_unless($allowed, 403);
    }

    /**
     * Kiểm tra người dùng có một trong các vai trò cho trước.
     */
    private function hasAnyRole(User $user, array $roles): bool
    {
        if (empty($roles)) {
            return false;
        }
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole($roles);
        }

        return in_array((string) ($user->role ?? ''), $roles, true);
    }

    /**
     * Giới hạn query theo đơn do sales tạo, trừ các vai trò quản lý.
     */
    private function scopeForUser($query, User $user): void
    {
        if ($this->hasAnyRole($user, ['admin', 'management', 'accounting', 'warehouse', 'kho', 'sales_manager'])) {
            return;
        }
        $query->whereHas('order', fn ($q) => $q->where('created_by', $user->id));
    }
}
