@php
    $proposalStatusLabels = [
        'SUBMITTED' => 'Chờ Trưởng phòng Kỹ thuật thẩm định',
        'NEEDS_REVISION' => 'Kỹ thuật cần bổ sung',
        'TECHNICAL_APPROVED' => 'Đã thẩm định · Chờ Kho',
        'PARTIALLY_ALLOCATED' => 'Kho đang đối chiếu',
        'WAREHOUSE_ALLOCATED' => 'Kho đã đối chiếu · Chờ Admin',
        'ADMIN_APPROVED' => 'Admin đã duyệt · Chờ tạo đơn xuất',
        'READY_FOR_EXPORT' => 'Đã tạo đơn · Chờ xuất kho',
        'EXPORTED' => 'Đã giao và giám sát xác nhận',
        'CANCELLED' => 'Đã hủy',
    ];
    $proposalTone = [
        'SUBMITTED' => 'purple', 'NEEDS_REVISION' => 'orange', 'TECHNICAL_APPROVED' => 'blue',
        'PARTIALLY_ALLOCATED' => 'orange', 'WAREHOUSE_ALLOCATED' => 'teal',
        'ADMIN_APPROVED' => 'blue', 'READY_FOR_EXPORT' => 'green',
        'EXPORTED' => 'green', 'CANCELLED' => 'muted',
    ];
    $proposalTypeLabels = [
        'INITIAL' => 'Vật tư ban đầu',
        'ADDITIONAL' => 'Vật tư phát sinh thi công',
        'REPLACEMENT' => 'Vật tư thay thế bảo hành',
    ];
    $paymentMethodLabels = ['bank_transfer'=>'Chuyển khoản','cash'=>'Tiền mặt','card'=>'Thẻ','other'=>'Khác'];
    $hasMaterialAccess = $canProposeMaterials || $canApproveMaterials || $canWarehouse || $canManage;
    $defaultWorkspace = request('workspace') === 'finance' && $canSeeFinance
        ? 'finance'
        : ((!$hasMaterialAccess && $canSeeFinance) ? 'finance' : 'materials');
@endphp

