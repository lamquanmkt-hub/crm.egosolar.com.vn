@extends('layouts.app')

@section('content')
@include('hr.office-supply-process._style')


{{-- VPP_RECEIVER_REAL_USERS_START --}}
@php
    $vppReceiverUsers = collect();

    try {
        $db = \Illuminate\Support\Facades\DB::class;
        $schema = \Illuminate\Support\Facades\Schema::class;

        $userQuery = $db::table('users as u')
            ->select('u.id', 'u.name', 'u.email')
            ->whereNotNull('u.name')
            ->where('u.name', '!=', '');

        if ($schema::hasColumn('users', 'deleted_at')) {
            $userQuery->whereNull('u.deleted_at');
        }

        if ($schema::hasColumn('users', 'status')) {
            $userQuery->where(function ($q) {
                $q->whereNull('u.status')
                  ->orWhereNotIn('u.status', ['inactive', 'resigned', 'terminated', 'ngung_hop_tac', 'da_nghi']);
            });
        }

        if ($schema::hasColumn('users', 'department_id') && $schema::hasTable('departments') && $schema::hasColumn('departments', 'name')) {
            $userQuery->leftJoin('departments as d', 'd.id', '=', 'u.department_id')
                ->addSelect('d.name as department_name');
        } else {
            $userQuery->addSelect($db::raw('NULL as department_name'));
        }

        if ($schema::hasColumn('users', 'position_id') && $schema::hasTable('positions') && $schema::hasColumn('positions', 'name')) {
            $userQuery->leftJoin('positions as p', 'p.id', '=', 'u.position_id')
                ->addSelect('p.name as position_name');
        } else {
            $userQuery->addSelect($db::raw('NULL as position_name'));
        }

        if ($schema::hasColumn('users', 'role')) {
            $userQuery->addSelect('u.role as direct_role');
        } else {
            $userQuery->addSelect($db::raw('NULL as direct_role'));
        }

        $usersRaw = $userQuery->orderBy('u.name')->get();

        $rolesByUser = collect();

        if (
            $usersRaw->count()
            && $schema::hasTable('model_has_roles')
            && $schema::hasTable('roles')
            && $schema::hasColumn('model_has_roles', 'model_id')
            && $schema::hasColumn('model_has_roles', 'role_id')
            && $schema::hasColumn('roles', 'name')
        ) {
            $rolesByUser = $db::table('model_has_roles as mr')
                ->join('roles as r', 'r.id', '=', 'mr.role_id')
                ->whereIn('mr.model_id', $usersRaw->pluck('id')->all())
                ->selectRaw('mr.model_id, GROUP_CONCAT(r.name SEPARATOR ", ") as role_names')
                ->groupBy('mr.model_id')
                ->pluck('role_names', 'model_id');
        }

        $vppReceiverUsers = $usersRaw->map(function ($u) use ($rolesByUser) {
            $role = $rolesByUser[$u->id] ?? ($u->direct_role ?? '');
            $position = $u->position_name ?? '';

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email ?? '',
                'department' => $u->department_name ?? '',
                'position' => $position,
                'role' => $role,
                'label' => trim($u->name . ' - ' . ($role ?: $position ?: 'Nhân sự') . (($u->department_name ?? '') ? ' - ' . $u->department_name : '')),
            ];
        })->values();
    } catch (\Throwable $e) {
        $vppReceiverUsers = collect();
    }
@endphp
{{-- VPP_RECEIVER_REAL_USERS_END --}}

