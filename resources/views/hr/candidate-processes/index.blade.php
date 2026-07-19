@extends('layouts.app')

@section('content')
<style>
    .candidate-page{padding:0 2px 28px}
    .candidate-head{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:14px}
    .candidate-title{margin:0;font-size:24px;font-weight:950;color:#0f172a;letter-spacing:-.03em}
    .candidate-sub{margin-top:5px;font-size:13px;font-weight:700;color:#64748b}
    .candidate-actions{display:flex;gap:8px;flex-wrap:wrap}
    .candidate-btn,.candidate-btn-outline,.candidate-btn-danger{height:35px;border-radius:11px;padding:0 13px;border:1px solid transparent;display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:12px;font-weight:900;text-decoration:none;cursor:pointer;white-space:nowrap}
    .candidate-btn{background:#0f766e;color:#fff;box-shadow:0 8px 18px rgba(15,118,110,.16)}
    .candidate-btn-outline{background:#fff;color:#0f172a;border-color:#dbe3ef}
    .candidate-btn-danger{background:#fff1f2;color:#be123c;border-color:#fecdd3}
    .candidate-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:12px}
    .candidate-stat{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:13px 14px;box-shadow:0 10px 24px rgba(15,23,42,.045);position:relative;overflow:hidden}
    .candidate-stat:after{content:"";position:absolute;right:-18px;top:-22px;width:70px;height:70px;border-radius:999px;background:linear-gradient(135deg,rgba(15,118,110,.10),rgba(14,165,233,.08))}
    .candidate-stat-label{position:relative;z-index:1;font-size:11px;font-weight:950;text-transform:uppercase;color:#64748b}
    .candidate-stat-value{position:relative;z-index:1;margin-top:7px;font-size:25px;font-weight:950;color:#0f766e}
    .candidate-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 14px 30px rgba(15,23,42,.055);overflow:hidden}
    .candidate-card-head{padding:13px 15px;border-bottom:1px solid #edf2f7;background:linear-gradient(180deg,#fff,#f8fafc)}
    .candidate-card-title{margin:0;font-size:15px;font-weight:950;color:#0f172a}
    .candidate-card-note{margin-top:3px;font-size:12px;font-weight:650;color:#64748b}
    .candidate-card-body{padding:14px 15px 16px}
    .candidate-input,.candidate-select,.candidate-textarea{width:100%;border:1px solid #dbe3ef;border-radius:10px;background:#fff;color:#0f172a;font-size:12px;font-weight:750;outline:none}
    .candidate-input,.candidate-select{height:36px;padding:0 10px}
    .candidate-textarea{min-height:66px;padding:10px;resize:vertical}
    .candidate-input:focus,.candidate-select:focus,.candidate-textarea:focus{border-color:#14b8a6;box-shadow:0 0 0 3px rgba(20,184,166,.12)}
    .candidate-table-wrap{border:1px solid #e2e8f0;border-radius:16px;overflow:auto;background:#fff}
    .candidate-table{width:100%;border-collapse:collapse;min-width:950px}
    .candidate-table th{background:#f1f5f9;color:#334155;font-size:11px;text-transform:uppercase;letter-spacing:.03em;font-weight:950;padding:10px;border-bottom:1px solid #e2e8f0;text-align:left}
    .candidate-table td{padding:9px 10px;border-bottom:1px solid #edf2f7;vertical-align:top;font-size:12px;font-weight:700;color:#0f172a}
    .candidate-table tr:last-child td{border-bottom:0}
    .candidate-alert{margin-bottom:12px;padding:10px 12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:14px;font-size:12px;font-weight:850}
    .candidate-error{margin-bottom:12px;padding:10px 12px;border:1px solid #fecaca;background:#fff1f2;color:#be123c;border-radius:14px;font-size:12px;font-weight:850}
    .candidate-empty{padding:30px 14px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;color:#64748b;font-size:12px;font-weight:750;text-align:center}
    .candidate-inline{display:flex;gap:6px;flex-wrap:wrap}
    .candidate-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.48);z-index:9998;display:none;align-items:center;justify-content:center;padding:18px}
    .candidate-modal-backdrop.show{display:flex}
    .candidate-modal{width:min(820px,100%);max-height:92vh;overflow:auto;background:#fff;border-radius:20px;box-shadow:0 30px 80px rgba(15,23,42,.28);border:1px solid #e2e8f0}
    .candidate-modal-head{position:sticky;top:0;background:linear-gradient(135deg,#ecfeff,#f0fdf4);padding:16px 18px;border-bottom:1px solid #dbeafe;display:flex;align-items:center;justify-content:space-between;gap:12px;z-index:2}
    .candidate-modal-title{margin:0;font-size:18px;font-weight:950;color:#0f172a}
    .candidate-modal-close{width:34px;height:34px;border-radius:10px;border:1px solid #dbe3ef;background:#fff;font-weight:950;cursor:pointer}
    .candidate-modal-body{padding:16px 18px}
    .candidate-modal-foot{padding:14px 18px;border-top:1px solid #edf2f7;display:flex;justify-content:flex-end;gap:8px;background:#f8fafc}
    .candidate-grid{display:grid;grid-template-columns:1fr 1fr;gap:11px}
    .candidate-field{display:flex;flex-direction:column;gap:6px}
    .candidate-field.full{grid-column:1/-1}
    .candidate-label{font-size:12px;font-weight:900;color:#475569}
    .round-box{border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;padding:10px}
    .round-add{display:grid;grid-template-columns:1fr 90px auto;gap:8px;margin-bottom:10px}
    .round-row{display:grid;grid-template-columns:90px 1fr auto;gap:7px;align-items:center;margin-top:7px}
    @media(max-width:900px){.candidate-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.candidate-grid{grid-template-columns:1fr}.candidate-field.full{grid-column:auto}}
    @media(max-width:720px){.candidate-head{display:block}.candidate-actions{margin-top:10px}.candidate-stats{grid-template-columns:1fr}.candidate-btn,.candidate-btn-outline,.candidate-btn-danger{width:100%}.round-add,.round-row{grid-template-columns:1fr}}
</style>

<div class="candidate-page">
    <div class="candidate-head">
        <div>
            <h1 class="candidate-title">Quy trình ứng viên</h1>
            <div class="candidate-sub">Theo dõi ứng viên theo từng đợt, người phụ trách, đánh giá và kết quả.</div>
        </div>
        <div class="candidate-actions">
            <a class="candidate-btn-outline" href="{{ route('hr.operations.index') }}">HC & Vận hành</a>
            <button type="button" class="candidate-btn-outline" onclick="openRoundModal()">Quản lý đợt</button>
            <button type="button" class="candidate-btn" onclick="openAddModal()">+ Thêm ứng viên</button>
        </div>
    </div>

    @if(session('success'))
        <div class="candidate-alert">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="candidate-error">{{ $errors->first() }}</div>
    @endif

    <div class="candidate-stats">
        <div class="candidate-stat">
            <div class="candidate-stat-label">Tổng ứng viên</div>
            <div class="candidate-stat-value">{{ $stats['total'] ?? 0 }}</div>
        </div>
        <div class="candidate-stat">
            <div class="candidate-stat-label">Số đợt</div>
            <div class="candidate-stat-value">{{ $stats['rounds'] ?? 0 }}</div>
        </div>
        <div class="candidate-stat">
            <div class="candidate-stat-label">Đã có kết quả</div>
            <div class="candidate-stat-value">{{ $stats['done'] ?? 0 }}</div>
        </div>
        <div class="candidate-stat">
            <div class="candidate-stat-label">Chưa có kết quả</div>
            <div class="candidate-stat-value">{{ $stats['pending'] ?? 0 }}</div>
        </div>
    </div>

    <div class="candidate-card">
        <div class="candidate-card-head">
            <h3 class="candidate-card-title">Danh sách ứng viên ứng viên</h3>
            <div class="candidate-card-note">Sửa trực tiếp trên từng dòng rồi bấm lưu.</div>
        </div>

        <div class="candidate-card-body">
            @if(($items ?? collect())->count())
                <div class="candidate-table-wrap">
                    <table class="candidate-table">
                        <thead>
                            <tr>
                                <th>Tên ứng viên</th>
                                <th>Lịch / Đợt</th>
                                <th>Phụ trách</th>
                                <th>Đánh giá</th>
                                <th>Kết quả</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td>
                                    <form id="candidateUpdate{{ $item->id }}" method="POST" action="{{ route('hr.candidate-processes.update', $item->id) }}">
                                        @csrf
                                        @method('PUT')
                                        <input class="candidate-input" name="candidate_name" value="{{ $item->candidate_name }}" required>
                                    </form>
                                </td>
                                <td>
                                    <select form="candidateUpdate{{ $item->id }}" class="candidate-select" name="round_id" required>
                                        @foreach($rounds as $round)
                                            <option value="{{ $round->id }}" @selected((int)$item->round_id === (int)$round->id)>{{ $round->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input form="candidateUpdate{{ $item->id }}" class="candidate-input" name="responsible_person" value="{{ $item->responsible_person }}">
                                </td>
                                <td>
                                    <textarea form="candidateUpdate{{ $item->id }}" class="candidate-textarea" name="evaluation">{{ $item->evaluation }}</textarea>
                                </td>
                                <td>
                                    <input form="candidateUpdate{{ $item->id }}" class="candidate-input" name="result" value="{{ $item->result }}" placeholder="VD: Đạt / Rớt / Chờ PV">
                                </td>
                                <td>
                                    <div class="candidate-inline">
                                        <button form="candidateUpdate{{ $item->id }}" class="candidate-btn" type="submit">Lưu</button>
                                        <form method="POST" action="{{ route('hr.candidate-processes.destroy', $item->id) }}" onsubmit="return confirm('Xoá ứng viên này khỏi quy trình?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="candidate-btn-danger" type="submit">Xoá</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="candidate-empty">Chưa có ứng viên nào. Bấm “+ Thêm ứng viên” để nhập dữ liệu.</div>
            @endif
        </div>
    </div>
</div>

<div id="addCandidateModal" class="candidate-modal-backdrop" onclick="closeAddModal(event)">
    <div class="candidate-modal" onclick="event.stopPropagation()">
        <div class="candidate-modal-head">
            <h3 class="candidate-modal-title">Thêm ứng viên vào quy trình</h3>
            <button class="candidate-modal-close" type="button" onclick="hideAddModal()">×</button>
        </div>

        <form method="POST" action="{{ route('hr.candidate-processes.store') }}">
            @csrf
            <div class="candidate-modal-body">
                <div class="candidate-grid">
                    <div class="candidate-field">
                        <label class="candidate-label">Tên ứng viên *</label>
                        <input class="candidate-input" name="candidate_name" placeholder="Nhập tên ứng viên" required>
                    </div>

                    <div class="candidate-field">
                        <label class="candidate-label">Lịch / Đợt *</label>
                        <select class="candidate-select" name="round_id" required>
                            @foreach($rounds as $round)
                                <option value="{{ $round->id }}">{{ $round->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="candidate-field">
                        <label class="candidate-label">Phụ trách</label>
                        <input class="candidate-input" name="responsible_person" placeholder="Tên người phụ trách">
                    </div>

                    <div class="candidate-field">
                        <label class="candidate-label">Kết quả</label>
                        <input class="candidate-input" name="result" placeholder="VD: Đạt / Rớt / Chờ PV">
                    </div>

                    <div class="candidate-field full">
                        <label class="candidate-label">Đánh giá</label>
                        <textarea class="candidate-textarea" name="evaluation" placeholder="Nhận xét, đánh giá ứng viên..."></textarea>
                    </div>
                </div>
            </div>

            <div class="candidate-modal-foot">
                <button class="candidate-btn-outline" type="button" onclick="hideAddModal()">Huỷ</button>
                <button class="candidate-btn" type="submit">+ Thêm ứng viên</button>
            </div>
        </form>
    </div>
</div>

<div id="roundModal" class="candidate-modal-backdrop" onclick="closeRoundModal(event)">
    <div class="candidate-modal" onclick="event.stopPropagation()">
        <div class="candidate-modal-head">
            <h3 class="candidate-modal-title">Quản lý lịch / đợt ứng viên</h3>
            <button class="candidate-modal-close" type="button" onclick="hideRoundModal()">×</button>
        </div>

        <div class="candidate-modal-body">
            <div class="round-box">
                <form method="POST" action="{{ route('hr.candidate-processes.rounds.store') }}" class="round-add">
                    @csrf
                    <input class="candidate-input" name="name" placeholder="VD: Đợt 4" required>
                    <input class="candidate-input" name="sort_order" type="number" placeholder="STT">
                    <button class="candidate-btn" type="submit">+ Thêm</button>
                </form>

                @foreach($rounds as $round)
                    <form method="POST" action="{{ route('hr.candidate-processes.rounds.update', $round->id) }}" class="round-row">
                        @csrf
                        @method('PUT')
                        <input class="candidate-input" name="sort_order" type="number" value="{{ $round->sort_order }}">
                        <input class="candidate-input" name="name" value="{{ $round->name }}" required>
                        <button class="candidate-btn-outline" type="submit">Lưu</button>
                    </form>

                    <form method="POST" action="{{ route('hr.candidate-processes.rounds.destroy', $round->id) }}" onsubmit="return confirm('Xoá đợt này? Chỉ xoá được khi chưa có ứng viên sử dụng.')" style="margin:6px 0 10px;">
                        @csrf
                        @method('DELETE')
                        <button class="candidate-btn-danger" type="submit">Xoá: {{ $round->name }}</button>
                    </form>
                @endforeach
            </div>
        </div>

        <div class="candidate-modal-foot">
            <button class="candidate-btn-outline" type="button" onclick="hideRoundModal()">Đóng</button>
        </div>
    </div>
</div>

<script>
    function openAddModal(){document.getElementById('addCandidateModal').classList.add('show')}
    function hideAddModal(){document.getElementById('addCandidateModal').classList.remove('show')}
    function closeAddModal(e){if(e.target.id === 'addCandidateModal') hideAddModal()}

    function openRoundModal(){document.getElementById('roundModal').classList.add('show')}
    function hideRoundModal(){document.getElementById('roundModal').classList.remove('show')}
    function closeRoundModal(e){if(e.target.id === 'roundModal') hideRoundModal()}

    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){
            hideAddModal();
            hideRoundModal();
        }
    });
</script>
@endsection
