@extends('layouts.app')

@section('title', ($claim->claim_code ?: 'Đề xuất đổi hàng').' · Bảo hành')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-warranty-exchange-v1.css') }}?v={{ file_exists(public_path('css/technical-warranty-exchange-v1.css')) ? filemtime(public_path('css/technical-warranty-exchange-v1.css')) : time() }}">
@endsection

@section('content')
<?php
$statusTone = [
    'received' => 'blue',
    'eligibility_check' => 'blue',
    'diagnosing' => 'amber',
    'solution_proposed' => 'cyan',
    'pending_approval' => 'violet',
    'approved' => 'green',
    'waiting_stock' => 'orange',
    'replacing' => 'amber',
    'waiting_customer' => 'cyan',
    'completed' => 'green',
    'rejected' => 'red',
    'cancelled' => 'gray',
];
$stockTone = [
    'pending' => 'violet',
    'approved' => 'blue',
    'completed' => 'green',
    'cancelled' => 'gray',
];
$steps = [
    'pending_approval' => ['Duyệt đề xuất', 'bi-clipboard-check'],
    'approved' => ['Đã duyệt', 'bi-check2-circle'],
    'waiting_stock' => ['Kho chuẩn bị', 'bi-box-seam'],
    'replacing' => ['Đang thay thế', 'bi-arrow-repeat'],
    'waiting_customer' => ['Xác nhận khách', 'bi-person-check'],
    'completed' => ['Hoàn thành', 'bi-shield-check'],
];
$stepKeys = array_keys($steps);
$currentStepIndex = array_search($claim->status, $stepKeys, true);
if ($currentStepIndex === false) {
    $currentStepIndex = -1;
}
$allErrors = collect();
foreach ($errors->getBags() as $bag) {
    foreach ($bag->all() as $message) {
        $allErrors->push($message);
    }
}
$allErrors = $allErrors->unique()->values();
$site = $claim->site;
$order = $claim->order;
?>

