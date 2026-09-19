<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\ProjectTest\History;
use App\Models\ProjectTest\MaterialAftercareItem;
use App\Models\ProjectTest\MaterialAftercareRequest;
use App\Models\ProjectTest\MaterialItem;
use App\Models\ProjectTest\MaterialRequest;
use App\Models\ProjectTest\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ProjectMaterialAftercareController extends Controller
{
    private const TYPES = ['shortage', 'additional', 'return', 'exchange', 'damaged'];

    public function store(Request $request, Project $project, MaterialRequest $materialRequest): RedirectResponse
    {
        $this->authorizeTechnical($request, $project);
        $this->assertParent($project, $materialRequest);
        abort_unless($materialRequest->status === 'issued', 422, 'Chỉ tạo yêu cầu đổi/trả/bổ sung sau khi phiếu đã xuất kho.');

        $data = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'material_item_id' => ['nullable', 'integer'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'desired_item_name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['nullable', 'string', 'max:40'],
            'condition' => ['nullable', Rule::in(['new', 'used', 'damaged', 'missing'])],
            'technical_note' => ['required', 'string', 'max:3000'],
        ]);

        $type = (string) $data['type'];
        $sourceItem = null;
        if (! empty($data['material_item_id'])) {
            $sourceItem = MaterialItem::query()
                ->with('allocations')
                ->where('material_request_id', $materialRequest->id)
                ->find((int) $data['material_item_id']);
        }

        if ($type !== 'additional') {
            abort_unless($sourceItem, 422, 'Hãy chọn đúng vật tư đã xuất kho.');
        }

        $itemName = trim((string) ($data['item_name'] ?? ''));
        if ($sourceItem) {
            $itemName = (string) $sourceItem->item_name;
        } elseif ($type === 'additional') {
            // Biểu mẫu gọn dùng desired_item_name làm tên vật tư mới.
            $itemName = trim((string) ($data['desired_item_name'] ?? ''));
        }
        abort_if($itemName === '', 422, 'Hãy nhập tên vật tư cần bổ sung.');

        $quantity = (float) $data['quantity'];
        if ($sourceItem && in_array($type, ['return', 'exchange', 'damaged'], true)) {
            $issuedQty = (float) ($sourceItem->issued_quantity ?: $sourceItem->quantity);
            $activeQty = (float) DB::table('project_test_material_aftercare_items as ai')
                ->join('project_test_material_aftercare_requests as ar', 'ar.id', '=', 'ai.aftercare_request_id')
                ->where('ar.material_request_id', $materialRequest->id)
                ->where('ai.material_item_id', $sourceItem->id)
                ->whereIn('ar.status', ['warehouse_review', 'revision', 'pending_manager', 'approved', 'processing'])
                ->sum('ai.quantity');
            abort_if($quantity + $activeQty > $issuedQty + 0.0001, 422, 'Số lượng đổi/trả đang yêu cầu vượt quá số đã xuất.');
        }

        $allocation = $sourceItem?->allocations?->first();
        $productId = (int) ($allocation?->product_id ?: $sourceItem?->product_id ?: 0);
        $warehouseId = (int) ($allocation?->warehouse_id ?: 0);
        $stockSnapshot = $productId > 0 && $warehouseId > 0
            ? $this->stockQty($productId, $warehouseId)
            : null;

        $aftercare = DB::transaction(function () use ($request, $project, $materialRequest, $data, $sourceItem, $itemName, $quantity, $allocation, $productId, $warehouseId, $stockSnapshot, $type): MaterialAftercareRequest {
            $aftercare = MaterialAftercareRequest::create([
                'project_id' => $project->id,
                'material_request_id' => $materialRequest->id,
                'code' => $this->nextAftercareCode(),
                'type' => $type,
                'status' => 'warehouse_review',
                'requested_by' => $request->user()->id,
                'technical_note' => trim((string) $data['technical_note']),
                'requested_at' => now(),
            ]);

            $aftercare->items()->create([
                'material_item_id' => $sourceItem?->id,
                'product_id' => $productId ?: null,
                'warehouse_id' => $warehouseId ?: null,
                'item_name' => $itemName,
                'desired_item_name' => trim((string) ($data['desired_item_name'] ?? '')) ?: null,
                'quantity' => $quantity,
                'unit' => trim((string) ($data['unit'] ?? '')) ?: ((string) ($sourceItem?->unit ?: 'cái')),
                'condition' => $data['condition'] ?? null,
                'unit_cost_snapshot' => $allocation?->unit_cost,
                'stock_snapshot' => $stockSnapshot,
                'note' => trim((string) $data['technical_note']),
            ]);

            $this->history($project, 'Kỹ thuật tạo yêu cầu sau xuất kho '.$aftercare->code, [
                'aftercare_request_id' => $aftercare->id,
                'material_request_id' => $materialRequest->id,
                'type' => $type,
                'quantity' => $quantity,
            ]);

            return $aftercare;
        });

        return redirect()->route('project-test.show', [
            'project' => $project,
            'tab' => 'materials',
            'material_view' => 'return',
            'material_request' => $materialRequest->id,
        ])->with('success', 'Đã gửi yêu cầu '.$aftercare->code.' cho Kho kiểm tra.');
    }

    public function update(Request $request, Project $project, MaterialAftercareRequest $aftercare): RedirectResponse
    {
        $this->authorizeTechnical($request, $project);
        $this->assertAftercareProject($project, $aftercare);
        abort_unless($aftercare->status === 'revision', 422, 'Yêu cầu không còn ở bước Kỹ thuật điều chỉnh.');

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'desired_item_name' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', Rule::in(['new', 'used', 'damaged', 'missing'])],
            'technical_note' => ['required', 'string', 'max:3000'],
        ]);

        $aftercare->loadMissing('items.materialItem');
        $currentItem = $aftercare->items->first();
        if ($currentItem?->materialItem && in_array($aftercare->type, ['return', 'exchange', 'damaged'], true)) {
            $issuedQty = (float) ($currentItem->materialItem->issued_quantity ?: $currentItem->materialItem->quantity);
            $otherActiveQty = (float) DB::table('project_test_material_aftercare_items as ai')
                ->join('project_test_material_aftercare_requests as ar', 'ar.id', '=', 'ai.aftercare_request_id')
                ->where('ar.material_request_id', $aftercare->material_request_id)
                ->where('ai.material_item_id', $currentItem->material_item_id)
                ->where('ar.id', '!=', $aftercare->id)
                ->whereIn('ar.status', ['warehouse_review', 'revision', 'pending_manager', 'approved', 'processing'])
                ->sum('ai.quantity');
            abort_if((float) $data['quantity'] + $otherActiveQty > $issuedQty + 0.0001, 422, 'Số lượng đổi/trả đang yêu cầu vượt quá số đã xuất.');
        }

        DB::transaction(function () use ($request, $aftercare, $data, $project): void {
            $locked = MaterialAftercareRequest::query()->lockForUpdate()->findOrFail($aftercare->id);
            abort_unless($locked->status === 'revision', 422, 'Yêu cầu vừa được người khác xử lý.');

            $item = $locked->items()->firstOrFail();
            $item->update([
                'quantity' => (float) $data['quantity'],
                'desired_item_name' => trim((string) ($data['desired_item_name'] ?? '')) ?: null,
                'condition' => $data['condition'] ?? $item->condition,
                'note' => trim((string) $data['technical_note']),
            ]);
            $locked->update([
                'status' => 'warehouse_review',
                'technical_note' => trim((string) $data['technical_note']),
                'warehouse_reviewed_by' => null,
                'warehouse_reviewed_at' => null,
                'warehouse_note' => null,
                'manager_reviewed_by' => null,
                'manager_reviewed_at' => null,
                'manager_note' => null,
            ]);

            $this->history($project, 'Kỹ thuật cập nhật và gửi lại yêu cầu sau xuất kho '.$locked->code, [
                'aftercare_request_id' => $locked->id,
            ]);
        });

        return back()->with('success', 'Đã cập nhật và gửi lại Kho kiểm tra.');
    }

    public function warehouseReview(Request $request, Project $project, MaterialAftercareRequest $aftercare): RedirectResponse
    {
        $this->authorizeWarehouse($request);
        $this->assertAftercareProject($project, $aftercare);
        abort_unless($aftercare->status === 'warehouse_review', 422, 'Yêu cầu không còn ở bước Kho kiểm tra.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['send_manager', 'return_technical', 'reject'])],
            'warehouse_note' => ['required', 'string', 'max:3000'],
            'items' => ['nullable', 'array'],
            'items.*.replacement_product_id' => ['nullable', 'integer'],
            'items.*.replacement_warehouse_id' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($request, $project, $aftercare, $data): void {
            $locked = MaterialAftercareRequest::query()->with(['items', 'materialRequest'])->lockForUpdate()->findOrFail($aftercare->id);
            abort_unless($locked->status === 'warehouse_review', 422, 'Yêu cầu vừa được người khác xử lý.');

            foreach ($locked->items as $item) {
                // Tồn/giá vốn vật tư gốc đã xuất để Admin đánh giá đổi/trả/hư hỏng.
                if ($item->product_id && $item->warehouse_id) {
                    $item->stock_snapshot = $this->stockQty((int) $item->product_id, (int) $item->warehouse_id);
                    $item->unit_cost_snapshot = $this->estimatedUnitCost((int) $item->product_id, (int) $item->warehouse_id);
                }

                // Với cấp bổ sung/đổi mới, Kho phải ghép đúng SKU và kho trước khi gửi Admin.
                if (in_array($locked->type, ['additional', 'exchange'], true)) {
                    $payload = Arr::get($data, 'items.'.$item->id, []);
                    $replacementProductId = (int) Arr::get($payload, 'replacement_product_id', 0);
                    $replacementWarehouseId = (int) Arr::get($payload, 'replacement_warehouse_id', 0);

                    if ($data['decision'] === 'send_manager') {
                        abort_if($replacementProductId <= 0 || $replacementWarehouseId <= 0, 422, 'Kho phải chọn sản phẩm/SKU và kho cấp cho '.$item->item_name.'.');
                    }

                    if ($replacementProductId > 0) {
                        abort_unless(
                            DB::table('crm_product_catalog')->where('id', $replacementProductId)->where('is_active', 1)->exists(),
                            422,
                            'Sản phẩm thay thế không tồn tại hoặc đã ngừng dùng.'
                        );
                    }
                    if ($replacementWarehouseId > 0) {
                        abort_unless(DB::table('crm_warehouses')->where('id', $replacementWarehouseId)->exists(), 422, 'Kho cấp thay thế không tồn tại.');
                    }

                    $item->replacement_product_id = $replacementProductId ?: null;
                    $item->replacement_warehouse_id = $replacementWarehouseId ?: null;
                    $item->replacement_stock_snapshot = $replacementProductId > 0 && $replacementWarehouseId > 0
                        ? $this->stockQty($replacementProductId, $replacementWarehouseId)
                        : null;
                    $item->replacement_unit_cost_snapshot = $replacementProductId > 0 && $replacementWarehouseId > 0
                        ? $this->estimatedUnitCost($replacementProductId, $replacementWarehouseId)
                        : null;
                }

                $item->save();
            }

            $status = match ($data['decision']) {
                'send_manager' => 'pending_manager',
                'return_technical' => 'revision',
                default => 'rejected',
            };

            $locked->update([
                'status' => $status,
                'warehouse_reviewed_by' => $request->user()->id,
                'warehouse_reviewed_at' => now(),
                'warehouse_note' => trim((string) $data['warehouse_note']),
                'completed_at' => $status === 'rejected' ? now() : null,
            ]);

            $this->history($project, 'Kho xử lý yêu cầu sau xuất kho '.$locked->code, [
                'aftercare_request_id' => $locked->id,
                'decision' => $data['decision'],
                'to_status' => $status,
            ]);
        });

        return back()->with('success', match ($data['decision']) {
            'send_manager' => 'Đã gửi Admin/Quản lý phê duyệt cùng tồn kho và giá vốn tham chiếu.',
            'return_technical' => 'Đã trả Kỹ thuật bổ sung thông tin.',
            default => 'Đã từ chối yêu cầu.',
        });
    }

    public function managerReview(Request $request, Project $project, MaterialAftercareRequest $aftercare): RedirectResponse
    {
        $this->authorizeManager($request);
        $this->assertAftercareProject($project, $aftercare);
        abort_unless($aftercare->status === 'pending_manager', 422, 'Yêu cầu không còn ở bước Admin/Quản lý phê duyệt.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return_warehouse', 'return_technical', 'reject'])],
            'manager_note' => ['nullable', 'string', 'max:3000'],
        ]);
        if ($data['decision'] !== 'approve') {
            abort_if(trim((string) ($data['manager_note'] ?? '')) === '', 422, 'Bắt buộc nhập lý do khi trả lại hoặc từ chối.');
        }

        DB::transaction(function () use ($request, $project, $aftercare, $data): void {
            $locked = MaterialAftercareRequest::query()->with(['items.materialItem', 'materialRequest'])->lockForUpdate()->findOrFail($aftercare->id);
            abort_unless($locked->status === 'pending_manager', 422, 'Yêu cầu vừa được người khác xử lý.');

            $decision = (string) $data['decision'];
            $note = trim((string) ($data['manager_note'] ?? ''));

            if ($decision === 'approve') {
                $linkedRequest = null;
                if (in_array($locked->type, ['shortage', 'additional', 'exchange'], true)) {
                    $linkedRequest = $this->createLinkedMaterialRequest($locked, $request->user()->id);
                }

                $locked->update([
                    'status' => $linkedRequest ? 'processing' : 'approved',
                    'linked_material_request_id' => $linkedRequest?->id,
                    'manager_reviewed_by' => $request->user()->id,
                    'manager_reviewed_at' => now(),
                    'manager_note' => $note !== '' ? $note : 'Đã duyệt xử lý',
                ]);

                $this->history($project, 'Admin/Quản lý duyệt yêu cầu sau xuất kho '.$locked->code, [
                    'aftercare_request_id' => $locked->id,
                    'linked_material_request_id' => $linkedRequest?->id,
                    'type' => $locked->type,
                ]);
                return;
            }

            $status = match ($decision) {
                'return_warehouse' => 'warehouse_review',
                'return_technical' => 'revision',
                default => 'rejected',
            };
            $locked->update([
                'status' => $status,
                'manager_reviewed_by' => $request->user()->id,
                'manager_reviewed_at' => now(),
                'manager_note' => $note,
                'completed_at' => $status === 'rejected' ? now() : null,
            ]);

            $this->history($project, 'Admin/Quản lý trả lại yêu cầu sau xuất kho '.$locked->code, [
                'aftercare_request_id' => $locked->id,
                'decision' => $decision,
                'to_status' => $status,
            ]);
        });

        return back()->with('success', match ($data['decision']) {
            'approve' => in_array($aftercare->type, ['shortage', 'additional', 'exchange'], true)
                ? 'Đã duyệt và tạo phiếu vật tư bổ sung để Kho xử lý.'
                : 'Đã duyệt để Kho xử lý thu hồi.',
            'return_warehouse' => 'Đã trả Kho kiểm tra lại.',
            'return_technical' => 'Đã trả Kỹ thuật điều chỉnh.',
            default => 'Đã từ chối yêu cầu.',
        });
    }

    public function processReturn(Request $request, Project $project, MaterialAftercareRequest $aftercare): RedirectResponse
    {
        $this->authorizeWarehouse($request);
        $this->assertAftercareProject($project, $aftercare);
        abort_unless(in_array($aftercare->type, ['return', 'exchange', 'damaged'], true), 422, 'Loại yêu cầu này không có bước thu hồi vật tư.');
        abort_unless(in_array($aftercare->status, ['approved', 'processing'], true), 422, 'Yêu cầu chưa được Admin/Quản lý phê duyệt.');

        $data = $request->validate([
            'disposition' => ['required', Rule::in(['restock', 'quarantine', 'scrap'])],
            'warehouse_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.serial_unit_ids' => ['nullable', 'array'],
            'items.*.serial_unit_ids.*' => ['integer'],
            'process_note' => ['required', 'string', 'max:3000'],
        ]);

        abort_unless(DB::table('crm_warehouses')->where('id', (int) $data['warehouse_id'])->exists(), 422, 'Kho nhận lại không tồn tại.');

        DB::transaction(function () use ($request, $project, $aftercare, $data): void {
            $locked = MaterialAftercareRequest::query()
                ->with(['items.materialItem.allocations', 'linkedMaterialRequest'])
                ->lockForUpdate()
                ->findOrFail($aftercare->id);
            abort_unless(in_array($locked->status, ['approved', 'processing'], true), 422, 'Yêu cầu vừa được người khác xử lý.');
            abort_if($locked->return_processed_at, 422, 'Yêu cầu đã hoàn tất bước thu hồi.');

            $companyId = (int) ($project->company_id ?? 0);
            $eventId = $this->createInventoryEvent($request->user()->id, $locked, (string) $data['process_note']);

            foreach ($locked->items as $aftercareItem) {
                $payload = Arr::get($data, 'items.'.$aftercareItem->id);
                abort_unless(is_array($payload), 422, 'Thiếu dữ liệu xử lý cho '.$aftercareItem->item_name.'.');

                $quantity = $this->wholeQuantity((float) Arr::get($payload, 'quantity'), $aftercareItem->item_name);
                $remaining = (float) $aftercareItem->quantity - (float) $aftercareItem->processed_quantity;
                abort_if($quantity > $remaining + 0.0001, 422, 'Số lượng xử lý vượt quá số lượng được duyệt ở '.$aftercareItem->item_name.'.');

                $sourceItem = $aftercareItem->materialItem;
                $allocation = $sourceItem?->allocations?->first();
                $productId = (int) ($aftercareItem->product_id ?: $allocation?->product_id ?: $sourceItem?->product_id ?: 0);
                abort_if($productId <= 0, 422, 'Không xác định được sản phẩm gốc ở '.$aftercareItem->item_name.'.');

                $isSerialized = (bool) ($allocation?->is_serialized ?? false);
                $serialIds = collect(Arr::get($payload, 'serial_unit_ids', []))
                    ->map(fn ($id): int => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();

                if ($isSerialized) {
                    $originalIds = collect($allocation?->selected_serial_unit_ids ?? [])->map(fn ($id): int => (int) $id);
                    abort_if($serialIds->count() !== $quantity, 422, 'Phải chọn đúng '.$quantity.' serial ở '.$aftercareItem->item_name.'.');
                    abort_if($serialIds->diff($originalIds)->isNotEmpty(), 422, 'Có serial không thuộc phiếu xuất ban đầu.');
                }

                if ($data['disposition'] === 'restock') {
                    $this->increaseStock($productId, (int) $data['warehouse_id'], $companyId, $quantity);
                    $this->createReturnLot($productId, (int) $data['warehouse_id'], $companyId, $quantity, (float) ($aftercareItem->unit_cost_snapshot ?? $allocation?->unit_cost ?? 0), $locked, $request->user()->id);

                    if ($isSerialized) {
                        foreach ($serialIds as $serialId) {
                            DB::table('crm_serial_units')->where('id', $serialId)->update([
                                'warehouse_id' => (int) $data['warehouse_id'],
                                'updated_at' => now(),
                            ]);
                            DB::table('crm_serial_unit_states')->updateOrInsert(
                                ['serial_unit_id' => $serialId],
                                [
                                    'warehouse_id' => (int) $data['warehouse_id'],
                                    'company_id' => $companyId ?: null,
                                    'state' => 'in_stock',
                                    'last_event_id' => $eventId,
                                    'synced_at' => now(),
                                    'note' => 'Hoàn kho theo '.$locked->code,
                                ]
                            );
                        }
                    }
                } elseif ($isSerialized && $serialIds->isNotEmpty()) {
                    DB::table('crm_serial_unit_states')->whereIn('serial_unit_id', $serialIds->all())->update([
                        'state' => 'removed',
                        'warehouse_id' => null,
                        'last_event_id' => $eventId,
                        'synced_at' => now(),
                        'note' => ($data['disposition'] === 'quarantine' ? 'Cách ly' : 'Loại bỏ').' theo '.$locked->code,
                    ]);
                }

                $aftercareItem->update([
                    'warehouse_id' => (int) $data['warehouse_id'],
                    'processed_quantity' => (float) $aftercareItem->processed_quantity + $quantity,
                    'serials' => $serialIds->isNotEmpty() ? $serialIds->implode(',') : $aftercareItem->serials,
                    'note' => trim((string) $data['process_note']),
                ]);
            }

            $linkedIssued = ! $locked->linked_material_request_id || $locked->linkedMaterialRequest?->status === 'issued';
            $completed = $locked->type !== 'exchange' || $linkedIssued;
            $locked->update([
                'status' => $completed ? 'completed' : 'processing',
                'processed_by' => $request->user()->id,
                'return_disposition' => (string) $data['disposition'],
                'return_processed_at' => now(),
                'completed_at' => $completed ? now() : null,
                'warehouse_note' => trim((string) $data['process_note']),
            ]);

            $this->history($project, 'Kho xử lý thu hồi '.$locked->code, [
                'aftercare_request_id' => $locked->id,
                'disposition' => $data['disposition'],
                'inventory_event_id' => $eventId,
                'completed' => $completed,
            ]);
        });

        return back()->with('success', 'Đã ghi nhận xử lý thu hồi. Tồn kho chỉ tăng với hàng được chọn “Nhập lại kho”.');
    }

    private function createLinkedMaterialRequest(MaterialAftercareRequest $aftercare, int $userId): MaterialRequest
    {
        if ($aftercare->linked_material_request_id) {
            return MaterialRequest::query()->findOrFail($aftercare->linked_material_request_id);
        }

        $parent = $aftercare->materialRequest;
        $linked = MaterialRequest::create([
            'project_id' => $aftercare->project_id,
            'code' => $this->nextMaterialCode(),
            'request_kind' => $aftercare->type === 'exchange' ? 'exchange' : 'supplement',
            'parent_request_id' => $parent?->id,
            'aftercare_request_id' => $aftercare->id,
            'requested_by' => $aftercare->requested_by ?: $userId,
            'needed_at' => now()->toDateString(),
            'status' => 'warehouse_check',
            'warehouse_status' => 'waiting_match',
            'request_note' => 'Tạo từ yêu cầu sau xuất kho '.$aftercare->code.'. '.$aftercare->technical_note,
        ]);

        foreach ($aftercare->items as $aftercareItem) {
            $source = $aftercareItem->materialItem;
            $linkedProductId = $aftercare->type === 'shortage'
                ? ($aftercareItem->product_id ?: $source?->product_id)
                : ($aftercareItem->replacement_product_id ?: null);
            $linked->items()->create([
                'product_id' => $linkedProductId,
                'item_name' => trim((string) ($aftercareItem->desired_item_name ?: $aftercareItem->item_name)),
                'quantity' => (float) $aftercareItem->quantity,
                'unit' => $aftercareItem->unit ?: ($source?->unit ?: 'cái'),
                'issued_quantity' => 0,
                'serials' => null,
                'note' => 'Bổ sung/đổi theo '.$aftercare->code,
            ]);
        }

        return $linked;
    }

    private function nextAftercareCode(): string
    {
        $prefix = 'VT-SX-'.now()->format('Ym').'-';
        $last = MaterialAftercareRequest::query()->where('code', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('code');
        $number = $last ? ((int) substr((string) $last, -4)) + 1 : 1;
        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    private function nextMaterialCode(): string
    {
        $prefix = 'VT-'.now()->format('Ym').'-';
        $last = MaterialRequest::query()->where('code', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('code');
        $number = $last ? ((int) substr((string) $last, -4)) + 1 : 1;
        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    private function stockQty(int $productId, int $warehouseId): float
    {
        if (! Schema::hasTable('crm_product_stock')) {
            return 0;
        }
        return (float) DB::table('crm_product_stock')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->sum('qty');
    }

    private function estimatedUnitCost(int $productId, int $warehouseId): ?float
    {
        if (! Schema::hasTable('crm_product_stock_lots')) {
            return null;
        }

        $row = DB::table('crm_product_stock_lots')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_remaining', '>', 0)
            ->selectRaw('SUM(qty_remaining * COALESCE(NULLIF(actual_cost_after_vat, 0), NULLIF(cost_after_vat, 0), NULLIF(cost_before_vat, 0), 0)) / NULLIF(SUM(qty_remaining), 0) as unit_cost')
            ->first();

        return $row && $row->unit_cost !== null ? round((float) $row->unit_cost, 2) : null;
    }

    private function increaseStock(int $productId, int $warehouseId, int $companyId, int $quantity): void
    {
        abort_unless(Schema::hasTable('crm_product_stock'), 422, 'Hệ thống chưa có bảng tồn kho.');
        $query = DB::table('crm_product_stock')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where(function ($where) use ($companyId): void {
                $companyId > 0 ? $where->where('company_id', $companyId) : $where->whereNull('company_id');
            });
        $row = $query->lockForUpdate()->first();
        if ($row) {
            DB::table('crm_product_stock')->where('id', $row->id)->update([
                'qty' => (int) $row->qty + $quantity,
                'last_updated' => now(),
            ]);
            return;
        }
        DB::table('crm_product_stock')->insert([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'company_id' => $companyId ?: null,
            'qty' => $quantity,
            'serials_json' => null,
            'last_updated' => now(),
        ]);
    }

    private function createReturnLot(int $productId, int $warehouseId, int $companyId, int $quantity, float $unitCost, MaterialAftercareRequest $aftercare, int $userId): void
    {
        if (! Schema::hasTable('crm_product_stock_lots')) {
            return;
        }
        DB::table('crm_product_stock_lots')->insert([
            'product_id' => $productId,
            'company_id' => $companyId ?: null,
            'warehouse_id' => $warehouseId,
            'lot_code' => 'RETURN-'.$aftercare->code,
            'lot_name' => 'Hoàn kho công trình '.$aftercare->project?->code,
            'received_at' => now(),
            'qty_in' => $quantity,
            'qty_remaining' => $quantity,
            'cost_before_vat' => $unitCost,
            'cost_vat_percent' => 0,
            'cost_after_vat' => $unitCost,
            'extra_cost' => 0,
            'actual_cost_after_vat' => $unitCost,
            'source_type' => 'project_material_aftercare',
            'source_id' => $aftercare->id,
            'note' => 'Nhập lại kho theo '.$aftercare->code,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createInventoryEvent(int $userId, MaterialAftercareRequest $aftercare, string $note): ?int
    {
        if (! Schema::hasTable('crm_inventory_events')) {
            return null;
        }
        return (int) DB::table('crm_inventory_events')->insertGetId([
            'event_type' => 'project_return',
            'occurred_at' => now(),
            'created_by' => $userId,
            'note' => $aftercare->code.' · '.$note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function wholeQuantity(float $quantity, string $label): int
    {
        $rounded = (int) round($quantity);
        abort_if(abs($quantity - $rounded) > 0.00001, 422, 'Tồn kho hiện tại chỉ hỗ trợ số lượng nguyên. Hãy chỉnh số lượng của '.$label.'.');
        abort_if($rounded <= 0, 422, 'Số lượng phải lớn hơn 0.');
        return $rounded;
    }

    private function assertParent(Project $project, MaterialRequest $materialRequest): void
    {
        abort_unless((int) $materialRequest->project_id === (int) $project->id, 404);
    }

    private function assertAftercareProject(Project $project, MaterialAftercareRequest $aftercare): void
    {
        abort_unless((int) $aftercare->project_id === (int) $project->id, 404);
    }

    private function authorizeTechnical(Request $request, Project $project): void
    {
        $user = $request->user();
        abort_unless($user && $user->hasAnyRole(['admin', 'technical_manager', 'technical_leader', 'ky_thuat', 'technical', 'technician', 'technical_staff']), 403);
        abort_unless(Project::query()->visibleTo($user)->whereKey($project->id)->exists(), 403);
    }

    private function authorizeWarehouse(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->hasAnyRole(['admin', 'warehouse', 'kho']), 403);
    }

    private function authorizeManager(Request $request): void
    {
        $user = $request->user();
        $allowed = $user && $user->hasAnyRole(['admin', 'management', 'manager', 'technical_manager', 'technical_leader']);
        if (! $allowed && $user) {
            try {
                $allowed = $user->can('project-test.admin');
            } catch (\Throwable) {
                $allowed = false;
            }
        }
        abort_unless($allowed, 403);
    }

    private function history(Project $project, string $action, array $meta = []): void
    {
        History::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'from_status' => $project->status,
            'to_status' => $project->status,
            'note' => null,
            'meta' => $meta,
        ]);
    }
}
