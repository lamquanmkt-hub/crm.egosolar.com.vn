<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\Kpi\HcnsSourceFiles;
use App\Services\Hr\Kpi\HcnsSourceReviews;
use App\Services\Hr\Kpi\Sources\AssetsServiceSource;
use App\Services\Hr\Kpi\VppAllocationKpi;
use App\Services\Hr\Payroll\PayrollAccess;
use App\Support\EgoCompanyLock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller quy trình văn phòng phẩm (VPP): kho vật phẩm, nhập kho, phân bổ và lịch sử xuất nhập.
 */
class OfficeSupplyProcessController extends Controller
{
    private ?int $lastRequestId = null;

    /**
     * Hiển thị trang tổng quan VPP: vật phẩm, phiếu phân bổ gần nhất, lịch sử xuất nhập và thống kê kho.
     */
    public function index(Request $request)
    {
        $this->ensureTables();

        $productQuery = DB::table('hr_vpp_products');

        if ($request->filled('q')) {
            $q = trim($request->q);
            $productQuery->where(function ($x) use ($q) {
                $x->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('note', 'like', "%{$q}%");
            });
        }

        $products = $productQuery
            ->orderBy('name')
            ->get();

        $requests = DB::table('hr_vpp_requests as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->select('r.*', 'u.name as creator_name')
            ->selectSub(function ($q) {
                $q->from('hr_vpp_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('hr_vpp_items.request_id', 'r.id');
            }, 'items_count')
            ->selectSub(function ($q) {
                $q->from('hr_vpp_items')
                    ->selectRaw('COALESCE(SUM(issued_qty), 0)')
                    ->whereColumn('hr_vpp_items.request_id', 'r.id');
            }, 'total_issued')
            ->orderByDesc('r.created_at')
            ->limit(20)
            ->get();

        $movements = DB::table('hr_vpp_movements as m')
            ->leftJoin('hr_vpp_products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->select('m.*', 'p.name as product_name', 'p.unit', 'u.name as user_name')
            ->orderByDesc('m.moved_at')
            ->orderByDesc('m.id')
            ->limit(30)
            ->get();

        $startMonth = now()->startOfMonth();

        $stats = [
            'products' => DB::table('hr_vpp_products')->count(),
            'stock' => DB::table('hr_vpp_products')->sum('current_stock'),
            'in_month' => DB::table('hr_vpp_movements')->where('type', 'in')->where('moved_at', '>=', $startMonth)->sum('qty'),
            'out_month' => DB::table('hr_vpp_movements')->where('type', 'out')->where('moved_at', '>=', $startMonth)->sum('qty'),
            'low_stock' => DB::table('hr_vpp_products')->whereColumn('current_stock', '<=', 'min_stock')->count(),
        ];

        $kpiReady = VppAllocationKpi::ready();
        $kpiUsers = $kpiReady ? DB::table('users')->select('id', 'name', 'email')->orderBy('name')->get() : collect();
        $kpiStatuses = $kpiReady ? DB::table('hr_kpi_source_reviews')->where('source_type', VppAllocationKpi::SOURCE_TYPE)
            ->whereIn('source_id', $requests->pluck('id'))->pluck('status', 'source_id')->all() : [];

        return view('hr.office-supply-process.index', compact(
            'products',
            'requests',
            'movements',
            'stats',
            'kpiReady',
            'kpiUsers',
            'kpiStatuses'
        ));
    }

    /**
     * Tạo vật phẩm VPP mới (hoặc cộng dồn vào vật phẩm trùng tên + đơn vị) kèm tồn đầu kỳ.
     */
    public function productStore(Request $request)
    {
        $this->ensureTables();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'initial_stock' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data) {
            $name = trim($data['name']);
            $unit = trim($data['unit'] ?: 'cái');
            $existing = $this->findSameVppProduct($name, $unit);

            if ($existing) {
                $id = $existing->id;

                DB::table('hr_vpp_products')->where('id', $id)->update([
                    'sku' => $data['sku'] ?: $existing->sku,
                    'min_stock' => $data['min_stock'] ?? $existing->min_stock,
                    'note' => $data['note'] ?? $existing->note,
                    'updated_at' => now(),
                ]);
            } else {
                $id = DB::table('hr_vpp_products')->insertGetId([
                    'name' => $name,
                    'sku' => $data['sku'] ?? null,
                    'unit' => $unit,
                    'current_stock' => 0,
                    'min_stock' => $data['min_stock'] ?? 0,
                    'note' => $data['note'] ?? null,
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $initial = (float) ($data['initial_stock'] ?? 0);

            if ($initial > 0) {
                $this->moveStock($id, 'in', $initial, [
                    'reason' => $existing ? 'Cộng tồn vật phẩm đã có' : 'Tồn đầu kỳ',
                    'note' => $existing ? 'Vật phẩm trùng tên, hệ thống cộng vào mã cũ' : 'Khởi tạo vật phẩm mới',
                    'receiver_name' => null,
                    'department_name' => null,
                    'request_id' => null,
                ]);
            }
        });

        return back()->with('success', 'Đã lưu vật phẩm VPP. Nếu trùng tên, hệ thống đã cộng vào mã cũ.');
    }

    /**
     * Nhập kho VPP cho vật phẩm có sẵn hoặc tạo vật phẩm mới rồi cộng tồn.
     */
    public function importStock(Request $request)
    {
        $this->ensureTables();

        $data = $request->validate([
            'product_id' => ['nullable', 'integer'],
            'new_product_name' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'qty' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data) {
            $productId = $data['product_id'] ?? null;

            if (! $productId && ! empty($data['new_product_name'])) {
                $name = trim($data['new_product_name']);
                $unit = trim($data['unit'] ?? 'cái');

                $existing = $this->findSameVppProduct($name, $unit);

                if ($existing) {
                    $productId = $existing->id;
                } else {
                    $productId = DB::table('hr_vpp_products')->insertGetId([
                        'name' => $name,
                        'unit' => $unit,
                        'current_stock' => 0,
                        'min_stock' => 0,
                        'created_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            abort_if(! $productId, 422, 'Vui lòng chọn vật phẩm hoặc nhập tên vật phẩm mới.');

            $this->moveStock($productId, 'in', (float) $data['qty'], [
                'reason' => $data['reason'] ?? 'Nhập VPP',
                'note' => $data['note'] ?? null,
                'receiver_name' => null,
                'department_name' => null,
                'request_id' => null,
            ]);
        });

        return back()->with('success', 'Đã lưu nhập VPP. Nếu trùng tên, hệ thống đã cộng vào vật phẩm cũ.');
    }

    /**
     * Phân bổ VPP cho phòng ban / người nhận: kiểm tra tồn kho, tạo phiếu hoàn tất và trừ kho.
     */
    public function allocateStock(Request $request)
    {
        $this->ensureTables();

        $data = $request->validate([
            'department_name' => ['required', 'string', 'max:255'],
            'receiver_name' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $items = $this->normalizeAllocateItems($request);

        if (count($items) === 0) {
            return back()->withInput()->withErrors(['items' => 'Cần chọn ít nhất 1 vật phẩm để phân bổ.']);
        }

        // KPI HC06 (cấp phát theo yêu cầu): người yêu cầu/xử lý/kiểm bằng ID, giờ nhận yêu cầu, hạn, giờ giao thực tế.
        $kpi = [];
        if ($request->boolean('kpi_tracked') && VppAllocationKpi::ready()) {
            $k = $request->validate([
                'requester_user_id' => ['required', 'integer', 'exists:users,id'],
                'handler_id' => ['nullable', 'integer', 'exists:users,id'],
                'reviewer_id' => ['required', 'integer', 'exists:users,id'],
                'request_received_at' => ['required', 'date', 'before_or_equal:now'],
                'due_at' => ['required', 'date', 'after_or_equal:request_received_at'],
                'delivered_at' => ['nullable', 'date', 'after_or_equal:request_received_at', 'before_or_equal:now'],
            ], [
                'requester_user_id.required' => 'Chọn người yêu cầu (tài khoản).',
                'reviewer_id.required' => 'Chọn người kiểm tra độc lập.',
                'request_received_at.required' => 'Nhập giờ HCNS nhận yêu cầu.',
                'request_received_at.before_or_equal' => 'Giờ nhận yêu cầu không được sau hiện tại.',
                'due_at.required' => 'Nhập hạn cấp phát theo yêu cầu.',
                'due_at.after_or_equal' => 'Hạn cấp phát phải sau giờ nhận yêu cầu.',
                'delivered_at.before_or_equal' => 'Giờ giao thực tế không được sau hiện tại.',
            ]);
            $handler = (int) ($k['handler_id'] ?? Auth::id());
            if ((int) $k['reviewer_id'] === $handler) {
                return back()->withInput()->withErrors(['reviewer_id' => 'Người kiểm phải khác người xử lý.']);
            }
            if ($locked = \App\Services\Hr\Kpi\HcnsKpiRules::lockedMonth([\Illuminate\Support\Carbon::parse($k['due_at'])->format('Y-m')])) {
                return back()->withInput()->withErrors(['due_at' => VppAllocationKpi::lockMessage($locked)]);
            }
            $kpi = [
                'company_id' => EgoCompanyLock::id(), 'kpi_tracked' => true, 'handler_id' => $handler, 'reviewer_id' => (int) $k['reviewer_id'],
                'requester_user_id' => (int) $k['requester_user_id'], 'request_received_at' => $k['request_received_at'],
                'due_at' => $k['due_at'], 'delivered_at' => $k['delivered_at'] ?? now(),
            ];
        }

        $stockErrors = [];

        foreach ($items as $item) {
            $product = DB::table('hr_vpp_products')->where('id', $item['product_id'])->first();

            if (! $product) {
                $stockErrors[] = 'Không tìm thấy vật phẩm ID '.$item['product_id'];

                continue;
            }

            if ((float) $product->current_stock < (float) $item['qty']) {
                $stockErrors[] = $product->name.' chỉ còn '.$product->current_stock.' '.$product->unit.', không đủ xuất '.$item['qty'];
            }
        }

        if ($stockErrors) {
            return back()->withInput()->withErrors(['stock' => implode(' | ', $stockErrors)]);
        }

        DB::transaction(function () use ($data, $items, $kpi) {
            $requestId = DB::table('hr_vpp_requests')->insertGetId($kpi + [
                'code' => $this->makeCode(),
                'department_name' => $data['department_name'],
                'requester_name' => $data['receiver_name'],
                'requested_month' => now()->format('Y-m'),
                'purpose' => $data['purpose'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => 'completed',
                'receiver_name' => $data['receiver_name'],
                'received_note' => 'Đã phân bổ và trừ kho',
                'received_by' => Auth::id(),
                'received_at' => now(),
                'completed_note' => 'Hoàn tất phân bổ VPP',
                'completed_at' => now(),
                'created_by' => Auth::id(),
                'submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($items as $item) {
                $product = DB::table('hr_vpp_products')->where('id', $item['product_id'])->first();

                DB::table('hr_vpp_items')->insert([
                    'request_id' => $requestId,
                    'product_id' => $item['product_id'],
                    'item_name' => $product->name,
                    'unit' => $product->unit,
                    'requested_qty' => $item['qty'],
                    'hr_qty' => $item['qty'],
                    'issued_qty' => $item['qty'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->moveStock($item['product_id'], 'out', (float) $item['qty'], [
                    'reason' => 'Phân bổ VPP',
                    'note' => $data['note'] ?? null,
                    'receiver_name' => $data['receiver_name'],
                    'department_name' => $data['department_name'],
                    'request_id' => $requestId,
                ]);
            }

            $this->log($requestId, null, 'completed', 'Phân bổ VPP và trừ tồn kho', $data['note'] ?? null);
            if ($kpi !== []) {
                $this->log($requestId, 'completed', 'completed', 'KPI HC06: theo dõi cấp phát theo yêu cầu', 'Nhận yêu cầu '.$kpi['request_received_at'].' · hạn '.$kpi['due_at']);
            }
            $this->lastRequestId = $requestId;
        });

        if ($kpi !== []) {
            return redirect()->route('hr.office-supply-process.show', $this->lastRequestId)
                ->with('success', 'Đã cấp phát và theo dõi KPI. Tải biên bản/ảnh ký nhận rồi bấm Gửi kiểm tra.');
        }

        return back()->with('success', 'Đã phân bổ VPP. Tồn kho đã tự động trừ.');
    }

    /**
     * Trả về JSON chi tiết phiếu phân bổ: thông tin phiếu, item và lịch sử xuất nhập.
     *
     * @param  int|string  $id  ID phiếu
     */
    public function detailJson($id)
    {
        $this->ensureTables();

        $requestRow = DB::table('hr_vpp_requests as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->select('r.*', 'u.name as creator_name')
            ->where('r.id', $id)
            ->first();

        abort_unless($requestRow, 404);

        $items = DB::table('hr_vpp_items as i')
            ->leftJoin('hr_vpp_products as p', 'p.id', '=', 'i.product_id')
            ->select('i.*', 'p.current_stock')
            ->where('i.request_id', $id)
            ->orderBy('i.id')
            ->get();

        $movements = DB::table('hr_vpp_movements as m')
            ->leftJoin('hr_vpp_products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->select('m.*', 'p.name as product_name', 'p.unit', 'u.name as user_name')
            ->where('m.request_id', $id)
            ->orderByDesc('m.id')
            ->get();

        return response()->json([
            'request' => $requestRow,
            'items' => $items,
            'movements' => $movements,
        ]);
    }

    /**
     * Hiển thị trang chi tiết phiếu phân bổ VPP.
     *
     * @param  int|string  $id  ID phiếu
     */
    public function show($id)
    {
        $this->ensureTables();

        $requestRow = DB::table('hr_vpp_requests as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->select('r.*', 'u.name as creator_name')
            ->where('r.id', $id)
            ->first();

        abort_unless($requestRow, 404);

        $items = DB::table('hr_vpp_items as i')
            ->leftJoin('hr_vpp_products as p', 'p.id', '=', 'i.product_id')
            ->select('i.*', 'p.current_stock')
            ->where('i.request_id', $id)
            ->orderBy('i.id')
            ->get();

        $movements = DB::table('hr_vpp_movements as m')
            ->leftJoin('hr_vpp_products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->select('m.*', 'p.name as product_name', 'p.unit', 'u.name as user_name')
            ->where('m.request_id', $id)
            ->orderByDesc('m.id')
            ->get();

        return view('hr.office-supply-process.show', compact('requestRow', 'items', 'movements') + $this->kpiViewData($requestRow));
    }

    /**
     * Xoá phiếu phân bổ và hoàn trả tồn kho các vật phẩm đã xuất (trong transaction).
     *
     * @param  int|string  $id  ID phiếu
     */
    public function destroy($id)
    {
        $this->ensureTables();

        $requestRow = DB::table('hr_vpp_requests')->where('id', $id)->first();
        abort_unless($requestRow, 404);
        if ($blocked = $this->kpiDeleteBlocked($requestRow)) {
            return back()->with('error', $blocked);
        }

        DB::transaction(function () use ($id) {
            $outs = DB::table('hr_vpp_movements')
                ->where('request_id', $id)
                ->where('type', 'out')
                ->get();

            foreach ($outs as $out) {
                $this->moveStock($out->product_id, 'in', (float) $out->qty, [
                    'reason' => 'Hoàn kho do huỷ phiếu phân bổ',
                    'note' => 'Tự động cộng lại tồn kho khi xoá phiếu',
                    'receiver_name' => $out->receiver_name,
                    'department_name' => $out->department_name,
                    'request_id' => null,
                ]);
            }

            DB::table('hr_vpp_movements')->where('request_id', $id)->delete();
            DB::table('hr_vpp_logs')->where('request_id', $id)->delete();
            DB::table('hr_vpp_items')->where('request_id', $id)->delete();
            DB::table('hr_vpp_requests')->where('id', $id)->delete();
        });

        return redirect()->route('hr.office-supply-process.index')
            ->with('success', 'Đã xoá phiếu và hoàn lại tồn kho.');
    }

    /**
     * KPI HC06: sửa mốc/người của phiếu theo dõi KPI (người xử lý hoặc Ban Giám đốc/Admin).
     * Nội dung khác bản đã gửi/xác nhận → xác nhận cũ hết hiệu lực, phải gửi lại.
     */
    public function kpiUpdate(Request $request, $id)
    {
        [$row, $user] = $this->kpiActor((int) $id, 'editor');
        $k = $request->validate([
            'requester_user_id' => ['required', 'integer', 'exists:users,id'],
            'handler_id' => ['required', 'integer', 'exists:users,id'],
            'reviewer_id' => ['required', 'integer', 'exists:users,id', 'different:handler_id'],
            'request_received_at' => ['required', 'date', 'before_or_equal:now'],
            'due_at' => ['required', 'date', 'after_or_equal:request_received_at'],
            'delivered_at' => ['required', 'date', 'after_or_equal:request_received_at', 'before_or_equal:now'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'reviewer_id.different' => 'Người kiểm phải khác người xử lý.',
            'due_at.after_or_equal' => 'Hạn cấp phát phải sau giờ nhận yêu cầu.',
            'delivered_at.after_or_equal' => 'Giờ giao thực tế phải sau giờ nhận yêu cầu.',
            'delivered_at.before_or_equal' => 'Giờ giao thực tế không được sau hiện tại.',
        ]);
        if ($locked = \App\Services\Hr\Kpi\HcnsKpiRules::lockedMonth([\Illuminate\Support\Carbon::parse($k['due_at'])->format('Y-m')])) {
            return back()->withInput()->withErrors(['due_at' => VppAllocationKpi::lockMessage($locked)]);
        }

        $values = collect($k)->except('note')->all();
        $changes = [];
        $labels = ['requester_user_id' => 'người yêu cầu', 'handler_id' => 'người xử lý', 'reviewer_id' => 'người kiểm',
            'request_received_at' => 'giờ nhận yêu cầu', 'due_at' => 'hạn', 'delivered_at' => 'giờ giao'];
        foreach ($values as $field => $value) {
            $old = (string) ($row->{$field} ?? '');
            $new = in_array($field, ['request_received_at', 'due_at', 'delivered_at'], true)
                ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i:s') : (string) $value;
            if ($old !== $new) {
                $changes[] = $labels[$field].': '.($old ?: '—').' → '.$new;
            }
            $values[$field] = $new;
        }
        if ($changes === []) {
            return back()->with('success', 'Không có thay đổi.');
        }

        DB::table('hr_vpp_requests')->where('id', $row->id)->update($values + ['updated_at' => now()]);
        $note = trim((string) ($k['note'] ?? ''));
        $this->log((int) $row->id, $row->status, $row->status, 'KPI HC06: sửa mốc/người', implode('; ', $changes).($note !== '' ? ' · '.$note : ''));
        $this->invalidateKpiReview((int) $row->id, 'Sửa '.implode(', ', array_map(fn ($c) => strtok($c, ':'), $changes)));

        return back()->with('success', 'Đã lưu thay đổi. Nếu phiếu đã gửi/kiểm tra, cần gửi kiểm lại.');
    }

    /** KPI HC06: tải biên bản/ảnh ký nhận (tệp riêng tư). */
    public function kpiFileUpload(Request $request, $id)
    {
        [$row, $user] = $this->kpiActor((int) $id, 'editor');
        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'mimes:'.HcnsSourceFiles::MIMES, 'max:'.HcnsSourceFiles::MAX_KB],
        ], ['files.required' => 'Chọn ít nhất một tệp.']);
        $count = app(HcnsSourceFiles::class)->store(VppAllocationKpi::SOURCE_TYPE, (int) $row->id, (array) $request->file('files', []), (int) $user->id);
        $this->log((int) $row->id, $row->status, $row->status, 'KPI HC06: thêm '.$count.' tệp minh chứng');
        $this->invalidateKpiReview((int) $row->id, 'Thêm tệp minh chứng');

        return back()->with('success', 'Đã tải '.$count.' tệp minh chứng.');
    }

    /** Xem (inline) hoặc tải tệp minh chứng — chỉ qua trang HCNS có quyền, không có URL public. */
    public function kpiFile(Request $request, $id, $fileId)
    {
        $this->ensureTables();
        abort_unless(VppAllocationKpi::ready(), 404);
        $file = app(HcnsSourceFiles::class)->find(VppAllocationKpi::SOURCE_TYPE, (int) $id, (int) $fileId);
        abort_unless($file, 404);

        return app(HcnsSourceFiles::class)->respond($file, $request->boolean('download'));
    }

    public function kpiFileDelete(Request $request, $id, $fileId)
    {
        [$row] = $this->kpiActor((int) $id, 'editor');
        $file = app(HcnsSourceFiles::class)->find(VppAllocationKpi::SOURCE_TYPE, (int) $row->id, (int) $fileId);
        abort_unless($file, 404);
        app(HcnsSourceFiles::class)->delete($file);
        $this->log((int) $row->id, $row->status, $row->status, 'KPI HC06: xóa tệp '.$file->original_name);
        $this->invalidateKpiReview((int) $row->id, 'Xóa tệp minh chứng');

        return back()->with('success', 'Đã xóa tệp minh chứng.');
    }

    /** Người xử lý lưu nháp (chưa gửi, chưa tính KPI). */
    public function kpiDraft(Request $request, $id)
    {
        [$row, $user] = $this->kpiActor((int) $id, 'handler');
        $kpi = app(VppAllocationKpi::class);
        app(HcnsSourceReviews::class)->saveDraft(VppAllocationKpi::SOURCE_TYPE, (int) $row->id, VppAllocationKpi::CRITERION,
            (int) $row->handler_id, $row->reviewer_id ? (int) $row->reviewer_id : null, $kpi->declared($row), $kpi->fingerprint($row),
            (int) $user->id, $this->kpiNote($request));
        $this->log((int) $row->id, $row->status, $row->status, 'KPI HC06: lưu nháp', $this->kpiNote($request));

        return back()->with('success', 'Đã lưu nháp. Chưa gửi người kiểm, chưa tính KPI.');
    }

    /** Người xử lý gửi kiểm tra; thiếu mốc/người/tệp thì chặn. */
    public function kpiSubmit(Request $request, $id)
    {
        [$row, $user] = $this->kpiActor((int) $id, 'handler');
        $kpi = app(VppAllocationKpi::class);
        if (($missing = $kpi->missing($row)) !== []) {
            return back()->with('error', 'Chưa gửi kiểm được — thiếu: '.implode(', ', $missing).'.');
        }
        app(HcnsSourceReviews::class)->submit(VppAllocationKpi::SOURCE_TYPE, (int) $row->id, VppAllocationKpi::CRITERION,
            (int) $row->handler_id, (int) $row->reviewer_id, $kpi->declared($row), $kpi->fingerprint($row), (int) $user->id, $this->kpiNote($request));
        $this->log((int) $row->id, $row->status, $row->status, 'KPI HC06: gửi kiểm tra', $this->kpiNote($request));

        return back()->with('success', 'Đã gửi kiểm tra. Chờ người kiểm xác nhận.');
    }

    /** Người kiểm (khác người xử lý) xác nhận từng mục đạt/không đạt. */
    public function kpiConfirm(Request $request, $id)
    {
        [$row, $user] = $this->kpiActor((int) $id, 'reviewer');
        $checkKeys = array_keys((array) config('hcns_kpi.allocation_checks', []));
        $data = $request->validate([
            'checks' => ['required', 'array'],
            'checks.*' => ['required', \Illuminate\Validation\Rule::in(['pass', 'fail'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['checks.required' => 'Chọn Đạt/Không đạt cho từng mục kiểm tra.']);
        $checks = array_intersect_key((array) $data['checks'], array_flip($checkKeys));
        if (count($checks) !== count($checkKeys)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['checks' => 'Chọn Đạt/Không đạt cho đủ '.count($checkKeys).' mục kiểm tra.']);
        }
        if (collect($checks)->contains('fail') && trim((string) ($data['note'] ?? '')) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['note' => 'Ghi rõ lỗi khi có mục không đạt.']);
        }
        $review = $this->kpiReview((int) $row->id);
        abort_unless($review, 404);
        app(HcnsSourceReviews::class)->confirm($review, $checks, app(VppAllocationKpi::class)->fingerprint($row), (int) $user->id, $data['note'] ?? null);
        $this->log((int) $row->id, $row->status, $row->status, 'KPI HC06: xác nhận kiểm tra', $data['note'] ?? null);

        return back()->with('success', 'Đã xác nhận kết quả kiểm tra.');
    }

    public function kpiReturn(Request $request, $id)
    {
        [$row, $user] = $this->kpiActor((int) $id, 'reviewer');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']], ['reason.required' => 'Ghi lý do trả bổ sung.']);
        $review = $this->kpiReview((int) $row->id);
        abort_unless($review, 404);
        app(HcnsSourceReviews::class)->returnForFix($review, trim($data['reason']), (int) $user->id);
        $this->log((int) $row->id, $row->status, $row->status, 'KPI HC06: trả bổ sung', $data['reason']);

        return back()->with('success', 'Đã trả bổ sung cho người xử lý.');
    }

    /** Người yêu cầu tự xác nhận đã nhận (nếu truy cập được trang); không thay ký nhận trên biên bản. */
    public function kpiReceipt(Request $request, $id)
    {
        [$row, $user] = $this->kpiActor((int) $id, 'requester');
        abort_if(! empty($row->receipt_confirmed_at), 422, 'Phiếu đã được xác nhận nhận hàng.');
        DB::table('hr_vpp_requests')->where('id', $row->id)->update(['receipt_confirmed_by' => $user->id, 'receipt_confirmed_at' => now(), 'updated_at' => now()]);
        $this->log((int) $row->id, $row->status, $row->status, 'KPI HC06: người yêu cầu xác nhận đã nhận');

        return back()->with('success', 'Đã ghi nhận bạn đã nhận vật phẩm.');
    }

    /** Dữ liệu khối KPI HC06 trên trang chi tiết phiếu. */
    private function kpiViewData(object $requestRow): array
    {
        $ready = VppAllocationKpi::ready();
        $tracked = $ready && ! empty($requestRow->kpi_tracked);
        if (! $tracked) {
            return ['kpiReady' => $ready, 'kpiTracked' => false];
        }
        $user = Auth::user();
        $review = $this->kpiReview((int) $requestRow->id);
        $names = DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
        $row = app(AssetsServiceSource::class)->allocationRow((object) ((array) $requestRow + [
            'review_status' => $review->status ?? null, 'result' => $review->result ?? null, 'checks' => $review->checks ?? null,
            'confirmed_by' => $review->confirmed_by ?? null, 'confirmed_at' => $review->confirmed_at ?? null,
            'return_reason' => $review->return_reason ?? null, 'first_submitted_at' => $review->first_submitted_at ?? null,
        ]), $names, now());

        return [
            'kpiReady' => true,
            'kpiTracked' => true,
            'kpiReview' => $review,
            'kpiEvents' => $review ? app(HcnsSourceReviews::class)->events((int) $review->id) : collect(),
            'kpiFiles' => app(HcnsSourceFiles::class)->list(VppAllocationKpi::SOURCE_TYPE, (int) $requestRow->id),
            'kpiMissing' => app(VppAllocationKpi::class)->missing($requestRow),
            'kpiRow' => $row,
            'kpiNames' => $names,
            'kpiLocked' => VppAllocationKpi::lockedMonth($requestRow),
            'kpiIsHandler' => $user && (int) $requestRow->handler_id === (int) $user->id,
            'kpiCanEdit' => $user && $this->canEditKpi($requestRow, $user),
            'kpiCanReview' => $user && $this->canReviewKpi($requestRow, $user),
            'kpiIsRequester' => $user && (int) ($requestRow->requester_user_id ?? 0) === (int) $user->id && (int) $requestRow->handler_id !== (int) $user->id,
            'kpiUsers' => DB::table('users')->select('id', 'name', 'email')->orderBy('name')->get(),
            'allocationChecks' => (array) config('hcns_kpi.allocation_checks', []),
        ];
    }

    /** Phiếu theo dõi KPI đã gửi kiểm hoặc thuộc kỳ đã chốt lương thì không xóa (xóa sẽ mất nguồn đã tính). */
    private function kpiDeleteBlocked(object $row): ?string
    {
        if (! VppAllocationKpi::ready() || empty($row->kpi_tracked)) {
            return null;
        }
        if ($locked = VppAllocationKpi::lockedMonth($row)) {
            return VppAllocationKpi::lockMessage($locked);
        }
        $review = $this->kpiReview((int) $row->id);

        return $review && $review->first_submitted_at
            ? 'Phiếu đã gửi kiểm KPI HC06 — không xóa được. Sửa mốc/tệp rồi gửi lại nếu có sai.'
            : null;
    }

    /** @return array{0: object, 1: \App\Models\User} */
    private function kpiActor(int $id, string $role): array
    {
        $this->ensureTables();
        abort_unless(VppAllocationKpi::ready(), 404);
        $row = DB::table('hr_vpp_requests')->where('id', $id)->first();
        abort_unless($row && ! empty($row->kpi_tracked), 404);
        if ($locked = VppAllocationKpi::lockedMonth($row)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['kpi' => VppAllocationKpi::lockMessage($locked)]);
        }
        $user = Auth::user();
        match ($role) {
            'handler' => abort_unless((int) $row->handler_id === (int) $user->id, 403, 'Chỉ người xử lý của phiếu được lưu nháp/gửi kiểm.'),
            'editor' => abort_unless($this->canEditKpi($row, $user), 403, 'Chỉ người xử lý hoặc Ban Giám đốc/Admin được sửa phiếu theo dõi KPI.'),
            'reviewer' => abort_unless($this->canReviewKpi($row, $user), 403, 'Bạn không phải người kiểm của phiếu này.'),
            'requester' => abort_unless((int) ($row->requester_user_id ?? 0) === (int) $user->id && (int) $row->handler_id !== (int) $user->id, 403, 'Chỉ người yêu cầu được xác nhận đã nhận.'),
        };

        return [$row, $user];
    }

    private function canEditKpi(object $row, $user): bool
    {
        return (int) $row->handler_id === (int) $user->id || PayrollAccess::canApprove($user);
    }

    /** Người kiểm được chỉ định, hoặc Ban Giám đốc/Admin; không bao giờ là người xử lý. */
    private function canReviewKpi(object $row, $user): bool
    {
        if ((int) $row->handler_id === (int) $user->id) {
            return false;
        }

        return (int) ($row->reviewer_id ?? 0) === (int) $user->id || PayrollAccess::canApprove($user);
    }

    private function kpiReview(int $id): ?object
    {
        return app(HcnsSourceReviews::class)->find(VppAllocationKpi::SOURCE_TYPE, $id, VppAllocationKpi::CRITERION);
    }

    private function kpiNote(Request $request): ?string
    {
        $note = trim((string) $request->input('note', ''));

        return $note === '' ? null : mb_substr($note, 0, 2000);
    }

    private function invalidateKpiReview(int $id, string $what): void
    {
        $row = DB::table('hr_vpp_requests')->where('id', $id)->first();
        $before = $this->kpiReview($id);
        $after = app(HcnsSourceReviews::class)->invalidateIfChanged(VppAllocationKpi::SOURCE_TYPE, $id, VppAllocationKpi::CRITERION,
            app(VppAllocationKpi::class)->fingerprint($row), (int) Auth::id(), $what);
        if ($before && $after && $before->status !== $after->status) {
            $this->log($id, $row->status, $row->status, 'KPI HC06: xác nhận cũ hết hiệu lực', $what.' sau khi đã gửi/kiểm tra — cần gửi lại.');
        }
    }

    /**
     * Cập nhật thông tin vật phẩm VPP theo ID.
     *
     * @param  int|string  $id  ID vật phẩm
     */
    public function productUpdate(Request $request, $id)
    {
        $this->ensureTables();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        DB::table('hr_vpp_products')->where('id', $id)->update([
            'name' => trim($data['name']),
            'sku' => $data['sku'] ?? null,
            'unit' => trim($data['unit']),
            'min_stock' => $data['min_stock'] ?? 0,
            'note' => $data['note'] ?? null,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã cập nhật vật phẩm VPP.');
    }

    /**
     * Xoá vật phẩm VPP cùng item và lịch sử xuất nhập liên quan.
     *
     * @param  int|string  $id  ID vật phẩm
     */
    public function productDestroy($id)
    {
        $this->ensureTables();

        $product = DB::table('hr_vpp_products')->where('id', $id)->first();
        abort_unless($product, 404);

        DB::transaction(function () use ($id) {
            if (Schema::hasTable('hr_vpp_items') && Schema::hasColumn('hr_vpp_items', 'product_id')) {
                DB::table('hr_vpp_items')->where('product_id', $id)->delete();
            }

            if (Schema::hasTable('hr_vpp_movements')) {
                DB::table('hr_vpp_movements')->where('product_id', $id)->delete();
            }

            DB::table('hr_vpp_products')->where('id', $id)->delete();
        });

        return back()->with('success', 'Đã xoá vật phẩm VPP.');
    }

    /**
     * Hiển thị lịch sử xuất nhập kho VPP có phân trang.
     */
    public function history()
    {
        $this->ensureTables();

        $movements = DB::table('hr_vpp_movements as m')
            ->leftJoin('hr_vpp_products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->select('m.*', 'p.name as product_name', 'p.unit', 'u.name as user_name')
            ->orderByDesc('m.moved_at')
            ->paginate(50);

        return view('hr.office-supply-process.history', compact('movements'));
    }

    /**
     * Alias của allocateStock để tương thích route cũ.
     */
    public function store(Request $request)
    {
        return $this->allocateStock($request);
    }

    /**
     * Route cũ (đã bỏ): chỉ báo module mới dùng phân bổ trực tiếp từ kho.
     *
     * @param  int|string  $id  ID phiếu (không dùng)
     */
    public function update(Request $request, $id)
    {
        return back()->with('success', 'Module mới dùng phân bổ trực tiếp từ kho, không cần sửa quy trình chữ.');
    }

    /**
     * Route cũ (đã bỏ): bước HR duyệt không còn dùng trong module mới.
     *
     * @param  int|string  $id  ID phiếu (không dùng)
     */
    public function hrReview(Request $request, $id)
    {
        return back()->with('success', 'Module mới đã chuyển sang quản lý kho và phân bổ trực tiếp.');
    }

    /**
     * Route cũ (đã bỏ): bước duyệt phiếu không còn dùng trong module mới.
     *
     * @param  int|string  $id  ID phiếu (không dùng)
     */
    public function approve(Request $request, $id)
    {
        return back()->with('success', 'Module mới đã chuyển sang quản lý kho và phân bổ trực tiếp.');
    }

    /**
     * Route cũ (đã bỏ): bước từ chối phiếu không còn dùng trong module mới.
     *
     * @param  int|string  $id  ID phiếu (không dùng)
     */
    public function reject(Request $request, $id)
    {
        return back()->with('success', 'Module mới đã chuyển sang quản lý kho và phân bổ trực tiếp.');
    }

    /**
     * Route cũ (đã bỏ): bước xuất kho không còn dùng trong module mới.
     *
     * @param  int|string  $id  ID phiếu (không dùng)
     */
    public function issue(Request $request, $id)
    {
        return back()->with('success', 'Module mới đã chuyển sang quản lý kho và phân bổ trực tiếp.');
    }

    /**
     * Route cũ (đã bỏ): bước nhận hàng không còn dùng trong module mới.
     *
     * @param  int|string  $id  ID phiếu (không dùng)
     */
    public function receive(Request $request, $id)
    {
        return back()->with('success', 'Module mới đã chuyển sang quản lý kho và phân bổ trực tiếp.');
    }

    /**
     * Route cũ (đã bỏ): bước hoàn tất phiếu không còn dùng trong module mới.
     *
     * @param  int|string  $id  ID phiếu (không dùng)
     */
    public function complete(Request $request, $id)
    {
        return back()->with('success', 'Module mới đã chuyển sang quản lý kho và phân bổ trực tiếp.');
    }

    /**
     * Chuẩn hoá danh sách vật phẩm phân bổ từ request, chỉ giữ dòng có product_id và số lượng > 0.
     *
     * @return array<int, array{product_id: int, qty: float}>
     */
    private function normalizeAllocateItems(Request $request): array
    {
        $productIds = $request->input('product_id', []);
        $qtys = $request->input('qty', []);

        $items = [];

        foreach ($productIds as $i => $productId) {
            if (! $productId) {
                continue;
            }

            $qty = (float) ($qtys[$i] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $items[] = [
                'product_id' => (int) $productId,
                'qty' => $qty,
            ];
        }

        return $items;
    }

    /**
     * Xuất / nhập kho một vật phẩm (lock bản ghi), cập nhật tồn và ghi lịch sử movement.
     *
     * @param  string  $type  'in' hoặc 'out'
     * @param  array  $meta  Thông tin kèm theo (reason, note, receiver, department, request_id)
     */
    private function moveStock(int $productId, string $type, float $qty, array $meta): void
    {
        $product = DB::table('hr_vpp_products')
            ->where('id', $productId)
            ->lockForUpdate()
            ->first();

        abort_unless($product, 404, 'Không tìm thấy vật phẩm trong kho.');

        $before = (float) $product->current_stock;
        $after = $type === 'in' ? $before + $qty : $before - $qty;

        if ($after < 0) {
            abort(422, 'Tồn kho không đủ để xuất.');
        }

        DB::table('hr_vpp_products')
            ->where('id', $productId)
            ->update([
                'current_stock' => $after,
                'updated_at' => now(),
            ]);

        DB::table('hr_vpp_movements')->insert([
            'product_id' => $productId,
            'request_id' => $meta['request_id'] ?? null,
            'type' => $type,
            'qty' => $qty,
            'before_qty' => $before,
            'after_qty' => $after,
            'department_name' => $meta['department_name'] ?? null,
            'receiver_name' => $meta['receiver_name'] ?? null,
            'reason' => $meta['reason'] ?? null,
            'note' => $meta['note'] ?? null,
            'user_id' => Auth::id(),
            'moved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Ghi log chuyển trạng thái của phiếu VPP nếu bảng log tồn tại.
     */
    private function log(int $requestId, ?string $from, string $to, string $action, ?string $note = null): void
    {
        if (! Schema::hasTable('hr_vpp_logs')) {
            return;
        }

        DB::table('hr_vpp_logs')->insert([
            'request_id' => $requestId,
            'from_status' => $from,
            'to_status' => $to,
            'action' => $action,
            'note' => $note,
            'user_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Sinh mã phiếu dạng VPP-{năm}-{số thứ tự 5 chữ số}.
     */
    private function makeCode(): string
    {
        $next = ((int) DB::table('hr_vpp_requests')->max('id')) + 1;

        return 'VPP-'.now()->format('Y').'-'.str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Tìm vật phẩm VPP trùng tên và đơn vị (không phân biệt hoa thường / khoảng trắng).
     *
     * @return object|null Bản ghi vật phẩm hoặc null
     */
    private function findSameVppProduct(?string $name, ?string $unit = null)
    {
        $name = trim((string) $name);
        $unit = trim((string) ($unit ?: 'cái'));

        if ($name === '') {
            return null;
        }

        return DB::table('hr_vpp_products')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->whereRaw('LOWER(TRIM(unit)) = ?', [mb_strtolower($unit)])
            ->first();
    }

    /**
     * Tạo các bảng VPP (products, requests, items, movements, logs) và bổ sung cột còn thiếu nếu chưa có.
     */
    private function ensureTables(): void
    {
        if (! Schema::hasTable('hr_vpp_products')) {
            Schema::create('hr_vpp_products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('sku', 100)->nullable();
                $table->string('unit', 50)->default('cái');
                $table->decimal('current_stock', 12, 2)->default(0);
                $table->decimal('min_stock', 12, 2)->default(0);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_vpp_requests')) {
            Schema::create('hr_vpp_requests', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('department_name');
                $table->string('requester_name')->nullable();
                $table->string('requested_month', 20)->nullable();
                $table->text('purpose')->nullable();
                $table->text('note')->nullable();
                $table->string('status', 50)->default('completed')->index();
                $table->string('receiver_name')->nullable();
                $table->text('received_note')->nullable();
                $table->unsignedBigInteger('received_by')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->text('completed_note')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('hr_vpp_requests', 'receiver_name')) {
            Schema::table('hr_vpp_requests', function (Blueprint $table) {
                $table->string('receiver_name')->nullable()->after('status');
            });
        }

        if (! Schema::hasColumn('hr_vpp_requests', 'received_note')) {
            Schema::table('hr_vpp_requests', function (Blueprint $table) {
                $table->text('received_note')->nullable()->after('receiver_name');
            });
        }

        if (! Schema::hasColumn('hr_vpp_requests', 'received_by')) {
            Schema::table('hr_vpp_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('received_by')->nullable()->after('received_note');
            });
        }

        if (! Schema::hasColumn('hr_vpp_requests', 'received_at')) {
            Schema::table('hr_vpp_requests', function (Blueprint $table) {
                $table->timestamp('received_at')->nullable()->after('received_by');
            });
        }

        if (! Schema::hasColumn('hr_vpp_requests', 'completed_note')) {
            Schema::table('hr_vpp_requests', function (Blueprint $table) {
                $table->text('completed_note')->nullable()->after('received_at');
            });
        }

        if (! Schema::hasColumn('hr_vpp_requests', 'completed_at')) {
            Schema::table('hr_vpp_requests', function (Blueprint $table) {
                $table->timestamp('completed_at')->nullable()->after('completed_note');
            });
        }

        if (! Schema::hasTable('hr_vpp_items')) {
            Schema::create('hr_vpp_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('item_name');
                $table->string('unit', 50)->nullable();
                $table->decimal('requested_qty', 12, 2)->default(0);
                $table->decimal('hr_qty', 12, 2)->nullable();
                $table->decimal('issued_qty', 12, 2)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('hr_vpp_items', 'product_id')) {
            Schema::table('hr_vpp_items', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->index()->after('request_id');
            });
        }

        if (! Schema::hasTable('hr_vpp_movements')) {
            Schema::create('hr_vpp_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('request_id')->nullable()->index();
                $table->string('type', 20)->index();
                $table->decimal('qty', 12, 2)->default(0);
                $table->decimal('before_qty', 12, 2)->default(0);
                $table->decimal('after_qty', 12, 2)->default(0);
                $table->string('department_name')->nullable();
                $table->string('receiver_name')->nullable();
                $table->string('reason')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('moved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_vpp_logs')) {
            Schema::create('hr_vpp_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->index();
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50)->nullable();
                $table->string('action')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
    }
}
