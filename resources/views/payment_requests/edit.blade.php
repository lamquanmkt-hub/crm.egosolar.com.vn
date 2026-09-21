@extends('layouts.app')

@section('title', 'Sửa đề nghị thanh toán')

@section('content')
@php
    $statusMap = [
        'draft' => 'Nháp',
        'submitted' => 'Đã gửi duyệt',
        'admin_approved' => 'Quản lý tài chính đã duyệt',
        'admin_rejected' => 'Quản lý tài chính từ chối',
        'accounting_approved' => 'Kế toán đã chi',
        'accounting_rejected' => 'Kế toán từ chối',
    ];

    $statusToneMap = [
        'draft' => 'neutral',
        'submitted' => 'info',
        'admin_approved' => 'primary',
        'admin_rejected' => 'danger',
        'accounting_approved' => 'success',
        'accounting_rejected' => 'warning',
    ];

    $statusLabel = $statusMap[$item->status] ?? ($item->status ?? '-');
    $statusTone = $statusToneMap[$item->status] ?? 'neutral';

    $dueValue = old('payment_due_date');
    if ($dueValue === null && !empty($item->payment_due_date)) {
        try {
            $dueValue = \Illuminate\Support\Carbon::parse($item->payment_due_date)->format('Y-m-d');
        } catch (\Throwable $e) {
            $dueValue = '';
        }
    }

    $createdAt = !empty($item->created_at)
        ? \Illuminate\Support\Carbon::parse($item->created_at)->format('d/m/Y H:i')
        : '-';

    $attachments = $item->attachments ?? collect();
    /* EGO_ATTACHMENTS_DIRECT_QUERY_START */
    try {
        if (!empty($item->id) && \Illuminate\Support\Facades\Schema::hasTable('payment_attachments')) {
            $attachments = \Illuminate\Support\Facades\DB::table('payment_attachments')
                ->where('payment_request_id', (int) $item->id)
                ->orderBy('id', 'asc')
                ->get();
        }
    } catch (\Throwable $e) {
        $attachments = $attachments ?? collect();
    }
    /* EGO_ATTACHMENTS_DIRECT_QUERY_END */

@endphp