<div class="wx-page">
    <div class="wx-shell">
        <?php if (session('success')): ?>
            <div class="wx-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>
        <?php endif; ?>

        <?php if (session('error')): ?>
            <div class="wx-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>
        <?php endif; ?>

        <?php if ($allErrors->isNotEmpty()): ?>
            <div class="wx-alert danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    <strong>Chưa thể cập nhật</strong>
                    <ul>
                        <?php foreach ($allErrors as $error): ?>
                            <li>{{ $error }}</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <header class="wx-detail-head">
            <div class="wx-detail-title">
                <a href="{{ route('ky-thuat.warranty-exchange.index') }}"><i class="bi bi-arrow-left"></i> Danh sách đề xuất</a>
                <div class="wx-kicker">HỒ SƠ ĐỔI HÀNG BẢO HÀNH</div>
                <h1>{{ $claim->claim_code ?: '#'.$claim->id }}</h1>
                <p>
                    <?php if ($site): ?>
                        {{ $site->project_code ? $site->project_code.' · ' : '' }}{{ $site->name }}
                    <?php elseif ($order && $order->order_code): ?>
                        Nguồn đơn hàng · {{ $order->order_code }}
                    <?php else: ?>
                        Chưa có công trình / đơn hàng
                    <?php endif; ?>
                </p>
            </div>
            <div class="wx-detail-badges">
                <span class="wx-pill {{ $statusTone[$claim->status] ?? 'gray' }}">{{ $statuses[$claim->status] ?? $claim->status }}</span>
                <span class="wx-pill mini">{{ $priorities[$claim->priority] ?? $claim->priority }}</span>
            </div>
        </header>

        <section class="wx-flow">
            <?php foreach ($steps as $key => $step): ?>
                <?php
                $idx = array_search($key, $stepKeys, true);
                $flowClass = '';
                if ($claim->status === $key) {
                    $flowClass = 'active';
                } elseif (($currentStepIndex >= 0 && $idx < $currentStepIndex) || $claim->status === 'completed') {
                    $flowClass = 'done';
                }
                ?>
                <div class="{{ $flowClass }}">
                    <span><i class="bi {{ $step[1] }}"></i></span>
                    <small>{{ $step[0] }}</small>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="wx-detail-grid">
            <main class="wx-detail-main">
                <section class="wx-panel wx-detail-card">
                    <div class="wx-panel-head">
                        <div><span>THIẾT BỊ LỖI</span><h2>Thông tin bảo hành</h2></div>
                        <?php if (\Illuminate\Support\Facades\Route::has('serial-warranty.index')): ?>
                            <a class="wx-btn ghost small" href="{{ route('serial-warranty.index', ['q' => $claim->serial_code]) }}"><i class="bi bi-upc-scan"></i>Tra cứu serial</a>
                        <?php endif; ?>
                    </div>
                    <div class="wx-info-grid">
                        <div><small>Serial lỗi</small><strong class="mono">{{ $claim->serial_code ?: '—' }}</strong></div>
                        <div><small>Sản phẩm</small><strong>{{ $device ? ($device->product_name ?: 'Chưa xác định') : 'Chưa xác định' }}</strong><span>{{ $device ? ($device->sku ?: '') : '' }}</span></div>
                        <div><small>Khách hàng</small><strong>{{ $device && $device->customer_name ? $device->customer_name : ($site && $site->contact_name ? $site->contact_name : '—') }}</strong><span>{{ $device && $device->customer_phone ? $device->customer_phone : ($site && $site->contact_phone ? $site->contact_phone : '') }}</span></div>
                        <div><small>Đơn hàng</small><strong>{{ $order && $order->order_code ? $order->order_code : ($device && $device->order_code ? $device->order_code : '—') }}</strong></div>
                        <div><small>Bảo hành</small><strong>{{ $device && $device->warranty_status === 'active' ? 'Đang hiệu lực' : ($device && $device->warranty_status ? $device->warranty_status : 'Chưa rõ') }}</strong><span>{{ $device && $device->warranty_start_at ? $device->warranty_start_at : '—' }} → {{ $device && $device->warranty_end_at ? $device->warranty_end_at : '—' }}</span></div>
                        <div><small>Serial thay thế</small><strong class="mono">{{ $claim->replacement_serial_code ?: 'Chưa xuất' }}</strong></div>
                        <div><small>Serial lỗi thu hồi</small><strong class="mono">{{ $claim->returned_serial_code ?: 'Chưa thu hồi' }}</strong></div>
                        <div><small>Người phụ trách</small><strong>{{ $claim->assignee ? ($claim->assignee->name ?: $claim->assigned_name ?: 'Chưa phân công') : ($claim->assigned_name ?: 'Chưa phân công') }}</strong></div>
                    </div>
                </section>

                <section class="wx-panel wx-detail-card">
                    <div class="wx-panel-head"><div><span>ĐÁNH GIÁ KỸ THUẬT</span><h2>Chẩn đoán &amp; phương án đổi</h2></div></div>
                    <div class="wx-text-block"><small>Hiện tượng / lỗi</small><p>{{ $claim->issue_description ?: '—' }}</p></div>
                    <div class="wx-text-block"><small>Chẩn đoán</small><p>{{ $claim->diagnosis ?: 'Chưa cập nhật' }}</p></div>
                    <div class="wx-text-block emphasis"><small>Phương án đề xuất</small><p>{{ $claim->proposed_solution ?: 'Chưa cập nhật' }}</p></div>
                    <?php if ($claim->resolution): ?>
                        <div class="wx-text-block success"><small>Kết quả xử lý</small><p>{{ $claim->resolution }}</p></div>
                    <?php endif; ?>
                    <?php if ($claim->approval_note): ?>
                        <div class="wx-text-block"><small>Ghi chú phê duyệt</small><p>{{ $claim->approval_note }}</p></div>
                    <?php endif; ?>
                </section>

                <section class="wx-panel wx-detail-card">
                    <div class="wx-panel-head"><div><span>MINH CHỨNG</span><h2>Hình ảnh / biên bản</h2><p>Ảnh lỗi, màn hình báo lỗi, biên bản kiểm tra hoặc PDF liên quan.</p></div></div>
                    <div class="wx-files">
                        <?php if ($attachments->isNotEmpty()): ?>
                            <?php foreach ($attachments as $file): ?>
                                <div class="wx-file">
                                    <span><i class="bi {{ str_contains((string) $file->mime_type, 'pdf') ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image' }}"></i></span>
                                    <div>
                                        <a target="_blank" href="{{ route('ky-thuat.warranty-exchange.evidence.download', ['claim' => $claim->id, 'attachment' => $file->id]) }}">{{ $file->original_name }}</a>
                                        <small>{{ number_format(((int) $file->file_size) / 1024, 0) }} KB · {{ $file->uploader_name ?: 'Không rõ người tải' }}</small>
                                    </div>
                                    <?php if ($canUpdate): ?>
                                        <form method="POST" action="{{ route('ky-thuat.warranty-exchange.evidence.destroy', ['claim' => $claim->id, 'attachment' => $file->id]) }}" onsubmit="return confirm('Gỡ minh chứng này khỏi phiếu?')">
                                            {!! csrf_field() !!}
                                            {!! method_field('DELETE') !!}
                                            <button title="Gỡ tệp"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="wx-empty compact"><i class="bi bi-images"></i><strong>Chưa có minh chứng</strong><span>Có thể bổ sung ảnh/PDF bên dưới.</span></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($canUpdate): ?>
                        <form class="wx-upload" method="POST" enctype="multipart/form-data" action="{{ route('ky-thuat.warranty-exchange.evidence.upload', ['claim' => $claim->id]) }}">
                            {!! csrf_field() !!}
                            <input type="file" name="evidence[]" multiple required accept="image/jpeg,image/png,image/webp,application/pdf">
                            <button class="wx-btn secondary" type="submit"><i class="bi bi-cloud-arrow-up"></i>Tải minh chứng</button>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="wx-panel wx-detail-card">
                    <div class="wx-panel-head"><div><span>KHO &amp; SERIAL</span><h2>Lịch sử xuất đổi / thu hồi</h2></div></div>
                    <div class="wx-movement-list">
                        <?php if ($claim->stockMovements->isNotEmpty()): ?>
                            <?php foreach ($claim->stockMovements as $movement): ?>
                                <article class="wx-movement">
                                    <div class="wx-move-icon"><i class="bi {{ $movement->movement_type === 'warranty_out' ? 'bi-box-arrow-up-right' : ($movement->movement_type === 'faulty_return' ? 'bi-box-arrow-in-down' : 'bi-arrow-left-right') }}"></i></div>
                                    <div class="wx-move-main">
                                        <strong>{{ $stockTypes[$movement->movement_type] ?? $movement->movement_type }}</strong>
                                        <span>{{ $movement->movement_code }} · {{ $movement->warehouse ? ($movement->warehouse->name ?: 'Chưa rõ kho') : 'Chưa rõ kho' }}</span>
                                        <code>{{ $movement->serial_code }}</code>
                                        <?php if ($movement->note): ?><small>{{ $movement->note }}</small><?php endif; ?>
                                    </div>
                                    <span class="wx-pill {{ $stockTone[$movement->status] ?? 'gray' }}">{{ $stockStatuses[$movement->status] ?? $movement->status }}</span>
                                    <?php if ($canStock && in_array($movement->status, ['pending', 'approved', 'cancelled'], true)): ?>
                                        <div class="wx-move-actions">
                                            <?php if ($movement->status === 'pending'): ?>
                                                <form method="POST" action="{{ route('projects-unified.maintenance.stock.status', ['movement' => $movement->id]) }}">
                                                    {!! csrf_field() !!}
                                                    <input type="hidden" name="status" value="approved">
                                                    <button class="wx-btn tiny secondary">Duyệt kho</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (in_array($movement->status, ['pending', 'approved'], true)): ?>
                                                <form method="POST" action="{{ route('projects-unified.maintenance.stock.status', ['movement' => $movement->id]) }}" onsubmit="return confirm('Hoàn tất nghiệp vụ kho này? Hệ thống sẽ cập nhật trạng thái serial/tồn kho.')">
                                                    {!! csrf_field() !!}
                                                    <input type="hidden" name="status" value="completed">
                                                    <button class="wx-btn tiny primary">Hoàn tất</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="wx-empty compact"><i class="bi bi-box-seam"></i><strong>Chưa phát sinh phiếu kho</strong><span>Phiếu phải được duyệt trước khi Kho xuất đổi.</span></div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>

            <aside class="wx-detail-side">
                <section class="wx-panel wx-side-card">
                    <div class="wx-panel-head"><div><span>HÀNH ĐỘNG TIẾP THEO</span><h2>Cập nhật xử lý</h2></div></div>
                    <?php if ($canUpdate): ?>
                        <form class="wx-stack-form" method="POST" action="{{ route('projects-unified.maintenance.claims.status', ['claim' => $claim->id]) }}">
                            {!! csrf_field() !!}
                            <label class="wx-field">
                                <span>Trạng thái</span>
                                <select name="status" required>
                                    <?php foreach ($allowedStatuses as $key): ?>
                                        <option value="{{ $key }}" <?php echo $claim->status === $key ? 'selected' : ''; ?>>{{ $statuses[$key] ?? $key }}</option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="wx-field"><span>Chẩn đoán</span><textarea name="diagnosis" rows="3">{{ $claim->diagnosis }}</textarea></label>
                            <label class="wx-field"><span>Phương án xử lý</span><textarea name="proposed_solution" rows="3">{{ $claim->proposed_solution }}</textarea></label>
                            <label class="wx-field"><span>Kết quả thực hiện</span><textarea name="resolution" rows="3" placeholder="Nhập kết quả khi đã đổi / kiểm tra lại thiết bị...">{{ $claim->resolution }}</textarea></label>
                            <?php if ($isManager): ?>
                                <label class="wx-field"><span>Ghi chú phê duyệt / trả lại</span><textarea name="approval_note" rows="2">{{ $claim->approval_note }}</textarea></label>
                            <?php endif; ?>
                            <?php if ($canViewCosts): ?>
                                <label class="wx-field"><span>Chi phí thực tế</span><input type="number" min="0" step="1000" name="actual_cost" value="{{ $claim->actual_cost }}"></label>
                            <?php endif; ?>
                            <button class="wx-btn primary full" type="submit"><i class="bi bi-check2-square"></i>Lưu &amp; chuyển trạng thái</button>
                        </form>
                    <?php else: ?>
                        <div class="wx-empty compact"><i class="bi bi-eye"></i><strong>Chế độ theo dõi</strong><span>Bạn có thể xem tiến độ nhưng không phải người xử lý phiếu.</span></div>
                    <?php endif; ?>
                </section>

                <?php if ($canStock && in_array($claim->status, ['approved', 'waiting_stock', 'replacing', 'waiting_customer'], true)): ?>
                    <section class="wx-panel wx-side-card">
                        <div class="wx-panel-head"><div><span>PHỐI HỢP KHO</span><h2>Xuất / thu hồi serial</h2><p>Kho là bộ phận chọn serial thay thế.</p></div></div>

                        <?php if (! $claim->replacement_serial_code): ?>
                            <form class="wx-stack-form wx-stock-form" method="POST" action="{{ route('projects-unified.maintenance.stock.store') }}">
                                {!! csrf_field() !!}
                                <input type="hidden" name="warranty_claim_id" value="{{ $claim->id }}">
                                <input type="hidden" name="movement_type" value="warranty_out">
                                <input type="hidden" name="return_to" value="warranty_exchange">
                                <label class="wx-field">
                                    <span>Kho xuất <b>*</b></span>
                                    <select name="warehouse_id" class="wx-warehouse-select" required>
                                        <option value="">Chọn kho...</option>
                                        <?php foreach ($warehouses as $warehouse): ?>
                                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="wx-field">
                                    <span>Serial thay thế <b>*</b></span>
                                    <input name="serial_code" list="wxReplacementSerials" required placeholder="Kho chọn serial đang tồn...">
                                    <datalist id="wxReplacementSerials">
                                        <?php foreach ($replacementCandidates as $candidate): ?>
                                            <option value="{{ $candidate->serial_code }}" data-warehouse="{{ $candidate->warehouse_id }}">{{ $candidate->warehouse_name }} · {{ $candidate->serial_code }}</option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <small class="wx-hint">Gợi ý hiển thị serial cùng sản phẩm đang ở trạng thái in_stock. Kho vẫn có thể nhập serial khác nếu được phép nghiệp vụ.</small>
                                </label>
                                <label class="wx-field"><span>Ghi chú kho</span><textarea name="note" rows="2" placeholder="Tình trạng hàng xuất, phụ kiện kèm theo..."></textarea></label>
                                <button class="wx-btn secondary full"><i class="bi bi-box-arrow-up-right"></i>Tạo phiếu xuất đổi</button>
                            </form>
                        <?php else: ?>
                            <div class="wx-done-box"><i class="bi bi-check-circle-fill"></i><div><small>Serial thay thế đã ghi nhận</small><strong>{{ $claim->replacement_serial_code }}</strong></div></div>
                        <?php endif; ?>

                        <?php if (! $claim->returned_serial_code): ?>
                            <form class="wx-stack-form wx-stock-form separated" method="POST" action="{{ route('projects-unified.maintenance.stock.store') }}">
                                {!! csrf_field() !!}
                                <input type="hidden" name="warranty_claim_id" value="{{ $claim->id }}">
                                <input type="hidden" name="movement_type" value="faulty_return">
                                <input type="hidden" name="return_to" value="warranty_exchange">
                                <label class="wx-field">
                                    <span>Kho nhận hàng lỗi <b>*</b></span>
                                    <select name="warehouse_id" required>
                                        <option value="">Chọn kho...</option>
                                        <?php foreach ($warehouses as $warehouse): ?>
                                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="wx-field"><span>Serial lỗi thu hồi</span><input name="serial_code" value="{{ $claim->serial_code }}" readonly></label>
                                <label class="wx-field"><span>Ghi chú tình trạng</span><textarea name="note" rows="2" placeholder="Móp, cháy, lỗi nguồn, phụ kiện thu hồi..."></textarea></label>
                                <button class="wx-btn ghost full"><i class="bi bi-box-arrow-in-down"></i>Tạo phiếu thu hồi lỗi</button>
                            </form>
                        <?php else: ?>
                            <div class="wx-done-box muted"><i class="bi bi-box-seam-fill"></i><div><small>Hàng lỗi đã thu hồi</small><strong>{{ $claim->returned_serial_code }}</strong></div></div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <section class="wx-panel wx-side-card">
                    <div class="wx-panel-head"><div><span>THÔNG TIN PHIẾU</span><h2>Kiểm soát hồ sơ</h2></div></div>
                    <div class="wx-side-facts">
                        <div><span>Người tạo</span><strong>{{ $claim->creator ? ($claim->creator->name ?: '—') : '—' }}</strong></div>
                        <div><span>Ngày gửi duyệt</span><strong>{{ $claim->submitted_at ? $claim->submitted_at->format('d/m/Y H:i') : '—' }}</strong></div>
                        <div><span>Người duyệt</span><strong>{{ $claim->approver ? ($claim->approver->name ?: 'Chưa duyệt') : 'Chưa duyệt' }}</strong></div>
                        <div><span>Ngày duyệt</span><strong>{{ $claim->approved_at ? $claim->approved_at->format('d/m/Y H:i') : '—' }}</strong></div>
                        <?php if ($canViewCosts): ?>
                            <div><span>Dự kiến</span><strong>{{ number_format((float) $claim->estimated_cost, 0, ',', '.') }} đ</strong></div>
                            <div><span>Thực tế</span><strong>{{ number_format((float) $claim->actual_cost, 0, ',', '.') }} đ</strong></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($claim->internal_note): ?>
                        <div class="wx-note"><i class="bi bi-journal-text"></i><p>{{ $claim->internal_note }}</p></div>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/technical-warranty-exchange-v1.js') }}?v={{ file_exists(public_path('js/technical-warranty-exchange-v1.js')) ? filemtime(public_path('js/technical-warranty-exchange-v1.js')) : time() }}"></script>
@endsection