<div class="vpp-page">
    @if(session('success'))
        <div class="vpp-alert">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="vpp-error">{{ $errors->first() }}</div>
    @endif

    <div class="vpp-mini-hero">
        <div>
            <h1 class="vpp-mini-title">Quản lý văn phòng phẩm</h1>
            <p class="vpp-mini-sub">Sổ VPP riêng của HR, không liên kết kho hàng chính.</p>
        </div>

        <div class="vpp-hero-actions">
            <button class="vpp-btn-outline" type="button" data-vpp-open="modalAddProduct">+ Thêm vật phẩm</button>
            <button class="vpp-btn-outline" type="button" data-vpp-open="modalImport">Nhập VPP</button>
            <button class="vpp-btn" type="button" data-vpp-open="modalAllocate">Cấp phát</button>
        </div>
    </div>

    <div class="vpp-stats">
        <div class="vpp-stat">
            <div class="vpp-stat-label">Loại vật phẩm</div>
            <div class="vpp-stat-value">{{ number_format($stats['products'] ?? 0) }}</div>
        </div>
        <div class="vpp-stat">
            <div class="vpp-stat-label">Tổng tồn VPP</div>
            <div class="vpp-stat-value">{{ number_format($stats['stock'] ?? 0, 0) }}</div>
        </div>
        <div class="vpp-stat">
            <div class="vpp-stat-label">Nhập tháng này</div>
            <div class="vpp-stat-value">{{ number_format($stats['in_month'] ?? 0, 0) }}</div>
        </div>
        <div class="vpp-stat">
            <div class="vpp-stat-label">Đã cấp tháng này</div>
            <div class="vpp-stat-value">{{ number_format($stats['out_month'] ?? 0, 0) }}</div>
        </div>
        <div class="vpp-stat">
            <div class="vpp-stat-label">Sắp hết</div>
            <div class="vpp-stat-value">{{ number_format($stats['low_stock'] ?? 0) }}</div>
        </div>
    </div>

    <div class="vpp-layout">
        <div>
            <div class="vpp-card">
                <div class="vpp-card-head">
                    <div>
                        <h2 class="vpp-card-title">Tồn văn phòng phẩm</h2>
                        <div class="vpp-card-note">Theo dõi số lượng còn lại của từng vật phẩm.</div>
                    </div>
                </div>

                <div class="vpp-card-body">
                    <div class="vpp-toolbar">
                        <form method="GET" class="vpp-search" action="{{ url('/nhan-su/quy-trinh-phan-bo-vpp') }}">
                            <input class="vpp-input" name="q" value="{{ request('q') }}" placeholder="Tìm vật phẩm...">
                            <button class="vpp-btn-soft" type="submit">Lọc</button>
                        </form>
                    </div>

                    @if(isset($products) && $products->count())
                        <div class="vpp-table-wrap">
                            <table class="vpp-table">
                                <thead>
                                    <tr>
                                        <th>Vật phẩm</th>
                                        <th>Mã</th>
                                        <th>ĐVT</th>
                                        <th>Tồn</th>
                                        <th>Cảnh báo</th>
                                        <th>Trạng thái</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($products as $p)
                                        <tr>
                                            <td>
                                                <strong>{{ $p->name }}</strong>
                                                @if($p->note)
                                                    <div class="vpp-muted">{{ $p->note }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $p->sku ?: '-' }}</td>
                                            <td>{{ $p->unit }}</td>
                                            <td><strong>{{ number_format($p->current_stock, 0) }}</strong></td>
                                            <td>{{ number_format($p->min_stock, 0) }}</td>
                                            <td>
                                                @if($p->current_stock <= $p->min_stock)
                                                    <span class="vpp-badge vpp-low">Sắp hết</span>
                                                @else
                                                    <span class="vpp-badge vpp-ok">Còn</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="vpp-action-row">
                                                    <button class="vpp-btn-soft" type="button" data-vpp-open="modalEditProduct{{ $p->id }}">Sửa</button>

                                                    <form method="POST" action="{{ url('/nhan-su/quy-trinh-phan-bo-vpp/xoa-vpp/'.$p->id) }}" onsubmit="return confirm('Xóa vật phẩm này? Lịch sử VPP riêng của vật phẩm này cũng sẽ bị xóa.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="vpp-btn-danger" type="submit">Xóa</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="vpp-empty">Chưa có vật phẩm nào. Bấm “+ Thêm vật phẩm” để tạo mới.</div>
                    @endif
                </div>
            </div>

            <div class="vpp-card">
                <div class="vpp-card-head">
                    <div>
                        <h2 class="vpp-card-title">Lịch sử nhập / cấp phát</h2>
                        <div class="vpp-card-note">Ghi nhận ai nhập, ai nhận, phòng ban nào lấy.</div>
                    </div>
                </div>

                <div class="vpp-card-body">
                    @if(isset($movements) && $movements->count())
                        <div class="vpp-table-wrap">
                            <table class="vpp-table">
                                <thead>
                                    <tr>
                                        <th>Thời gian</th>
                                        <th>Loại</th>
                                        <th>Vật phẩm</th>
                                        <th>SL</th>
                                        <th>Tồn trước</th>
                                        <th>Tồn sau</th>
                                        <th>Người nhận / Phòng ban</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($movements as $m)
                                        <tr>
                                            <td>{{ $m->moved_at ? date('d/m/Y H:i', strtotime($m->moved_at)) : '-' }}</td>
                                            <td>
                                                @if($m->type === 'in')
                                                    <span class="vpp-badge vpp-in">Nhập</span>
                                                @else
                                                    <span class="vpp-badge vpp-out">Cấp</span>
                                                @endif
                                            </td>
                                            <td>{{ $m->product_name }}</td>
                                            <td>{{ number_format($m->qty, 0) }} {{ $m->unit }}</td>
                                            <td>{{ number_format($m->before_qty, 0) }}</td>
                                            <td>{{ number_format($m->after_qty, 0) }}</td>
                                            <td>
                                                {{ $m->receiver_name ?: '-' }}
                                                @if($m->department_name)
                                                    <div class="vpp-muted">{{ $m->department_name }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="vpp-empty">Chưa có lịch sử nhập / cấp phát.</div>
                    @endif
                </div>
            </div>
        </div>

        <div>
            <div class="vpp-card">
                <div class="vpp-card-head">
                    <div>
                        <h2 class="vpp-card-title">Phiếu cấp phát gần nhất</h2>
                        <div class="vpp-card-note">Tra nhanh ai đã lấy vật phẩm gì.</div>
                    </div>
                </div>

                <div class="vpp-card-body">
                    @if(isset($requests) && $requests->count())
                        <div class="vpp-table-wrap">
                            <table class="vpp-table">
                                <thead>
                                    <tr>
                                        <th>Mã</th>
                                        <th>Người nhận</th>
                                        <th>SL</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requests as $r)
                                        <tr>
                                            <td><button class="vpp-code vpp-link-btn" type="button" data-vpp-view="{{ $r->id }}">{{ $r->code }}</button></td>
                                            <td>
                                                {{ $r->receiver_name ?: $r->requester_name }}
                                                <div class="vpp-muted">{{ $r->department_name }}</div>
                                            </td>
                                            <td>{{ number_format($r->total_issued ?? 0, 0) }}</td>
                                            <td>
                                                <button class="vpp-btn-soft" type="button" data-vpp-view="{{ $r->id }}">Xem</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="vpp-empty">Chưa có phiếu cấp phát.</div>
                    @endif
                </div>
            </div>

            <div class="vpp-card">
                <div class="vpp-card-head">
                    <div>
                        <h2 class="vpp-card-title">Ghi chú module</h2>
                        <div class="vpp-card-note">Module này chỉ lưu sổ VPP riêng.</div>
                    </div>
                </div>
                <div class="vpp-card-body">
                    <div class="vpp-muted">
                        Không liên kết kho hàng chính, không ảnh hưởng tồn kho sản phẩm, không ảnh hưởng đơn hàng.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="vpp-modal-backdrop" id="modalAddProduct">
    <div class="vpp-modal">
        <form method="POST" action="{{ url('/nhan-su/quy-trinh-phan-bo-vpp/them-vpp') }}">
            @csrf

            <div class="vpp-modal-head">
                <h3 class="vpp-modal-title">Thêm vật phẩm</h3>
                <button class="vpp-modal-close" type="button" data-vpp-close>×</button>
            </div>

            <div class="vpp-modal-body">
                <div class="vpp-form-grid">
                    <div class="vpp-field-full">
                        <label class="vpp-label">Tên vật phẩm *</label>
                        <input class="vpp-input" name="name" placeholder="Bút bi / Giấy A4 / Kẹp file..." required>
                    </div>

                    <div>
                        <label class="vpp-label">Mã</label>
                        <input class="vpp-input" name="sku" placeholder="VPP001">
                    </div>

                    <div>
                        <label class="vpp-label">Đơn vị *</label>
                        <input class="vpp-input" name="unit" value="cái" required>
                    </div>

                    <div>
                        <label class="vpp-label">Tồn ban đầu</label>
                        <input class="vpp-input" type="number" min="0" step="0.01" name="initial_stock" value="0">
                    </div>

                    <div>
                        <label class="vpp-label">Cảnh báo dưới</label>
                        <input class="vpp-input" type="number" min="0" step="0.01" name="min_stock" value="0">
                    </div>

                    <div class="vpp-field-full">
                        <label class="vpp-label">Ghi chú</label>
                        <textarea class="vpp-textarea" name="note"></textarea>
                    </div>
                </div>
            </div>

            <div class="vpp-modal-foot">
                <button class="vpp-btn-soft" type="button" data-vpp-close>Đóng</button>
                <button class="vpp-btn" type="submit">Lưu</button>
            </div>
        </form>
    </div>