<div class="pw22-workspace" data-pw22-workspace data-default-workspace="{{ $defaultWorkspace }}">
    @if($hasMaterialAccess && $canSeeFinance)
        <div class="pw22-switcher">
            <button type="button" class="is-active" data-pw22-switch="materials"><i class="bi bi-box-seam"></i>Vật tư dự án</button>
            <button type="button" data-pw22-switch="finance"><i class="bi bi-cash-stack"></i>Tài chính dự án</button>
        </div>
    @endif

    @if($hasMaterialAccess)
    <div class="pw22-subpane {{ $defaultWorkspace === 'materials' ? 'is-active' : '' }}" data-pw22-subpane="materials">
        <div class="pw22-head">
            <div>
                <span class="pw2-kicker">Luồng ngang theo quy trình 7 bước</span>
                <h2>{{ $canWarehouse && !$canProposeMaterials ? 'Chuẩn bị & xuất vật tư' : 'Vật tư dự án' }}</h2>
                <p>Người phụ trách lập phiếu → Trưởng phòng Kỹ thuật thẩm định → Kho đối chiếu → Admin duyệt bắt buộc → Kho xuất → Đội thi công và Giám sát xác nhận.</p>
            </div>
            <div class="pw22-actions">
                @if($canProposeMaterials)
                    <button type="button" class="pw2-btn pw2-btn-primary" data-pw22-modal-open="proposal"><i class="bi bi-plus-circle"></i>Đề xuất vật tư</button>
                @endif
                @if(Route::has('material-requests.index') && ($canWarehouse || $canManage))
                    <a class="pw2-btn pw2-btn-soft" href="{{ route('material-requests.index', ['site_id'=>$site->id]) }}"><i class="bi bi-box-arrow-up-right"></i>Mở module Kho</a>
                @endif
            </div>
        </div>

        <div class="pw22-kpis">
            <article><span><i class="bi bi-clipboard-data"></i></span><small>Tổng đề xuất</small><strong>{{ $materialKpis['total'] ?? 0 }}</strong></article>
            <article><span class="purple"><i class="bi bi-hourglass-split"></i></span><small>Chờ xác nhận</small><strong>{{ $materialKpis['awaiting'] ?? 0 }}</strong></article>
            <article><span class="orange"><i class="bi bi-boxes"></i></span><small>Kho đang xử lý</small><strong>{{ $materialKpis['warehouse'] ?? 0 }}</strong></article>
            <article><span class="green"><i class="bi bi-truck"></i></span><small>Chờ/đã xuất</small><strong>{{ $materialKpis['ready'] ?? 0 }}</strong></article>
        </div>

        @if($materialProposals->isEmpty())
            <div class="pw22-empty"><span><i class="bi bi-box-seam"></i></span><strong>Chưa có đề xuất vật tư</strong><p>Kỹ sư tạo đề xuất theo thông số và số lượng cần dùng; Kho sẽ chọn SKU thực tế sau khi được duyệt.</p></div>
        @else
            <div class="pw22-proposals">
                @foreach($materialProposals as $proposal)
                    @php
                        $proposalItems = $materialProposalItems->get($proposal->id, collect());
                        $status = strtoupper((string) $proposal->status);
                        $proposalType = strtoupper((string) ($proposal->proposal_type ?? 'INITIAL'));
                    @endphp
                    <article class="pw22-proposal">
                        <header>
                            <div>
                                <div class="pw22-proposal-code">{{ $proposalTypeLabels[$proposalType] ?? $proposalType }} · Đề xuất #{{ $proposal->id }} · {{ $proposal->created_at ? \Illuminate\Support\Carbon::parse($proposal->created_at)->format('d/m/Y H:i') : '—' }}</div>
                                <h3>{{ $proposal->purpose ?: 'Nhu cầu vật tư dự án' }}</h3>
                                <p>Tạo bởi <strong>{{ $proposal->creator_name ?: '—' }}</strong>@if($proposal->needed_at) · Cần ngày {{ \Illuminate\Support\Carbon::parse($proposal->needed_at)->format('d/m/Y') }}@endif</p>
                            </div>
                            <span class="pw2-table-badge {{ $proposalTone[$status] ?? 'muted' }}">{{ $proposalStatusLabels[$status] ?? $status }}</span>
                        </header>

                        <div class="pw22-item-table">
                            <div class="pw22-item-head"><span>Yêu cầu kỹ thuật</span><span>Số lượng</span><span>Kho ghép hàng</span><span>Tình trạng</span></div>
                            @foreach($proposalItems as $item)
                                <div class="pw22-item-row">
                                    <div><strong>{{ $item->requested_name }}</strong><small>{{ $item->requested_spec ?: 'Chưa ghi thông số' }}</small>@if($item->is_critical)<em>Thiết bị/vật tư trọng yếu</em>@endif</div>
                                    <div><strong>{{ rtrim(rtrim(number_format((float)$item->requested_qty, 2, ',', '.'), '0'), ',') }} {{ $item->requested_unit }}</strong><small>{{ $item->need_date ? 'Cần '.\Illuminate\Support\Carbon::parse($item->need_date)->format('d/m/Y') : 'Theo tiến độ dự án' }}</small></div>
                                    <div>
                                        @if($item->selected_product_name)
                                            <strong>{{ $item->selected_product_name }}</strong><small>{{ $item->selected_product_sku ?: '' }}{{ $item->selected_warehouse_name ? ' · '.$item->selected_warehouse_name : '' }}</small>
                                        @else
                                            <span class="pw22-muted">Kho chưa chọn sản phẩm</span>
                                        @endif
                                    </div>
                                    <div><span class="pw22-mini-status {{ $item->selected_product_id ? 'green' : 'orange' }}">{{ $item->selected_product_id ? 'Đã ghép hàng' : 'Chờ xử lý' }}</span></div>
                                </div>
                            @endforeach
                        </div>

                        @if($proposal->note || $proposal->approval_note || $proposal->warehouse_note || $proposal->admin_approval_note || $proposal->recipient_confirmed_at || $proposal->supervisor_confirmed_at)
                            <div class="pw22-notes">
                                @if($proposal->note)<p><strong>Ghi chú kỹ thuật:</strong> {{ $proposal->note }}</p>@endif
                                @if($proposal->approval_note)<p><strong>Phản hồi duyệt:</strong> {{ $proposal->approval_note }}</p>@endif
                                @if($proposal->warehouse_note)<p><strong>Ghi chú Kho:</strong> {{ $proposal->warehouse_note }}</p>@endif
                                @if($proposal->admin_approval_note)<p><strong>Ý kiến Admin:</strong> {{ $proposal->admin_approval_note }}</p>@endif
                                @if($proposal->recipient_confirmed_at)<p><strong>Đội thi công:</strong> Đã nhận và kiểm đếm lúc {{ \Illuminate\Support\Carbon::parse($proposal->recipient_confirmed_at)->format('d/m/Y H:i') }}</p>@endif
                                @if($proposal->supervisor_confirmed_at)<p><strong>Giám sát:</strong> Đã xác nhận vật tư tập kết lúc {{ \Illuminate\Support\Carbon::parse($proposal->supervisor_confirmed_at)->format('d/m/Y H:i') }}</p>@endif
                            </div>
                        @endif

                        <footer>
                            <div>
                                @if($proposal->attachment_path)<a href="{{ asset('storage/'.$proposal->attachment_path) }}" target="_blank"><i class="bi bi-paperclip"></i>File BOQ/đính kèm</a>@endif
                            </div>
                            <div class="pw22-row-actions">
                                @if($canApproveMaterials && in_array($status, ['SUBMITTED','NEEDS_REVISION']))
                                    <form method="POST" action="{{ route('projects-unified.materials.proposal.approve', [$site, $proposal->id]) }}">@csrf<button class="pw22-btn success" type="submit"><i class="bi bi-check2-circle"></i>Duyệt kỹ thuật</button></form>
                                    <button type="button" class="pw22-btn warning" data-pw22-modal-open="return-{{ $proposal->id }}"><i class="bi bi-arrow-counterclockwise"></i>Yêu cầu bổ sung</button>
                                @endif
                                @if($canWarehouse && in_array($status, ['TECHNICAL_APPROVED','PARTIALLY_ALLOCATED','WAREHOUSE_ALLOCATED']))
                                    <button type="button" class="pw22-btn primary" data-pw22-modal-open="allocate-{{ $proposal->id }}"><i class="bi bi-boxes"></i>Chọn hàng thực tế</button>
                                @endif
                                @if($canAdminApproveMaterials && $status === 'WAREHOUSE_ALLOCATED')
                                    <button type="button" class="pw22-btn success" data-pw22-modal-open="admin-approve-{{ $proposal->id }}"><i class="bi bi-shield-check"></i>Admin duyệt</button>
                                @endif
                                @if($canWarehouse && $status === 'ADMIN_APPROVED')
                                    <form method="POST" action="{{ route('projects-unified.materials.proposal.material-request', [$site, $proposal->id]) }}">@csrf<button class="pw22-btn success" type="submit"><i class="bi bi-truck"></i>Tạo đơn xuất kho</button></form>
                                @endif
                                @if($canConfirmMaterialReceipt && in_array($status, ['READY_FOR_EXPORT','EXPORTED']) && empty($proposal->recipient_confirmed_at))
                                    <form method="POST" action="{{ route('projects-unified.materials.proposal.receipt-confirm', [$site, $proposal->id]) }}">@csrf<button class="pw22-btn primary" type="submit"><i class="bi bi-box-arrow-in-down"></i>Xác nhận đã nhận</button></form>
                                @endif
                                @if($canConfirmMaterialSupervisor && !empty($proposal->recipient_confirmed_at) && empty($proposal->supervisor_confirmed_at))
                                    <form method="POST" action="{{ route('projects-unified.materials.proposal.supervisor-confirm', [$site, $proposal->id]) }}">@csrf<button class="pw22-btn success" type="submit"><i class="bi bi-camera-check"></i>Giám sát xác nhận</button></form>
                                @endif
                            </div>
                        </footer>
                    </article>

                    @if($canApproveMaterials)
                    <div class="pw22-modal" data-pw22-modal="return-{{ $proposal->id }}" aria-hidden="true"><div class="pw22-modal-card"><header><div><span>Phản hồi đề xuất #{{ $proposal->id }}</span><h3>Yêu cầu kỹ thuật bổ sung</h3></div><button type="button" data-pw22-modal-close><i class="bi bi-x-lg"></i></button></header><form method="POST" action="{{ route('projects-unified.materials.proposal.return', [$site, $proposal->id]) }}">@csrf<label><span>Nội dung cần bổ sung</span><textarea name="revision_note" rows="5" required placeholder="Thông số còn thiếu, số lượng cần kiểm tra hoặc nội dung cần chỉnh..."></textarea></label><footer><button type="button" class="pw2-btn pw2-btn-soft" data-pw22-modal-close>Hủy</button><button type="submit" class="pw2-btn pw2-btn-primary">Gửi yêu cầu</button></footer></form></div></div>
                    @endif

                    @if($canAdminApproveMaterials)
                    <div class="pw22-modal" data-pw22-modal="admin-approve-{{ $proposal->id }}" aria-hidden="true"><div class="pw22-modal-card"><header><div><span>Phê duyệt bắt buộc</span><h3>Admin duyệt phiếu vật tư #{{ $proposal->id }}</h3><p>Kho chỉ được tạo đơn xuất sau khi phiếu này được duyệt.</p></div><button type="button" data-pw22-modal-close><i class="bi bi-x-lg"></i></button></header><form method="POST" action="{{ route('projects-unified.materials.proposal.admin-approve', [$site, $proposal->id]) }}">@csrf<label><span>Ý kiến duyệt</span><textarea name="admin_approval_note" rows="4" placeholder="Phạm vi duyệt, lưu ý khi xuất, phần hàng cần mua bổ sung..."></textarea></label><footer><button type="button" class="pw2-btn pw2-btn-soft" data-pw22-modal-close>Hủy</button><button type="submit" class="pw2-btn pw2-btn-primary"><i class="bi bi-shield-check"></i>Duyệt cho Kho xuất</button></footer></form></div></div>
                    @endif

                    @if($canWarehouse)
                    <div class="pw22-modal pw22-modal-wide" data-pw22-modal="allocate-{{ $proposal->id }}" aria-hidden="true"><div class="pw22-modal-card"><header><div><span>Kho xử lý đề xuất #{{ $proposal->id }}</span><h3>Chọn sản phẩm và kho xuất thực tế</h3><p>Giữ nguyên nhu cầu kỹ thuật; sản phẩm thay thế phải ghi rõ lý do.</p></div><button type="button" data-pw22-modal-close><i class="bi bi-x-lg"></i></button></header><form method="POST" action="{{ route('projects-unified.materials.proposal.allocate', [$site, $proposal->id]) }}">@csrf<div class="pw22-allocation-list">@foreach($proposalItems as $item)<div class="pw22-allocation-item"><input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}"><div class="pw22-allocation-request"><strong>{{ $item->requested_name }}</strong><span>{{ $item->requested_spec }}</span><small>Cần {{ rtrim(rtrim(number_format((float)$item->requested_qty,2,',','.'),'0'),',') }} {{ $item->requested_unit }}</small></div><label><span>Sản phẩm/SKU thực tế</span><div class="pw22-product-picker" data-wf2-product-picker data-search-url="{{ route('projects-unified.materials.products.search') }}"><input type="hidden" name="items[{{ $loop->index }}][product_id]" value="{{ $item->selected_product_id }}" data-wf2-product-id required><div class="pw22-product-search"><i class="bi bi-search"></i><input type="search" value="{{ $item->selected_product_name ? $item->selected_product_name.($item->selected_product_sku ? ' · '.$item->selected_product_sku : '') : '' }}" placeholder="Tìm tên sản phẩm, SKU hoặc mã vạch..." autocomplete="off" data-wf2-product-search><button type="button" data-wf2-product-clear title="Bỏ chọn"><i class="bi bi-x-lg"></i></button></div><div class="pw22-product-results" data-wf2-product-results></div></div></label><label><span>Kho xuất</span><select name="items[{{ $loop->index }}][warehouse_id]" required><option value="">Chọn kho...</option>@foreach($warehouseOptions as $warehouse)<option value="{{ $warehouse->id }}" @selected((int)$item->selected_warehouse_id===(int)$warehouse->id)>{{ $warehouse->name }}{{ $warehouse->location ? ' · '.$warehouse->location : '' }}</option>@endforeach</select></label><label><span>Số lượng ghép</span><input type="number" name="items[{{ $loop->index }}][selected_qty]" min="0.01" step="0.01" value="{{ $item->selected_qty ?: $item->requested_qty }}" required></label><label class="full"><span>Lý do thay thế/tương đương</span><input type="text" name="items[{{ $loop->index }}][substitution_reason]" value="{{ $item->substitution_reason }}" placeholder="Để trống nếu đúng vật tư yêu cầu"></label><label class="pw22-check"><input type="checkbox" name="items[{{ $loop->index }}][serial_required]" value="1" @checked($item->serial_required)><span>Quản lý serial khi xuất</span></label></div>@endforeach</div><label><span>Ghi chú của Kho</span><textarea name="warehouse_note" rows="3" placeholder="Tình trạng tồn, hàng thiếu, thời gian sẵn sàng..."></textarea></label><footer><button type="button" class="pw2-btn pw2-btn-soft" data-pw22-modal-close>Hủy</button><button type="submit" class="pw2-btn pw2-btn-primary"><i class="bi bi-save"></i>Lưu ghép hàng</button></footer></form></div></div>
                    @endif
                @endforeach
            </div>
        @endif

        <article class="pw2-card pw22-linked-orders">
            <div class="pw2-card-heading"><div><span class="pw2-kicker">Liên kết Kho</span><h2>Đơn vật tư & xuất kho</h2><p>Đề xuất sau khi Kho ghép hàng được tạo thành đơn vật tư để xử lý xuất kho.</p></div></div>
            @if($materials->isEmpty())
                <div class="pw22-inline-empty">Chưa có đơn vật tư được tạo từ dự án này.</div>
            @else
                <div class="pw2-table-wrap"><table class="pw2-table"><thead><tr><th>Mã đơn</th><th>Trạng thái</th><th>Ghi chú</th>@if($canSeeFinance)<th>Giá trị</th>@endif<th>Cập nhật</th><th></th></tr></thead><tbody>@foreach($materials as $item)<tr><td><strong>#{{ $item->id }}</strong></td><td><span class="pw2-table-badge orange">{{ $item->status ?? 'Đang xử lý' }}</span></td><td>{{ $item->note ?? '—' }}</td>@if($canSeeFinance)<td>{{ $money($item->total_cost ?? 0) }}</td>@endif<td>{{ !empty($item->updated_at) ? \Illuminate\Support\Carbon::parse($item->updated_at)->format('d/m/Y H:i') : '—' }}</td><td>@if(Route::has('material-requests.show'))<a class="pw22-link" href="{{ route('material-requests.show', $item->id) }}">Mở đơn</a>@endif</td></tr>@endforeach</tbody></table></div>
            @endif
        </article>
    </div>
    @endif

    @if($canSeeFinance)
    <div class="pw22-subpane {{ $defaultWorkspace === 'finance' ? 'is-active' : '' }}" data-pw22-subpane="finance">
        <div class="pw22-head">
            <div><span class="pw2-kicker">Admin & Kế toán</span><h2>Tài chính dự án</h2><p>Lập kế hoạch thu, ghi nhận từng lần thanh toán, đính kèm chứng từ và tự cập nhật công nợ.</p></div>
            @if($canRecordPayment)<div class="pw22-actions"><button type="button" class="pw2-btn pw2-btn-soft" data-pw22-modal-open="payment-term"><i class="bi bi-calendar2-plus"></i>Thêm đợt thu</button><button type="button" class="pw2-btn pw2-btn-primary" data-pw22-modal-open="payment"><i class="bi bi-cash-coin"></i>Ghi nhận thanh toán</button></div>@endif
        </div>

        @if($finance)
        <div class="pw22-finance-kpis">
            <article><small>Giá trị hợp đồng</small><strong>{{ $money($finance['contract']) }}</strong><span>100% kế hoạch</span></article>
            <article class="received"><small>Đã thu</small><strong>{{ $money($finance['received']) }}</strong><span>{{ $finance['contract'] > 0 ? round($finance['received']/$finance['contract']*100,1) : 0 }}% hợp đồng</span></article>
            <article class="debt"><small>Công nợ còn lại</small><strong>{{ $money($finance['debt']) }}</strong><span>Cần theo dõi thu tiền</span></article>
            <article><small>Lợi nhuận tạm tính</small><strong>{{ $money($finance['profit']) }}</strong><span>Hợp đồng trừ chi phí</span></article>
        </div>
        @endif

        <div class="pw22-finance-grid">
            <article class="pw2-card">
                <div class="pw2-card-heading"><div><span class="pw2-kicker">Kế hoạch thanh toán</span><h2>Các đợt thu theo hợp đồng</h2></div></div>
                @if($paymentTerms->isEmpty())<div class="pw22-inline-empty">Chưa có kế hoạch thanh toán.</div>@else<div class="pw22-payment-terms">@foreach($paymentTerms as $term)@php $paid=(float)($paymentTermPaid[$term->id]??0);$termAmount=(float)$term->amount;$pct=$termAmount>0?min(100,round($paid/$termAmount*100)):0;@endphp<div class="pw22-term"><div><strong>{{ $term->name }}</strong><small>{{ $term->due_date ? 'Hạn '.\Illuminate\Support\Carbon::parse($term->due_date)->format('d/m/Y') : 'Chưa đặt hạn' }} · {{ $term->percent ? rtrim(rtrim(number_format((float)$term->percent,2,',','.'),'0'),',').'%' : 'Theo số tiền' }}</small></div><div class="pw22-term-money"><strong>{{ $money($paid) }} / {{ $money($termAmount) }}</strong><div><i style="width:{{ $pct }}%"></i></div><small>{{ $pct }}% · {{ $term->status }}</small></div></div>@endforeach</div>@endif
            </article>

            <article class="pw2-card">
                <div class="pw2-card-heading"><div><span class="pw2-kicker">Lịch sử thực thu</span><h2>Các lần ghi nhận thanh toán</h2></div></div>
                @if($paymentRecords->isEmpty())<div class="pw22-inline-empty">Chưa có khoản thanh toán được ghi nhận.</div>@else<div class="pw22-payment-list">@foreach($paymentRecords as $record)<div class="pw22-payment"><span><i class="bi bi-check-circle-fill"></i></span><div><strong>{{ $money($record->amount) }}</strong><small>{{ $record->term_name ?: 'Không gắn đợt' }} · {{ \Illuminate\Support\Carbon::parse($record->paid_at)->format('d/m/Y') }}</small><p>{{ $paymentMethodLabels[$record->payment_method] ?? $record->payment_method }}{{ $record->transaction_reference ? ' · Mã '.$record->transaction_reference : '' }} · {{ $record->creator_name ?: '—' }}</p></div>@if($record->attachment_path)<a href="{{ asset('storage/'.$record->attachment_path) }}" target="_blank"><i class="bi bi-paperclip"></i></a>@endif</div>@endforeach</div>@endif
            </article>
        </div>
    </div>
    @endif
