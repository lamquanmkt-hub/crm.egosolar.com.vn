<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\SolarWarrantyClaim;
use App\Services\Warranty\WarrantyExchangeService;
use App\Support\SolarMaintenanceAccess;
use App\Support\Warranty\WarrantyException;
use App\Support\Warranty\WarrantyFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Hành động của quy trình ĐỔI HÀNG BẢO HÀNH. Mỗi action = 1 method riêng, KHÔNG nhận status tuỳ ý từ request.
 * Quyền/trạng thái được kiểm tra lại trong WarrantyExchangeService (server-side, có khóa dòng).
 */
class WarrantyExchangeWorkflowController extends Controller
{
    public function __construct(private readonly WarrantyExchangeService $service)
    {
    }

    public function approve(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        $d = $r->validate(['approval_note' => ['nullable', 'string', 'max:5000'], 'override_reason' => ['nullable', 'string', 'max:2000']]);

        return $this->run($claim, 'Đã duyệt và chuyển Kho xử lý.', fn () => $this->service->approve($claim->id, $r->user(), $d['approval_note'] ?? null, $d['override_reason'] ?? null));
    }

    public function requestInfo(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        $d = $r->validate(['reason' => ['required', 'string', 'max:5000']], ['reason.required' => 'Bắt buộc nhập lý do yêu cầu bổ sung.']);

        return $this->run($claim, 'Đã yêu cầu bổ sung thông tin.', fn () => $this->service->requestInfo($claim->id, $r->user(), $d['reason']));
    }

    public function reject(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        $d = $r->validate(['reason' => ['required', 'string', 'max:5000']], ['reason.required' => 'Bắt buộc nhập lý do từ chối.']);

        return $this->run($claim, 'Đã từ chối đề xuất.', fn () => $this->service->reject($claim->id, $r->user(), $d['reason']));
    }

    public function resubmit(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        $d = $r->validate([
            'issue_description' => ['nullable', 'string', 'max:10000'],
            'diagnosis' => ['nullable', 'string', 'max:10000'],
            'proposed_solution' => ['nullable', 'string', 'max:10000'],
        ]);

        return $this->run($claim, 'Đã bổ sung và gửi duyệt lại.', fn () => $this->service->resubmit($claim->id, $r->user(), $d));
    }

    public function reopen(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        $d = $r->validate(['reason' => ['required', 'string', 'max:5000']]);

        return $this->run($claim, 'Đã mở lại phiếu bị từ chối.', fn () => $this->service->reopenRejected($claim->id, $r->user(), $d['reason']));
    }

    public function cancel(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        $d = $r->validate(['reason' => ['required', 'string', 'max:5000']], ['reason.required' => 'Bắt buộc nhập lý do hủy phiếu.']);

        return $this->run($claim, 'Đã hủy phiếu.', fn () => $this->service->cancel($claim->id, $r->user(), $d['reason']));
    }