</div>

<div class="vpp-modal-backdrop" id="modalImport">
    <div class="vpp-modal">
        <form method="POST" action="{{ url('/nhan-su/quy-trinh-phan-bo-vpp/nhap-vpp') }}">
            @csrf

            <div class="vpp-modal-head">
                <h3 class="vpp-modal-title">Nhập VPP</h3>
                <button class="vpp-modal-close" type="button" data-vpp-close>×</button>
            </div>

            <div class="vpp-modal-body">
                <label class="vpp-label">Vật phẩm</label>
                <select class="vpp-select" name="product_id">
                    <option value="">-- Chọn vật phẩm đã có --</option>
                    @foreach(($products ?? []) as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} - còn {{ number_format($p->current_stock,0) }} {{ $p->unit }}</option>
                    @endforeach
                </select>

                <div style="height:8px"></div>

                <label class="vpp-label">Hoặc vật phẩm mới</label>
                <input class="vpp-input" name="new_product_name" placeholder="Nhập nếu chưa có trong danh mục">

                <div style="height:8px"></div>

                <div class="vpp-form-grid">
                    <div>
                        <label class="vpp-label">ĐVT</label>
                        <input class="vpp-input" name="unit" value="cái">
                    </div>

                    <div>
                        <label class="vpp-label">Số lượng nhập *</label>
                        <input class="vpp-input" type="number" min="0.01" step="0.01" name="qty" required>
                    </div>
                </div>

                <div style="height:8px"></div>

                <label class="vpp-label">Ghi chú</label>
                <textarea class="vpp-textarea" name="note" placeholder="Nguồn mua / hóa đơn / ghi chú..."></textarea>

                <input type="hidden" name="reason" value="Nhập VPP">
            </div>

            <div class="vpp-modal-foot">
                <button class="vpp-btn-soft" type="button" data-vpp-close>Đóng</button>
                <button class="vpp-btn" type="submit">Lưu nhập</button>
            </div>
        </form>
    </div>