</div>

@if($canProposeMaterials)
<div class="pw22-modal pw22-modal-wide" data-pw22-modal="proposal" aria-hidden="true"><div class="pw22-modal-card"><header><div><span>Luồng vật tư dùng chung</span><h3>Tạo đề xuất vật tư dự án</h3><p>Chọn đúng loại phiếu. Vật tư phát sinh phải kèm biên bản giải trình; vật tư thay thế phải xác định phạm vi bảo hành.</p></div><button type="button" data-pw22-modal-close><i class="bi bi-x-lg"></i></button></header><form method="POST" enctype="multipart/form-data" action="{{ route('projects-unified.materials.proposal.store', $site) }}" data-pw22-proposal-form>@csrf<div class="pw22-form-grid"><label><span>Loại đề xuất</span><select name="proposal_type" required data-pw22-proposal-type><option value="INITIAL">Vật tư ban đầu trước thi công</option><option value="ADDITIONAL">Vật tư phát sinh trong thi công</option><option value="REPLACEMENT">Vật tư thay thế khi bảo hành</option></select></label><label><span>Mức ưu tiên</span><select name="priority" required><option value="normal">Bình thường</option><option value="high">Cao</option><option value="urgent">Khẩn cấp</option></select></label><label><span>Ngày cần chung</span><input type="date" name="needed_at"></label><label><span>Phạm vi bảo hành</span><select name="warranty_scope"><option value="">Không áp dụng</option><option value="in_scope">Trong phạm vi bảo hành</option><option value="out_of_scope">Ngoài phạm vi bảo hành</option><option value="pending_assessment">Chờ đánh giá</option></select></label><label class="full"><span>Mục đích/hạng mục sử dụng</span><input type="text" name="purpose" placeholder="VD: Thi công hệ thống inverter khu A"></label><label class="full"><span>Ghi chú chung</span><textarea name="note" rows="3" placeholder="Yêu cầu thay thế tương đương, điều kiện kỹ thuật đặc biệt..."></textarea></label><label class="full"><span>File BOQ/bản vẽ đính kèm</span><input type="file" name="attachment"></label></div><div class="pw22-items-builder"><div class="pw22-builder-head"><div><strong>Danh sách vật tư yêu cầu</strong><small>Tối thiểu một dòng</small></div><button type="button" data-pw22-add-item><i class="bi bi-plus-lg"></i>Thêm dòng</button></div><div data-pw22-items><div class="pw22-builder-row" data-pw22-item><label><span>Tên vật tư/thiết bị</span><input name="items[0][name]" required placeholder="VD: Inverter 50 kW"></label><label><span>Thông số yêu cầu</span><input name="items[0][spec]" placeholder="3 pha, hybrid, IP65..."></label><label><span>Số lượng</span><input type="number" min="0.01" step="0.01" name="items[0][qty]" required></label><label><span>ĐVT</span><input name="items[0][unit]" required placeholder="bộ, cái, mét..."></label><label><span>Ngày cần</span><input type="date" name="items[0][need_date]"></label><label><span>Ghi chú dòng</span><input name="items[0][note]"></label><label class="pw22-check"><input type="checkbox" name="items[0][is_critical]" value="1"><span>Vật tư trọng yếu</span></label><button type="button" class="pw22-remove" data-pw22-remove-item title="Xóa dòng"><i class="bi bi-trash"></i></button></div></div></div><footer><button type="button" class="pw2-btn pw2-btn-soft" data-pw22-modal-close>Hủy</button><button type="submit" class="pw2-btn pw2-btn-primary"><i class="bi bi-send"></i>Gửi đề xuất</button></footer></form></div></div>
@endif