<style>
    .pay-edit {
        max-width: 1180px;
        margin: 0 auto;
        padding: 18px 12px 36px;
        color: #071b33;
        font-size: 13px;
    }

    .pay-edit * {
        box-sizing: border-box;
    }

    .pay-edit a {
        text-decoration: none;
    }

    .pay-edit-animate {
        animation: payEditFade .28s ease both;
    }

    @keyframes payEditFade {
        from {
            opacity: 0;
            transform: translateY(8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .pay-edit-hero {
        position: relative;
        overflow: hidden;
        border-radius: 22px;
        padding: 18px 20px;
        margin-bottom: 14px;
        background:
            radial-gradient(circle at top left, rgba(6,182,212,.20), transparent 32%),
            radial-gradient(circle at bottom right, rgba(34,197,94,.14), transparent 32%),
            linear-gradient(135deg, #ffffff 0%, #f6fbff 100%);
        border: 1px solid #d8eef8;
        box-shadow: 0 18px 45px rgba(15,23,42,.075);
    }

    .pay-edit-hero::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #06b6d4, #0ea5e9, #22c55e);
    }

    .pay-edit-hero-inner {
        position: relative;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }

    .pay-edit-title-wrap {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        min-width: 0;
    }

    .pay-edit-icon {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, #ecfeff, #dbeafe);
        border: 1px solid #c7eef8;
        color: #0284c7;
        font-size: 18px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.9);
    }

    .pay-edit-title {
        margin: 0;
        font-size: 24px;
        font-weight: 900;
        line-height: 1.2;
        color: #071b33;
        letter-spacing: -.02em;
    }

    .pay-edit-sub {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 7px;
        font-size: 12px;
        color: #64748b;
        font-weight: 700;
    }

    .pay-edit-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 27px;
        padding: 0 10px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 850;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .pay-edit-chip.neutral { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
    .pay-edit-chip.info { background: #e0f2fe; color: #0369a1; border-color: #bae6fd; }
    .pay-edit-chip.primary { background: #cffafe; color: #0e7490; border-color: #a5f3fc; }
    .pay-edit-chip.success { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .pay-edit-chip.danger { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
    .pay-edit-chip.warning { background: #ffedd5; color: #9a3412; border-color: #fed7aa; }

    .pay-edit-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .pay-edit-btn {
        border: 0;
        min-height: 36px;
        padding: 0 13px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        font-size: 12px;
        font-weight: 850;
        cursor: pointer;
        transition: .16s ease;
        white-space: nowrap;
    }

    .pay-edit-btn:hover {
        transform: translateY(-1px);
        filter: brightness(1.02);
    }

    .pay-edit-btn.light {
        color: #0f172a;
        background: #fff;
        border: 1px solid #d8e4ef;
    }

    .pay-edit-btn.blue {
        color: #fff;
        background: linear-gradient(135deg, #06b6d4, #0284c7);
        box-shadow: 0 10px 22px rgba(14,165,233,.23);
    }

    .pay-edit-btn.dark {
        color: #fff;
        background: linear-gradient(135deg, #1e293b, #0f172a);
    }

    .pay-edit-card {
        border-radius: 20px;
        background: rgba(255,255,255,.96);
        border: 1px solid #e2eaf3;
        box-shadow: 0 14px 38px rgba(15,23,42,.06);
        overflow: hidden;
        margin-bottom: 14px;
    }

    .pay-edit-card-head {
        padding: 15px 17px 0;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .pay-edit-card-title {
        margin: 0;
        font-size: 14px;
        font-weight: 900;
        color: #071b33;
    }

    .pay-edit-card-desc {
        margin-top: 4px;
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
    }

    .pay-edit-card-body {
        padding: 15px 17px 17px;
    }

    .pay-edit-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .pay-edit-grid-3 {
        display: grid;
        grid-template-columns: 1.3fr .7fr 1fr;
        gap: 12px;
    }

    .pay-edit-field {
        position: relative;
    }

    .pay-edit-field.full {
        grid-column: 1 / -1;
    }

    .pay-edit-label {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 6px;
        color: #475569;
        font-size: 11.5px;
        font-weight: 850;
        letter-spacing: .015em;
    }

    .pay-edit-required {
        color: #e11d48;
    }

    .pay-edit-control {
        width: 100%;
        min-height: 38px;
        border-radius: 12px !important;
        border: 1px solid #d8e4ef !important;
        background: #fff !important;
        padding: 8px 11px !important;
        color: #0f172a !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        outline: none !important;
        transition: .16s ease !important;
        box-shadow: none !important;
    }

    .pay-edit-control:focus {
        border-color: #38bdf8 !important;
        box-shadow: 0 0 0 4px rgba(14,165,233,.12) !important;
    }

    textarea.pay-edit-control {
        min-height: 115px;
        resize: vertical;
        line-height: 1.65;
    }

    .pay-edit-help {
        margin-top: 6px;
        color: #64748b;
        font-size: 11.5px;
        line-height: 1.45;
        font-weight: 600;
    }

    .pay-edit-file-list {
        display: grid;
        gap: 8px;
    }

    .pay-edit-file {
        display: grid;
        grid-template-columns: 34px 1fr auto;
        gap: 10px;
        align-items: center;
        padding: 10px;
        border-radius: 14px;
        border: 1px solid #e2eaf3;
        background: #f8fbff;
        color: #0f172a;
        transition: .16s ease;
    }

    .pay-edit-file:hover {
        transform: translateY(-1px);
        border-color: #93c5fd;
        color: #0f172a;
    }

    .pay-edit-file-icon {
        width: 34px;
        height: 34px;
        border-radius: 11px;
        display: grid;
        place-items: center;
        background: #e0f2fe;
        color: #0284c7;
        font-size: 11px;
        font-weight: 900;
    }

    .pay-edit-file-name {
        font-size: 12.5px;
        font-weight: 850;
        line-height: 1.35;
        word-break: break-word;
    }

    .pay-edit-file-meta {
        font-size: 11.5px;
        color: #64748b;
        font-weight: 700;
    }

    .pay-edit-empty {
        padding: 12px 14px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        color: #64748b;
        text-align: center;
        font-size: 12px;
        font-weight: 700;
    }

    .pay-edit-footer {
        position: sticky;
        bottom: 0;
        z-index: 10;
        margin-top: 14px;
        padding: 12px;
        border-radius: 18px;
        background: rgba(255,255,255,.86);
        border: 1px solid #dbe7f3;
        box-shadow: 0 -8px 28px rgba(15,23,42,.06);
        backdrop-filter: blur(12px);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .pay-edit-footer-note {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .pay-edit-alert {
        padding: 12px 14px;
        border-radius: 14px;
        margin-bottom: 12px;
        font-size: 12.5px;
        font-weight: 750;
        border: 1px solid transparent;
    }

    .pay-edit-alert.danger {
        color: #991b1b;
        background: #fef2f2;
        border-color: #fecaca;
    }

    .pay-edit-alert.success {
        color: #166534;
        background: #ecfdf5;
        border-color: #bbf7d0;
    }

    @media (max-width: 900px) {
        .pay-edit-grid,
        .pay-edit-grid-3 {
            grid-template-columns: 1fr;
        }

        .pay-edit-hero-inner {
            align-items: flex-start;
        }

        .pay-edit-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 560px) {
        .pay-edit {
            padding-left: 6px;
            padding-right: 6px;
        }

        .pay-edit-title {
            font-size: 20px;
        }

        .pay-edit-title-wrap {
            align-items: flex-start;
        }

        .pay-edit-footer {
            position: static;
        }
    }
</style>

<div class="pay-edit">
    @if(session('success'))
        <div class="pay-edit-alert success pay-edit-animate">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="pay-edit-alert danger pay-edit-animate">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="pay-edit-alert danger pay-edit-animate">
            <strong>Cần kiểm tra lại:</strong> {{ $errors->first() }}
        </div>
    @endif

    <section class="pay-edit-hero pay-edit-animate">
        <div class="pay-edit-hero-inner">
            <div class="pay-edit-title-wrap">
                <div class="pay-edit-icon">✎</div>
                <div>
                    <h1 class="pay-edit-title">Sửa đề nghị thanh toán: {{ $item->code }}</h1>
                    <div class="pay-edit-sub">
                        <span>ID #{{ $item->id }}</span>
                        <span>Ngày tạo: {{ $createdAt }}</span>
                        <span>Trạng thái: {{ $statusLabel }}</span>
                    </div>
                    <div style="margin-top:9px;">
                        <span class="pay-edit-chip {{ $statusTone }}">{{ $statusLabel }}</span>
                    </div>
                </div>
            </div>

            <div class="pay-edit-actions">
                <a href="{{ route('payment_requests.show', $item->id) }}" class="pay-edit-btn light">← Quay lại</a>
                <a href="{{ route('payment_requests.index') }}" class="pay-edit-btn light">Danh sách</a>
            </div>
        </div>
    </section>

    <form method="POST" action="{{ route('payment_requests.update', $item->id) }}">
        @csrf
        @method('PUT')

        <section class="pay-edit-card pay-edit-animate" style="animation-delay:.03s;">
            <div class="pay-edit-card-head">
                <div>
                    <h2 class="pay-edit-card-title">Thông tin cơ bản</h2>
                    <div class="pay-edit-card-desc">Các trường chính của phiếu, đồng bộ với form tạo phiếu.</div>
                </div>
            </div>

            <div class="pay-edit-card-body">
                <div class="pay-edit-grid">
                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Người nhận</label>
                        <input type="text"
                               name="receiver_name"
                               class="pay-edit-control"
                               value="{{ old('receiver_name', $item->receiver_name) }}"
                               placeholder="VD: Nguyễn Văn A">
                    </div>

                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Công ty</label>
                        <select name="company" class="pay-edit-control">
                            <option value="">-- Chọn công ty --</option>
                            @foreach($companyOptions as $company)
                                <option value="{{ $company }}" @selected(old('company', $item->company) === $company)>
                                    {{ $company }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Bộ phận / Đơn vị</label>
                        <input type="text"
                               name="department"
                               class="pay-edit-control"
                               value="{{ old('department', $item->department) }}"
                               placeholder="VD: Marketing & Sales">
                    </div>

                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Số tiền (VNĐ)</label>
                        <input type="number"
                               name="amount"
                               class="pay-edit-control"
                               value="{{ old('amount', $item->amount) }}"
                               min="0"
                               step="1"
                               placeholder="VD: 2500000">
                    </div>

                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Ngày phải thanh toán</label>
                        <input type="date"
                               name="payment_due_date"
                               class="pay-edit-control"
                               value="{{ $dueValue }}">
                    </div>

                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Thông tin chuyển khoản</label>
                        <input type="text"
                               name="bank_info"
                               class="pay-edit-control"
                               value="{{ old('bank_info', $item->bank_info) }}"
                               placeholder="VD: MB BANK - 0123... - Nguyễn Văn A">
                    </div>

                    {{-- EGO_FIX_REASON_FIELD_START --}}
                    <div class="pay-edit-field" style="grid-column:1 / -1;">
                        <label class="pay-edit-label">Nội dung / Lý do thanh toán</label>
                        <textarea name="reason"
                                  class="pay-edit-control"
                                  rows="3"
                                  placeholder="Nhập nội dung hoặc lý do thanh toán">{{ old('reason', $item->reason ?? $item->payment_content ?? 'Thanh toán theo đề nghị') }}</textarea>
                    </div>

                    <input type="hidden"
                           name="payment_content"
                           value="{{ old('payment_content', $item->payment_content ?? $item->reason ?? 'Thanh toán theo đề nghị') }}">
                    {{-- EGO_FIX_REASON_FIELD_END --}}
                </div>
            </div>
        </section>



        
        {{-- EGO_AJAX_ATTACHMENTS_START --}}
        @php
            $egoPrId = isset($item) && !empty($item->id) ? (int) $item->id : 0;
            $egoAttachments = collect();

            try {
                if ($egoPrId && \Illuminate\Support\Facades\Schema::hasTable('payment_attachments')) {
                    $egoAttachments = \Illuminate\Support\Facades\DB::table('payment_attachments')
                        ->where('payment_request_id', $egoPrId)
                        ->orderBy('id', 'asc')
                        ->get();
                }
            } catch (\Throwable $e) {
                $egoAttachments = collect();
            }
        @endphp

        <section class="pay-edit-card pay-edit-animate" style="animation-delay:.09s;" id="ego-pr-attachments-card">
            <div class="pay-edit-card-head">
                <div>
                    <h2 class="pay-edit-card-title">Chứng từ liên có</h2>
                    <div class="pay-edit-card-desc">Thêm chứng từ mới, thay file chứng từ cũ hoặc xóa chứng từ ngay tại màn hình sửa phiếu.</div>
                </div>
            </div>

            <div class="pay-edit-card-body">
                <div id="ego-pr-attachment-message" style="display:none;margin-bottom:10px;padding:10px 12px;border-radius:12px;font-weight:800;"></div>

                @if($egoAttachments->count())
                    <div class="pay-edit-file-list" style="margin-bottom:14px;">
                        @foreach($egoAttachments as $att)
                            @php
                                $egoFileName = !empty($att->original_name) ? $att->original_name : basename($att->path ?? '');
                                $egoFileSize = !empty($att->size) ? number_format(((float) $att->size) / 1024, 1) . ' KB' : '';
                                $egoDownloadUrl = url('/payment-requests/' . $egoPrId . '/attachments-thao/' . $att->id . '/download');
                            @endphp

                            <div class="pay-edit-file" style="align-items:center;">
                                <a href="{{ $egoDownloadUrl }}" target="_blank" style="display:flex;align-items:center;gap:10px;flex:1;min-width:220px;color:inherit;text-decoration:none;">
                                    <div class="pay-edit-file-icon">FILE</div>
                                    <div>
                                        <div class="pay-edit-file-name">{{ $egoFileName }}</div>
                                        <div class="pay-edit-file-meta">{{ $att->mime_type ?? 'Tệp đính kèm' }} {{ $egoFileSize ? ' - ' . $egoFileSize : '' }}</div>
                                    </div>
                                </a>

                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                                    <input type="file"
                                           class="ego-pr-replace-file"
                                           data-attachment-id="{{ $att->id }}"
                                           style="max-width:210px;font-size:12px;">

                                    <button type="button"
                                            class="pay-edit-btn yellow ego-pr-replace-btn"
                                            data-attachment-id="{{ $att->id }}"
                                            style="padding:8px 10px;">
                                        Sửa
                                    </button>

                                    <button type="button"
                                            class="pay-edit-btn danger ego-pr-delete-btn"
                                            data-attachment-id="{{ $att->id }}"
                                            style="padding:8px 10px;">
                                        Xóa
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="pay-edit-empty" style="margin-bottom:14px;">Phiếu này chưa có chứng từ đính kèm.</div>
                @endif

                <div style="border:1px dashed #bae6fd;background:#f8fcff;border-radius:16px;padding:14px;">
                    <label class="pay-edit-label">Thêm chứng từ mới</label>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <input type="file"
                               id="ego-pr-new-attachments"
                               multiple
                               class="pay-edit-control"
                               style="max-width:520px;">
                        <button type="button" id="ego-pr-upload-btn" class="pay-edit-btn blue">
                            + Thêm chứng từ
                        </button>
                    </div>
                    <div class="pay-edit-help">Hỗ trợ PDF, ảnh, Word, Excel... Mỗi file tối đa 20MB.</div>
                </div>
            </div>
        </section>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var paymentRequestId = '{{ $egoPrId }}';
            var tokenInput = document.querySelector('input[name="_token"]');
            var metaToken = document.querySelector('meta[name="csrf-token"]');
            var csrfToken = tokenInput ? tokenInput.value : (metaToken ? metaToken.getAttribute('content') : '{{ csrf_token() }}');

            var messageBox = document.getElementById('ego-pr-attachment-message');

            function showMessage(type, text) {
                if (!messageBox) {
                    alert(text);
                    return;
                }

                messageBox.style.display = 'block';
                messageBox.textContent = text;

                if (type === 'success') {
                    messageBox.style.background = '#dcfce7';
                    messageBox.style.color = '#166534';
                    messageBox.style.border = '1px solid #86efac';
                } else {
                    messageBox.style.background = '#fee2e2';
                    messageBox.style.color = '#991b1b';
                    messageBox.style.border = '1px solid #fecaca';
                }
            }

            function setBusy(button, text) {
                var old = button.textContent;
                button.disabled = true;
                button.textContent = text;
                return function () {
                    button.disabled = false;
                    button.textContent = old;
                };
            }

            function postForm(url, formData) {
                formData.append('_token', csrfToken);

                return fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                }).then(function (res) {
                    return res.text().then(function (text) {
                        var data = null;

                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            data = null;
                        }

                        if (!res.ok) {
                            var msg = 'Lỗi upload HTTP ' + res.status;

                            if (data && data.message) {
                                msg = data.message;
                            } else if (text) {
                                msg = text.substring(0, 300);
                            }

                            throw new Error(msg);
                        }

                        return data || {ok: true};
                    });
                });
            }

            var uploadBtn = document.getElementById('ego-pr-upload-btn');
            var fileInput = document.getElementById('ego-pr-new-attachments');

            if (uploadBtn && fileInput) {
                uploadBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();

                    if (!fileInput.files || fileInput.files.length === 0) {
                        showMessage('error', 'Bạn chưa chọn file chứng từ.');
                        return false;
                    }

                    var done = setBusy(uploadBtn, 'Đang thêm...');
                    var fd = new FormData();

                    Array.prototype.forEach.call(fileInput.files, function (file) {
                        fd.append('attachments[]', file);
                    });

                    postForm('/payment-requests/' + paymentRequestId + '/attachments-thao', fd)
                        .then(function () {
                            showMessage('success', 'Đã thêm chứng từ. Đang tải lại...');
                            window.location.reload();
                        })
                        .catch(function (err) {
                            showMessage('error', err.message || 'Không thêm được chứng từ.');
                            done();
                        });

                    return false;
                });
            }

            document.querySelectorAll('.ego-pr-replace-btn').forEach(function (btn) {
                btn.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var id = btn.getAttribute('data-attachment-id');
                    var input = document.querySelector('.ego-pr-replace-file[data-attachment-id="' + id + '"]');

                    if (!input || !input.files || input.files.length === 0) {
                        showMessage('error', 'Bạn chưa chọn file mới để sửa chứng từ.');
                        return false;
                    }

                    var done = setBusy(btn, 'Đang sửa...');
                    var fd = new FormData();
                    fd.append('attachment', input.files[0]);

                    postForm('/payment-requests/' + paymentRequestId + '/attachments-thao/' + id + '/cap-nhat', fd)
                        .then(function () {
                            showMessage('success', 'Đã sửa chứng từ. Đang tải lại...');
                            window.location.reload();
                        })
                        .catch(function (err) {
                            showMessage('error', err.message || 'Không sửa được chứng từ.');
                            done();
                        });

                    return false;
                });
            });

            document.querySelectorAll('.ego-pr-delete-btn').forEach(function (btn) {
                btn.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var id = btn.getAttribute('data-attachment-id');

                    if (!confirm('Xóa chứng từ này?')) {
                        return false;
                    }

                    var done = setBusy(btn, 'Đang xóa...');
                    var fd = new FormData();
                    fd.append('_method', 'DELETE');

                    postForm('/payment-requests/' + paymentRequestId + '/attachments-thao/' + id + '/xoa', fd)
                        .then(function () {
                            showMessage('success', 'Đã xóa chứng từ. Đang tải lại...');
                            window.location.reload();
                        })
                        .catch(function (err) {
                            showMessage('error', err.message || 'Không xóa được chứng từ.');
                            done();
                        });

                    return false;
                });
            });
        });
        </script>
        {{-- EGO_AJAX_ATTACHMENTS_END --}}

{{-- EGO_PR_AUDIT_REASON_START
     Phiếu đã duyệt/đã chi: bắt buộc nhập lý do thay đổi, lý do sẽ được ghi
     vào nhật ký `payment_request_edit_logs` kèm người thực hiện và IP. --}}
@if(\App\Services\Payments\PaymentRequestAuditLogger::isLockedStatus($item->status ?? null))
        <div class="pay-edit-footer pay-edit-animate" style="animation-delay:.1s;">
            <div class="pay-edit-footer-note" style="width:100%">
                <label for="egoPrAuditReason" style="display:block;font-weight:700;margin-bottom:6px">
                    Lý do sửa phiếu đã duyệt/đã chi <span style="color:#be123c">*</span>
                </label>
                <textarea id="egoPrAuditReason"
                          name="audit_reason"
                          required
                          minlength="5"
                          maxlength="2000"
                          rows="2"
                          style="width:100%"
                          placeholder="Ví dụ: Sửa số tài khoản người nhận theo công văn NCC ngày ...">{{ old('audit_reason') }}</textarea>
                @error('audit_reason')
                    <div style="color:#be123c;font-size:13px;margin-top:4px">{{ $message }}</div>
                @enderror
            </div>
        </div>
@endif
{{-- EGO_PR_AUDIT_REASON_END --}}

<div class="pay-edit-footer pay-edit-animate" style="animation-delay:.12s;">
            <div class="pay-edit-footer-note">
                Kiểm tra kỹ công ty, số tiền và ngày phải thanh toán trước khi lưu.
            </div>

            <div class="pay-edit-actions">
                <a href="{{ route('payment_requests.show', $item->id) }}" class="pay-edit-btn light">Hủy</a>
                <button type="submit" class="pay-edit-btn blue">Lưu thay đổi</button>
            </div>
        </div>
    </form>
</div>
@endsection