</div>

<div class="vpp-modal-backdrop" id="modalAllocate">
    <div class="vpp-modal large">
        <form method="POST" action="{{ url('/nhan-su/quy-trinh-phan-bo-vpp/cap-phat-vpp') }}">
            @csrf

            <div class="vpp-modal-head">
                <h3 class="vpp-modal-title">Cấp phát VPP</h3>
                <button class="vpp-modal-close" type="button" data-vpp-close>×</button>
            </div>

            <div class="vpp-modal-body">
                <div class="vpp-form-grid">
                    <div>
                        <label class="vpp-label">Phòng ban *</label>
                        <input class="vpp-input" name="department_name" placeholder="Sales / Marketing / Kỹ thuật..." required>
                    </div>

                    <div>
                        <label class="vpp-label">Người nhận *</label>
                        <input class="vpp-input" name="receiver_name" placeholder="Tên người nhận" required>
                    </div>

                    <div class="vpp-field-full">
                        <label class="vpp-label">Mục đích</label>
                        <textarea class="vpp-textarea" name="purpose" placeholder="VD: Phục vụ vận hành trong tháng"></textarea>
                    </div>
                </div>

                <div style="height:9px"></div>

                <label class="vpp-label">Danh sách vật phẩm *</label>
                <div id="allocateRows">
                    <div class="vpp-row">
                        <select class="vpp-select" name="product_id[]" required>
                            <option value="">Chọn vật phẩm</option>
                            @foreach(($products ?? []) as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} - còn {{ number_format($p->current_stock,0) }} {{ $p->unit }}</option>
                            @endforeach
                        </select>
                        <input class="vpp-input" type="number" min="0.01" step="0.01" name="qty[]" placeholder="SL" required>
                        <button class="vpp-remove" type="button" onclick="removeAllocateRow(this)">×</button>
                    </div>
                </div>

                <button class="vpp-btn-outline" type="button" onclick="addAllocateRow()">+ Thêm dòng</button>

                <div style="height:8px"></div>

                <label class="vpp-label">Ghi chú</label>
                <textarea class="vpp-textarea" name="note"></textarea>
            </div>

            <div class="vpp-modal-foot">
                <button class="vpp-btn-soft" type="button" data-vpp-close>Đóng</button>
                <button class="vpp-btn" type="submit">Lưu cấp phát</button>
            </div>
        </form>
    </div>