    // ---- Kho (chặn cứng server-side: Kỹ thuật không được gọi trực tiếp các action này)
    public function reserve(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        abort_unless(SolarMaintenanceAccess::isWarehouse($r->user()), 403);
        $d = $r->validate([
            'warehouse_id' => ['required', 'integer', 'exists:crm_warehouses,id'],
            'serial_code' => ['required', 'string', 'max:190'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->run($claim, 'Đã chọn serial thay thế và GIỮ HÀNG.', fn () => $this->service->reserveSerial($claim->id, $r->user(), $d['serial_code'], (int) $d['warehouse_id'], $d['note'] ?? null));
    }

    public function release(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        abort_unless(SolarMaintenanceAccess::isWarehouse($r->user()), 403);
        $d = $r->validate(['reason' => ['required', 'string', 'max:5000']]);

        return $this->run($claim, 'Đã nhả hàng.', fn () => $this->service->releaseReservation($claim->id, $r->user(), $d['reason']));
    }

    public function issue(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        abort_unless(SolarMaintenanceAccess::isWarehouse($r->user()), 403);
        $d = $r->validate(['note' => ['nullable', 'string', 'max:5000']]);

        return $this->run($claim, 'Đã xuất kho thiết bị thay thế.', fn () => $this->service->issue($claim->id, $r->user(), $d['note'] ?? null));
    }

    public function faultyReturn(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        abort_unless(SolarMaintenanceAccess::isWarehouse($r->user()), 403);
        $d = $r->validate([
            'warehouse_id' => ['required', 'integer', 'exists:crm_warehouses,id'],
            'returned_by' => ['required', 'string', 'max:190'],
            'condition' => ['required', 'string', 'in:'.implode(',', array_keys(WarrantyFlow::FAULTY_CONDITIONS))],
            'note' => ['nullable', 'string', 'max:5000'],
        ], ['returned_by.required' => 'Nhập người mang thiết bị lỗi về.']);

        return $this->run($claim, 'Đã ghi nhận thu hồi thiết bị lỗi.', fn () => $this->service->confirmFaultyReturn($claim->id, $r->user(), (int) $d['warehouse_id'], $d['returned_by'], $d['condition'], $d['note'] ?? null));
    }

    // ---- Kỹ thuật
    public function techReceive(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        $d = $r->validate([
            'received_at' => ['nullable', 'date'],
            'delivered_by' => ['nullable', 'string', 'max:190'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->run($claim, 'Đã xác nhận nhận thiết bị thay thế.', fn () => $this->service->techReceive($claim->id, $r->user(), $d['received_at'] ?? null, $d['delivered_by'] ?? null, $d['note'] ?? null));
    }

    public function confirmReplaced(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        $d = $r->validate([
            'replaced_at' => ['nullable', 'date'],
            'result' => ['required', 'in:success,issue'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->run($claim, 'Đã xác nhận thay thiết bị cho khách. Serial cũ ↔ mới đã được lưu.', fn () => $this->service->confirmReplaced($claim->id, $r->user(), $d['replaced_at'] ?? null, $d['result'], $d['note'] ?? null));
    }

    public function deferReturn(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        $d = $r->validate(['reason' => ['required', 'string', 'max:5000']]);

        return $this->run($claim, 'Đã ghi nhận hoãn thu hồi thiết bị lỗi (vẫn được theo dõi).', fn () => $this->service->deferFaultyReturn($claim->id, $r->user(), $d['reason']));
    }

    public function complete(Request $r, SolarWarrantyClaim $claim): RedirectResponse|JsonResponse
    {
        $d = $r->validate([
            'resolution' => ['required', 'string', 'max:10000'],
            'actual_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ], ['resolution.required' => 'Bắt buộc nhập kết quả xử lý.']);

        return $this->run($claim, 'Đã hoàn tất phiếu đổi hàng.', fn () => $this->service->complete($claim->id, $r->user(), $d['resolution'], isset($d['actual_cost']) ? (float) $d['actual_cost'] : null));
    }

    private function run(SolarWarrantyClaim $claim, string $ok, callable $fn): RedirectResponse|JsonResponse
    {
        abort_unless($claim->claim_type === WarrantyFlow::TYPE_EXCHANGE, 404);
        $user = request()->user();
        abort_unless(SolarMaintenanceAccess::canViewAny($user), 403);
        $current = \App\Support\Synced\EgoCompanyScope::currentId();
        $cc = (int) $claim->company_id;
        if ($current > 0 && $cc > 0 && $current !== $cc && ! SolarMaintenanceAccess::isAdmin($user)) {
            abort(403, 'Dữ liệu không thuộc công ty đang làm việc.');
        }
        try {
            \App\Support\Warranty\Retry::onDeadlock($fn);
        } catch (WarrantyException $e) {
            if (request()->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage(), 'errors' => ['workflow' => [$e->getMessage()]]], 422);
            }

            return back()->withInput()->withErrors(['workflow' => $e->getMessage()]);
        }
        if (request()->expectsJson()) {
            session()->flash('success', $ok);

            return response()->json(['ok' => true, 'message' => $ok]);
        }

        return back()->with('success', $ok);
    }
}
