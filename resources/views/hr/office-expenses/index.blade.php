@extends('layouts.app')

@section('content')
<style>
    .ego-office-wrap{width:100%;max-width:100%;padding:0 2px 30px;font-size:12px;color:#0f172a}
    .ego-office-hero{position:relative;overflow:hidden;border-radius:24px;padding:20px 22px;margin-bottom:12px;color:#fff;background:radial-gradient(800px 260px at 92% 0%,rgba(45,212,191,.30),transparent 58%),linear-gradient(135deg,#020617,#075985 56%,#0f766e);box-shadow:0 18px 42px rgba(15,23,42,.16)}
    .ego-office-hero:after{content:"";position:absolute;right:-70px;bottom:-95px;width:220px;height:220px;border-radius:999px;background:rgba(255,255,255,.08)}
    .ego-office-head{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-end;gap:14px}
    .ego-office-title{margin:0;font-size:24px;line-height:1.12;font-weight:950;letter-spacing:-.035em}
    .ego-office-sub{margin-top:5px;font-size:12.5px;font-weight:700;color:rgba(255,255,255,.82)}
    .ego-office-actions{display:flex;gap:8px;flex-wrap:wrap}
    .ego-btn,.ego-btn-light,.ego-btn-danger{height:34px;border-radius:999px;border:1px solid transparent;padding:0 13px;display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:12px;font-weight:900;text-decoration:none;cursor:pointer;white-space:nowrap}
    .ego-btn{background:#0f766e;color:#fff;box-shadow:0 10px 22px rgba(15,118,110,.16)}
    .ego-btn-light{background:#fff;color:#0f172a;border-color:#dbe3ef}
    .ego-btn-danger{background:#fff1f2;color:#be123c;border-color:#fecdd3}
    .ego-office-stats{display:grid;grid-template-columns:1.25fr repeat(3,1fr);gap:10px;margin-bottom:12px}
    .ego-stat{position:relative;overflow:hidden;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:14px 15px;box-shadow:0 10px 25px rgba(15,23,42,.045);min-height:84px}
    .ego-stat:after{content:"";position:absolute;right:-24px;top:-28px;width:82px;height:82px;border-radius:999px;background:linear-gradient(135deg,rgba(15,118,110,.11),rgba(14,165,233,.08))}
    .ego-stat-label{position:relative;z-index:1;font-size:11px;text-transform:uppercase;letter-spacing:.035em;font-weight:950;color:#64748b}
    .ego-stat-value{position:relative;z-index:1;margin-top:8px;font-size:24px;line-height:1;font-weight:950;color:#0f766e;letter-spacing:-.04em}
    .ego-stat-money{font-size:26px;color:#0369a1}
    .ego-stat-note{position:relative;z-index:1;margin-top:6px;font-size:11.5px;font-weight:700;color:#64748b}
    .ego-office-grid{display:grid;grid-template-columns:minmax(360px,.95fr) minmax(0,1.35fr);gap:12px;align-items:start}
    .ego-panel{background:#fff;border:1px solid #e2e8f0;border-radius:20px;box-shadow:0 14px 34px rgba(15,23,42,.055);overflow:hidden}
    .ego-panel-head{padding:13px 15px;border-bottom:1px solid #edf2f7;background:linear-gradient(180deg,#fff,#f8fafc);display:flex;justify-content:space-between;align-items:center;gap:10px}
    .ego-panel-title{margin:0;font-size:15px;font-weight:950;color:#0f172a;letter-spacing:-.015em}
    .ego-panel-note{margin-top:3px;font-size:11.5px;font-weight:700;color:#64748b}
    .ego-panel-body{padding:14px 15px 16px}
    .ego-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px}
    .ego-field.full{grid-column:1/-1}
    .ego-label{display:block;margin-bottom:5px;font-size:11px;font-weight:950;color:#475569}
    .ego-input,.ego-select,.ego-textarea{width:100%;border:1px solid #dbe3ef;border-radius:12px;background:#fff;color:#0f172a;font-size:12px;font-weight:750;outline:none}
    .ego-input,.ego-select{height:36px;padding:0 10px}
    .ego-textarea{min-height:74px;padding:9px 10px;resize:vertical}
    .ego-input:focus,.ego-select:focus,.ego-textarea:focus{border-color:#14b8a6;box-shadow:0 0 0 3px rgba(20,184,166,.12)}
    .ego-filter{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .ego-filter .ego-input,.ego-filter .ego-select{width:auto;min-width:150px}
    .ego-cat-grid{display:grid;gap:8px;margin-top:12px}
    .ego-cat{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:center;padding:10px 11px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}
    .ego-cat-name{font-size:12px;font-weight:950;color:#0f172a}
    .ego-cat-count{margin-top:2px;font-size:11px;font-weight:700;color:#64748b}
    .ego-cat-money{font-size:12px;font-weight:950;color:#0369a1;white-space:nowrap}
    .ego-table-wrap{overflow:auto}
    .ego-table{width:100%;border-collapse:separate;border-spacing:0}
    .ego-table th{position:sticky;top:0;z-index:1;background:#f8fafc;color:#475569;font-size:11px;text-transform:uppercase;letter-spacing:.035em;font-weight:950;text-align:left;padding:10px;border-bottom:1px solid #e2e8f0;white-space:nowrap}
    .ego-table td{padding:10px;border-bottom:1px solid #edf2f7;font-size:12px;font-weight:700;color:#334155;vertical-align:top}
    .ego-table tr:hover td{background:#fbfdff}
    .ego-exp-title{font-weight:950;color:#0f172a}
    .ego-exp-note{margin-top:3px;color:#64748b;font-size:11.5px;font-weight:650;max-width:360px}
    .ego-chip{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:4px 8px;font-size:11px;font-weight:900;background:#ecfeff;color:#0e7490;white-space:nowrap}
    .ego-money{font-weight:950;color:#0f766e;white-space:nowrap}
    .ego-empty{padding:34px 16px;text-align:center;color:#64748b;font-size:12px;font-weight:800}
    .ego-alert{margin-bottom:12px;padding:10px 12px;border-radius:14px;font-size:12px;font-weight:850}
    .ego-alert-success{border:1px solid #bbf7d0;background:#f0fdf4;color:#166534}
    .ego-alert-danger{border:1px solid #fecdd3;background:#fff1f2;color:#be123c}
    @media(max-width:1120px){.ego-office-grid{grid-template-columns:1fr}.ego-office-stats{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:720px){.ego-office-head{display:block}.ego-office-actions{margin-top:12px}.ego-office-stats,.ego-form-grid{grid-template-columns:1fr}.ego-filter .ego-input,.ego-filter .ego-select,.ego-btn,.ego-btn-light,.ego-btn-danger{width:100%}}
</style>

<div class="ego-office-wrap">
    <div class="ego-office-hero">
        <div class="ego-office-head">
            <div>
                <h1 class="ego-office-title">Chi phí VP</h1>
                <div class="ego-office-sub">Quản lý chi phí văn phòng gọn, rõ, dễ kiểm soát theo tháng và hạng mục.</div>
            </div>
            <div class="ego-office-actions">
                <a class="ego-btn-light" href="{{ route('hr.operations.index') }}">HC & Vận Hành</a>
                <a class="ego-btn-light" href="{{ route('hr.records.index') }}">HS nhân sự</a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="ego-alert ego-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="ego-alert ego-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="ego-office-stats">
        <div class="ego-stat">
            <div class="ego-stat-label">Tổng chi phí tháng</div>
            <div class="ego-stat-value ego-stat-money">{{ number_format($total, 0, ',', '.') }} đ</div>
            <div class="ego-stat-note">Tháng {{ $month }}</div>
        </div>
        <div class="ego-stat">
            <div class="ego-stat-label">Số khoản chi</div>
            <div class="ego-stat-value">{{ $expenses->count() }}</div>
            <div class="ego-stat-note">Theo bộ lọc hiện tại</div>
        </div>
        <div class="ego-stat">
            <div class="ego-stat-label">Hạng mục cao nhất</div>
            <div class="ego-stat-value" style="font-size:18px">{{ $topCategory['label'] ?? '—' }}</div>
            <div class="ego-stat-note">{{ number_format($topCategory['total'] ?? 0, 0, ',', '.') }} đ</div>
        </div>
        <div class="ego-stat">
            <div class="ego-stat-label">Trung bình / khoản</div>
            <div class="ego-stat-value">{{ $expenses->count() ? number_format($total / $expenses->count(), 0, ',', '.') : 0 }}</div>
            <div class="ego-stat-note">đ / khoản chi</div>
        </div>
    </div>

    <div class="ego-office-grid">
        <div class="ego-panel">
            <div class="ego-panel-head">
                <div>
                    <h3 class="ego-panel-title">Thêm chi phí</h3>
                    <div class="ego-panel-note">Nhập nhanh khoản chi văn phòng.</div>
                </div>
            </div>

            <div class="ego-panel-body">
                <form method="POST" action="{{ route('hr.office-expenses.store') }}">
                    @csrf
                    <div class="ego-form-grid">
                        <div class="ego-field">
                            <label class="ego-label">Ngày chi</label>
                            <input class="ego-input" type="date" name="expense_date" value="{{ now()->toDateString() }}" required>
                        </div>

                        <div class="ego-field">
                            <label class="ego-label">Hạng mục</label>
                            <select class="ego-select" name="category" required>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ego-field full">
                            <label class="ego-label">Nội dung chi</label>
                            <input class="ego-input" name="title" placeholder="Ví dụ: Mua giấy in, nước uống, gửi hồ sơ..." required>
                        </div>

                        <div class="ego-field full">
                            <label class="ego-label">Số tiền</label>
                            <input class="ego-input" name="amount" placeholder="Ví dụ: 250000 hoặc 250.000" required>
                        </div>

                        <div class="ego-field full">
                            <label class="ego-label">Ghi chú</label>
                            <textarea class="ego-textarea" name="note" placeholder="Ghi chú thêm nếu cần..."></textarea>
                        </div>

                        <div class="ego-field full">
                            <button class="ego-btn" type="submit">+ Thêm chi phí</button>
                        </div>
                    </div>
                </form>

                <div style="margin-top:14px;padding-top:14px;border-top:1px solid #edf2f7">
                    <div class="ego-label">Thêm hạng mục chi phí</div>
                    <form method="POST" action="{{ route('hr.office-expenses.categories.store') }}" style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px">
                        @csrf
                        <input class="ego-input" name="name" placeholder="Ví dụ: Internet, điện nước, gửi xe..." required>
                        <button class="ego-btn" type="submit">+ Hạng mục</button>
                    </form>
                </div>

                <div class="ego-cat-grid">
                    @foreach($categoryStats as $cat)
                        <div class="ego-cat">
                            <div>
                                <div class="ego-cat-name">{{ $cat['label'] }}</div>
                                <div class="ego-cat-count">{{ $cat['count'] }} khoản</div>
                            </div>

                            <div style="display:flex;align-items:center;gap:8px">
                                <div class="ego-cat-money">{{ number_format($cat['total'], 0, ',', '.') }} đ</div>

                                @php
                                    $catId = null;
                                    if (\Illuminate\Support\Facades\Schema::hasTable('hr_office_expense_categories')) {
                                        $catId = \Illuminate\Support\Facades\DB::table('hr_office_expense_categories')->where('slug', $cat['key'])->value('id');
                                    }
                                @endphp

                                @if(($cat['count'] ?? 0) == 0 && $catId)
                                    <form method="POST" action="{{ route('hr.office-expenses.categories.destroy', $catId) }}" onsubmit="return confirm('Xóa hạng mục này?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="ego-btn-danger" type="submit" style="height:28px;padding:0 9px">Xóa</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="ego-panel">
            <div class="ego-panel-head">
                <div>
                    <h3 class="ego-panel-title">Danh sách chi phí</h3>
                    <div class="ego-panel-note">Lọc theo tháng và hạng mục để kiểm soát nhanh.</div>
                </div>

                <form class="ego-filter" method="GET" action="{{ route('hr.office-expenses.index') }}">
                    <input class="ego-input" type="month" name="month" value="{{ $month }}">
                    <select class="ego-select" name="category">
                        <option value="">Tất cả hạng mục</option>
                        @foreach($categories as $key => $label)
                            <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="ego-btn-light" type="submit">Lọc</button>
                </form>
            </div>

            <div class="ego-table-wrap">
                <table class="ego-table">
                    <thead>
                        <tr>
                            <th>Ngày</th>
                            <th>Nội dung</th>
                            <th>Hạng mục</th>
                            <th>Số tiền</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expenses as $expense)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($expense->expense_date)->format('d/m/Y') }}</td>
                                <td>
                                    <div class="ego-exp-title">{{ $expense->title }}</div>
                                    @if($expense->note)
                                        <div class="ego-exp-note">{{ $expense->note }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="ego-chip">{{ $categories[$expense->category] ?? $expense->category }}</span>
                                </td>
                                <td class="ego-money">{{ number_format($expense->amount, 0, ',', '.') }} đ</td>
                                <td>
                                    <form method="POST" action="{{ route('hr.office-expenses.destroy', $expense->id) }}" onsubmit="return confirm('Xóa khoản chi này?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="ego-btn-danger" type="submit">Xóa</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="ego-empty">Chưa có chi phí văn phòng trong bộ lọc này.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