</div>

@foreach(($products ?? []) as $p)
<div class="vpp-modal-backdrop" id="modalEditProduct{{ $p->id }}">
    <div class="vpp-modal">
        <form method="POST" action="{{ url('/nhan-su/quy-trinh-phan-bo-vpp/sua-vpp/'.$p->id) }}">
            @csrf
            @method('PUT')

            <div class="vpp-modal-head">
                <h3 class="vpp-modal-title">Sửa vật phẩm</h3>
                <button class="vpp-modal-close" type="button" data-vpp-close>×</button>
            </div>

            <div class="vpp-modal-body">
                <div class="vpp-form-grid">
                    <div class="vpp-field-full">
                        <label class="vpp-label">Tên vật phẩm *</label>
                        <input class="vpp-input" name="name" value="{{ $p->name }}" required>
                    </div>

                    <div>
                        <label class="vpp-label">Mã</label>
                        <input class="vpp-input" name="sku" value="{{ $p->sku }}">
                    </div>

                    <div>
                        <label class="vpp-label">Đơn vị *</label>
                        <input class="vpp-input" name="unit" value="{{ $p->unit }}" required>
                    </div>

                    <div>
                        <label class="vpp-label">Cảnh báo dưới</label>
                        <input class="vpp-input" type="number" min="0" step="0.01" name="min_stock" value="{{ $p->min_stock }}">
                    </div>

                    <div class="vpp-field-full">
                        <label class="vpp-label">Ghi chú</label>
                        <textarea class="vpp-textarea" name="note">{{ $p->note }}</textarea>
                    </div>
                </div>
            </div>

            <div class="vpp-modal-foot">
                <button class="vpp-btn-soft" type="button" data-vpp-close>Đóng</button>
                <button class="vpp-btn" type="submit">Lưu sửa</button>
            </div>
        </form>
    </div>
</div>
@endforeach

<script>
document.querySelectorAll('[data-vpp-open]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = btn.getAttribute('data-vpp-open');
        var modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('active');
        }
    });
});

document.querySelectorAll('[data-vpp-close]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var modal = btn.closest('.vpp-modal-backdrop');
        if (modal) {
            modal.classList.remove('active');
        }
    });
});