@if($canRecordPayment)
<div class="pw22-modal" data-pw22-modal="payment-term" aria-hidden="true"><div class="pw22-modal-card"><header><div><span>Kế hoạch thu tiền</span><h3>Thêm đợt thanh toán</h3></div><button type="button" data-pw22-modal-close><i class="bi bi-x-lg"></i></button></header><form method="POST" action="{{ route('projects-unified.finance.term.store', $site) }}">@csrf<div class="pw22-form-grid"><label class="full"><span>Tên đợt</span><input name="name" required placeholder="VD: Đợt 1 - Tạm ứng"></label><label><span>Tỷ lệ (%)</span><input type="number" name="percent" min="0" max="100" step="0.01"></label><label><span>Số tiền dự kiến</span><input type="number" name="amount" min="0" step="1000" required></label><label><span>Ngày dự kiến</span><input type="date" name="due_date"></label><label class="full"><span>Ghi chú</span><textarea name="note" rows="3"></textarea></label></div><footer><button type="button" class="pw2-btn pw2-btn-soft" data-pw22-modal-close>Hủy</button><button type="submit" class="pw2-btn pw2-btn-primary">Lưu đợt thu</button></footer></form></div></div>
<div class="pw22-modal" data-pw22-modal="payment" aria-hidden="true"><div class="pw22-modal-card"><header><div><span>Kế toán ghi nhận</span><h3>Ghi nhận thanh toán thực tế</h3><p>Một đợt có thể được thu nhiều lần.</p></div><button type="button" data-pw22-modal-close><i class="bi bi-x-lg"></i></button></header><form method="POST" enctype="multipart/form-data" action="{{ route('projects-unified.finance.payment.store', $site) }}">@csrf<div class="pw22-form-grid"><label class="full"><span>Thuộc đợt thanh toán</span><select name="payment_term_id"><option value="">Không gắn đợt</option>@foreach($paymentTerms as $term)<option value="{{ $term->id }}">{{ $term->name }} · {{ $money($term->amount) }}</option>@endforeach</select></label><label><span>Số tiền thực nhận</span><input type="number" name="amount" min="1" step="1000" required></label><label><span>Ngày nhận tiền</span><input type="date" name="paid_at" value="{{ now()->format('Y-m-d') }}" required></label><label><span>Phương thức</span><select name="payment_method" required><option value="bank_transfer">Chuyển khoản</option><option value="cash">Tiền mặt</option><option value="card">Thẻ</option><option value="other">Khác</option></select></label><label><span>Tài khoản nhận</span><select name="account_id"><option value="">Không chọn</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select></label><label><span>Mã giao dịch</span><input name="transaction_reference" placeholder="Số UNC/mã ngân hàng"></label><label><span>Người/chủ thể thanh toán</span><input name="payer_name" value="{{ $site->contact_name }}"></label><label class="full"><span>Nội dung</span><textarea name="note" rows="3"></textarea></label><label class="full"><span>Chứng từ</span><input type="file" name="attachment"></label></div><footer><button type="button" class="pw2-btn pw2-btn-soft" data-pw22-modal-close>Hủy</button><button type="submit" class="pw2-btn pw2-btn-primary"><i class="bi bi-check2-circle"></i>Ghi nhận thanh toán</button></footer></form></div></div>
@endif