document.querySelectorAll('.vpp-modal-backdrop').forEach(function(bg) {
    bg.addEventListener('click', function(e) {
        if (e.target === bg) {
            bg.classList.remove('active');
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.vpp-modal-backdrop.active').forEach(function(bg) {
            bg.classList.remove('active');
        });
    }
});

function addAllocateRow() {
    var box = document.getElementById('allocateRows');
    var first = box.querySelector('.vpp-row');
    var row = first.cloneNode(true);
    row.querySelector('select').value = '';
    row.querySelector('input').value = '';
    box.appendChild(row);
}

function removeAllocateRow(btn) {
    var box = document.getElementById('allocateRows');
    if (box.querySelectorAll('.vpp-row').length > 1) {
        btn.closest('.vpp-row').remove();
    }
}
</script>

<script>
document.querySelectorAll('.vpp-modal form').forEach(function(form) {
    form.addEventListener('submit', function() {
        var btn = form.querySelector('button[type="submit"]');
        if (btn && !btn.dataset.submitted) {
            btn.dataset.submitted = '1';
            btn.disabled = true;
            btn.style.opacity = '.65';
            btn.innerText = 'Đang lưu...';
        }
    });
});
</script>


<style>
.vpp-link-btn {
    border: 0;
    background: transparent;
    padding: 0;
    cursor: pointer;
}
.vpp-detail-head {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 10px;
}
.vpp-detail-box {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 11px;
    padding: 9px 10px;
}
.vpp-detail-label {
    font-size: 12px;
    color: #64748b;
    font-weight: 650;
    margin-bottom: 2px;
}
.vpp-detail-value {
    font-size: 13px;
    color: #0f172a;
    font-weight: 700;
}
@media(max-width: 800px) {
    .vpp-detail-head { grid-template-columns: 1fr; }
}
</style>

<div class="vpp-modal-backdrop" id="modalVoucherDetail">
    <div class="vpp-modal large">
        <div class="vpp-modal-head">
            <h3 class="vpp-modal-title" id="vppDetailTitle">Chi tiết phiếu cấp phát</h3>
            <button class="vpp-modal-close" type="button" data-vpp-close>×</button>
        </div>

        <div class="vpp-modal-body" id="vppDetailBody">
            <div class="vpp-empty">Đang tải dữ liệu...</div>
        </div>

        <div class="vpp-modal-foot">
            <button class="vpp-btn-soft" type="button" data-vpp-close>Đóng</button>
        </div>
    </div>
</div>

<script>
(function(){
    function escapeHtml(value) {
        if (value === null || value === undefined) return '-';
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function fmtNumber(value) {
        value = Number(value || 0);
        return value.toLocaleString('vi-VN', { maximumFractionDigits: 0 });
    }

    function fmtDate(value) {
        if (!value) return '-';
        var d = new Date(value);
        if (isNaN(d.getTime())) return escapeHtml(value);
        return d.toLocaleString('vi-VN', {
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    function openDetailModal(id) {
        var modal = document.getElementById('modalVoucherDetail');
        var body = document.getElementById('vppDetailBody');
        var title = document.getElementById('vppDetailTitle');

        if (!modal || !body) return;

        modal.classList.add('active');
        title.innerText = 'Chi tiết phiếu cấp phát';
        body.innerHTML = '<div class="vpp-empty">Đang tải dữ liệu...</div>';

        fetch('/nhan-su/quy-trinh-phan-bo-vpp/popup-chi-tiet/' + id, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) {
            if (!res.ok) throw new Error('Không tải được dữ liệu');
            return res.json();
        })
        .then(function(data) {
            var r = data.request || {};
            var items = data.items || [];
            var movements = data.movements || [];

            title.innerText = 'Chi tiết phiếu ' + (r.code || '');

            var itemRows = items.length ? items.map(function(i) {
                var issued = i.issued_qty || i.hr_qty || i.requested_qty || 0;
                return '<tr>' +
                    '<td><strong>' + escapeHtml(i.item_name) + '</strong></td>' +
                    '<td>' + escapeHtml(i.unit || 'cái') + '</td>' +
                    '<td>' + fmtNumber(i.requested_qty) + '</td>' +
                    '<td>' + fmtNumber(issued) + '</td>' +
                    '<td>' + (i.current_stock === null || i.current_stock === undefined ? '-' : fmtNumber(i.current_stock)) + '</td>' +
                '</tr>';
            }).join('') : '<tr><td colspan="5"><div class="vpp-empty">Phiếu này chưa có vật phẩm.</div></td></tr>';

            var moveRows = movements.length ? movements.map(function(m) {
                return '<tr>' +
                    '<td>' + fmtDate(m.moved_at) + '</td>' +
                    '<td>' + (m.type === 'in' ? '<span class="vpp-badge vpp-in">Nhập</span>' : '<span class="vpp-badge vpp-out">Cấp</span>') + '</td>' +
                    '<td>' + escapeHtml(m.product_name) + '</td>' +
                    '<td>' + fmtNumber(m.qty) + ' ' + escapeHtml(m.unit || '') + '</td>' +
                    '<td>' + fmtNumber(m.before_qty) + '</td>' +
                    '<td>' + fmtNumber(m.after_qty) + '</td>' +
                    '<td>' + escapeHtml(m.user_name || 'Hệ thống') + '</td>' +
                '</tr>';
            }).join('') : '<tr><td colspan="7"><div class="vpp-empty">Chưa có lịch sử cho phiếu này.</div></td></tr>';

            body.innerHTML =
                '<div class="vpp-detail-head">' +
                    '<div class="vpp-detail-box"><div class="vpp-detail-label">Mã phiếu</div><div class="vpp-detail-value">' + escapeHtml(r.code) + '</div></div>' +
                    '<div class="vpp-detail-box"><div class="vpp-detail-label">Ngày cấp</div><div class="vpp-detail-value">' + fmtDate(r.created_at) + '</div></div>' +
                    '<div class="vpp-detail-box"><div class="vpp-detail-label">Người nhận</div><div class="vpp-detail-value">' + escapeHtml(r.receiver_name || r.requester_name) + '</div></div>' +
                    '<div class="vpp-detail-box"><div class="vpp-detail-label">Phòng ban</div><div class="vpp-detail-value">' + escapeHtml(r.department_name) + '</div></div>' +
                    '<div class="vpp-detail-box"><div class="vpp-detail-label">Mục đích</div><div class="vpp-detail-value">' + escapeHtml(r.purpose || '-') + '</div></div>' +
                    '<div class="vpp-detail-box"><div class="vpp-detail-label">Ghi chú</div><div class="vpp-detail-value">' + escapeHtml(r.note || '-') + '</div></div>' +
                '</div>' +

                '<h4 class="vpp-section-title">Vật phẩm đã cấp</h4>' +
                '<div class="vpp-table-wrap"><table class="vpp-table">' +
                    '<thead><tr><th>Vật phẩm</th><th>ĐVT</th><th>SL đề nghị</th><th>SL cấp</th><th>Tồn hiện tại</th></tr></thead>' +
                    '<tbody>' + itemRows + '</tbody>' +
                '</table></div>' +

                '<div style="height:12px"></div>' +

                '<h4 class="vpp-section-title">Lịch sử xử lý</h4>' +
                '<div class="vpp-table-wrap"><table class="vpp-table">' +
                    '<thead><tr><th>Thời gian</th><th>Loại</th><th>Vật phẩm</th><th>SL</th><th>Tồn trước</th><th>Tồn sau</th><th>Người thao tác</th></tr></thead>' +
                    '<tbody>' + moveRows + '</tbody>' +
                '</table></div>';
        })
        .catch(function(err) {
            body.innerHTML = '<div class="vpp-error">Không tải được chi tiết phiếu. Vui lòng thử lại.</div>';
        });
    }

    document.querySelectorAll('[data-vpp-view]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            openDetailModal(btn.getAttribute('data-vpp-view'));
        });
    });

    document.querySelectorAll('a[href*="/nhan-su/quy-trinh-phan-bo-vpp/"]').forEach(function(a) {
        a.addEventListener('click', function(e) {
            try {
                var url = new URL(a.href);
                var parts = url.pathname.split('/').filter(Boolean);
                var last = parts[parts.length - 1];

                if (/^\d+$/.test(last)) {
                    e.preventDefault();
                    openDetailModal(last);
                }
            } catch (err) {}
        });
    });
})();
</script>


<style>
/* FIX nút đóng popup chi tiết */
.vpp-modal-close,
[data-vpp-close] {
    pointer-events: auto !important;
    cursor: pointer !important;
    position: relative;
    z-index: 100002 !important;
}

.vpp-modal {
    position: relative;
    z-index: 100001 !important;
}
</style>

<script>
/* VPP_FORCE_CLOSE_MODAL_FIX */
(function () {
    if (window.__vppForceCloseModalFix) return;
    window.__vppForceCloseModalFix = true;

    function closeVppModal(el) {
        var modal = el ? el.closest('.vpp-modal-backdrop') : null;

        if (modal) {
            modal.classList.remove('active');
            return;
        }

        document.querySelectorAll('.vpp-modal-backdrop.active').forEach(function (bg) {
            bg.classList.remove('active');
        });
    }

    document.addEventListener('click', function (e) {
        var closeBtn = e.target.closest('[data-vpp-close], .vpp-modal-close');

        if (closeBtn) {
            e.preventDefault();
            e.stopPropagation();
            closeVppModal(closeBtn);
            return false;
        }

        if (e.target.classList && e.target.classList.contains('vpp-modal-backdrop')) {
            e.preventDefault();
            e.stopPropagation();
            e.target.classList.remove('active');
            return false;
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.vpp-modal-backdrop.active').forEach(function (bg) {
                bg.classList.remove('active');
            });
        }
    });
})();
</script>


<script>
/* VPP_REAL_RECEIVER_SELECT_START */
(function () {
    var receiverUsers = @json($vppReceiverUsers ?? []);

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function buildReceiverSelect(oldInput) {
        if (!oldInput || oldInput.dataset.replacedRealUser === '1') return;

        var select = document.createElement('select');
        select.className = oldInput.className || 'vpp-select';
        select.name = 'receiver_name';
        select.required = oldInput.required || true;
        select.autocomplete = 'off';
        select.dataset.realUserReceiver = '1';

        var first = document.createElement('option');
        first.value = '';
        first.textContent = receiverUsers.length ? '-- Chọn nhân sự thật --' : 'Chưa có danh sách nhân sự';
        first.disabled = true;
        first.selected = true;
        select.appendChild(first);

        receiverUsers.forEach(function (u) {
            var opt = document.createElement('option');
            opt.value = u.name || '';
            opt.textContent = u.label || u.name || '';
            opt.dataset.userId = u.id || '';
            opt.dataset.department = u.department || '';
            opt.dataset.role = u.role || '';
            opt.dataset.position = u.position || '';
            select.appendChild(opt);
        });

        select.addEventListener('change', function () {
            var selected = select.options[select.selectedIndex];
            var form = select.closest('form');

            if (!form || !selected) return;

            var departmentInput = form.querySelector('[name="department_name"]');

            if (departmentInput && selected.dataset.department && !departmentInput.value.trim()) {
                departmentInput.value = selected.dataset.department;
            }
        });

        oldInput.parentNode.replaceChild(select, oldInput);
    }

    function replaceReceiverInputs() {
        document.querySelectorAll('input[name="receiver_name"]').forEach(function (input) {
            buildReceiverSelect(input);
        });

        document.querySelectorAll('form').forEach(function (form) {
            form.setAttribute('autocomplete', 'off');
        });
    }

    replaceReceiverInputs();

    document.addEventListener('click', function () {
        setTimeout(replaceReceiverInputs, 100);
    });

    document.addEventListener('DOMContentLoaded', replaceReceiverInputs);
})();
/* VPP_REAL_RECEIVER_SELECT_END */
</script>

@endsection
