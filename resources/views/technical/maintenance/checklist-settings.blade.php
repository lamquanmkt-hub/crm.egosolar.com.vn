@extends('layouts.app')

@section('title', 'Cài đặt checklist O&M')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-v10.css') }}?v={{ file_exists(public_path('css/technical-maintenance-v10.css')) ? filemtime(public_path('css/technical-maintenance-v10.css')) : time() }}">
@endsection

@section('content')
@php $selectedTemplate = $templates->first(); @endphp
<div class="ego-container om10-page om13-builder-page">
    <nav class="om10-breadcrumb"><a href="{{ route('ky-thuat.maintenance.index') }}">Bảo hành & O&M</a><i class="bi bi-chevron-right"></i><span>Checklist builder</span></nav>

    @if(session('success'))<div class="om10-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="om10-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
    @if($errors->any())<div class="om10-alert danger"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Chưa thể lưu</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

    <header class="om13-builder-header">
        <div><div class="om10-eyebrow">THIẾT LẬP NGHIỆP VỤ</div><h1>Checklist builder</h1><p>Xây luồng kiểm tra bằng các khối công việc; kéo thả để thay đổi thứ tự thực hiện.</p></div>
        <div class="om13-builder-header-actions"><nav>@foreach($groups as $key=>$label)<a class="{{ $group===$key?'active':'' }}" href="{{ route('ky-thuat.maintenance.checklist-settings.index',['group'=>$key]) }}"><i class="bi {{ $key==='incident'?'bi-exclamation-diamond':'bi-calendar2-check' }}"></i> {{ $label }}</a>@endforeach</nav><button class="om10-btn primary" type="button" data-om13-new-template><i class="bi bi-plus-lg"></i> Thêm mục mới</button><a class="om10-btn light" href="{{ route('ky-thuat.maintenance.index') }}"><i class="bi bi-arrow-left"></i> Điều hành</a></div>
    </header>

    <div class="om13-builder-shell">
        <aside class="om13-builder-toolbox">
            <span>THƯ VIỆN KHỐI</span><h2>Thêm vào quy trình</h2><p>Chọn loại khối, sau đó cấu hình ở bảng thuộc tính.</p>
            <button class="om13-tool active" type="button" data-om13-new-template><i class="bi bi-clipboard-check"></i><span><strong>Mục kiểm tra</strong><small>Checklist + ghi chú</small></span><i class="bi bi-plus-lg"></i></button>
            <button class="om13-tool" type="button" data-om13-new-template data-om13-proof-default="1"><i class="bi bi-camera"></i><span><strong>Yêu cầu ảnh</strong><small>Mặc định có minh chứng</small></span><i class="bi bi-plus-lg"></i></button>
            <div class="om13-builder-note"><i class="bi bi-info-circle"></i><span>Mẫu mới chỉ áp dụng khi đợt công việc tạo checklist lần đầu. Hồ sơ cũ được giữ nguyên.</span></div>
        </aside>

        <main class="om13-builder-canvas">
            <header><div><span>LUỒNG THỰC HIỆN</span><h2>{{ $groups[$group] }}</h2><p>Chọn “Sửa” hoặc “Xóa” ngay trên từng mục. Kéo tay nắm để đổi thứ tự.</p></div><b>{{ $templates->count() }} bước</b></header>
            <form method="POST" action="#" data-om13-order-form data-om13-order-mode="sequential">@csrf<input type="hidden" name="maintenance_type" value="{{ $group }}">
                <div class="om13-flow" data-om13-builder-list>
                    @forelse($templates as $index=>$template)
                        <article class="om13-flow-block {{ $index===0?'selected':'' }}" draggable="true" data-om13-template data-id="{{ $template->id }}" data-label="{{ e($template->label) }}" data-description="{{ e($template->description) }}" data-sort="{{ $template->sort_order }}" data-required="{{ $template->is_required?1:0 }}" data-evidence="{{ $template->requires_evidence?1:0 }}" data-min="{{ max(1,(int)$template->min_evidence) }}" data-update-url="{{ route('ky-thuat.maintenance.checklist-settings.update',$template) }}" data-delete-url="{{ route('ky-thuat.maintenance.checklist-settings.destroy',$template) }}">
                            <input type="hidden" name="template_ids[]" value="{{ $template->id }}">
                            <button class="om14-flow-main" type="button" data-om13-select-template><span class="om13-flow-index">{{ str_pad($index+1,2,'0',STR_PAD_LEFT) }}</span><span class="om13-flow-copy"><strong>{{ $template->label }}</strong><small>{{ $template->is_required?'Bắt buộc':'Tùy chọn' }} · {{ $template->requires_evidence ? max(1,(int)$template->min_evidence).' file' : 'Không yêu cầu file' }}</small></span><span class="om13-flow-state"><i class="bi {{ $template->requires_evidence?'bi-camera':'bi-card-text' }}"></i></span></button><div class="om14-flow-actions"><button type="button" data-om13-select-template title="Sửa mục"><i class="bi bi-pencil-square"></i><span>Sửa</span></button><button class="danger" type="button" data-om14-delete-template title="Xóa mục"><i class="bi bi-trash3"></i><span>Xóa</span></button><i class="bi bi-grip-vertical om13-drag-handle" title="Kéo để sắp xếp"></i></div>
                        </article>
                    @empty<div class="om10-empty"><i class="bi bi-list-check"></i><strong>Chưa có bước nào</strong><span>Thêm khối từ thư viện bên trái.</span></div>@endforelse
                </div>
                @if($templates->isNotEmpty())<div class="om13-order-actions"><span data-om13-order-status>Thứ tự chưa thay đổi</span><button class="om10-btn light" type="submit"><i class="bi bi-arrow-down-up"></i> Lưu thứ tự</button></div>@endif
            </form>
        </main>

        <aside class="om10-card om13-property-panel">
            <header><div><span>THUỘC TÍNH MỤC</span><h2 data-om13-editor-title>{{ $selectedTemplate?'Chỉnh sửa mục':'Thêm mục mới' }}</h2></div><button class="om14-new-inline" type="button" data-om13-new-template><i class="bi bi-plus-lg"></i> Thêm mới</button></header>
            <form method="POST" action="{{ $selectedTemplate ? route('ky-thuat.maintenance.checklist-settings.update',$selectedTemplate) : route('ky-thuat.maintenance.checklist-settings.store') }}" data-om13-template-editor data-store-url="{{ route('ky-thuat.maintenance.checklist-settings.store') }}" data-next-sort="{{ ($templates->max('sort_order')??0)+10 }}">@csrf<input type="hidden" name="_method" value="PUT" data-om13-method @disabled(!$selectedTemplate)><input type="hidden" name="maintenance_type" value="{{ $group }}">
                <label><span>Tên mục kiểm tra <b>*</b></span><input name="label" value="{{ old('label',$selectedTemplate?->label) }}" required maxlength="255" placeholder="Ví dụ: Kiểm tra điện áp chuỗi pin" data-om13-label></label>
                <label><span>Hướng dẫn kỹ thuật</span><textarea name="description" rows="5" maxlength="1000" placeholder="Tiêu chuẩn, vị trí hoặc thông số cần ghi nhận..." data-om13-description>{{ old('description',$selectedTemplate?->description) }}</textarea></label>
                <input type="hidden" name="sort_order" value="{{ old('sort_order',$selectedTemplate?->sort_order ?? (($templates->max('sort_order')??0)+10)) }}" data-om13-sort>
                <div class="om13-property-switches">
                    <label><input type="checkbox" name="is_required" value="1" @checked(old('is_required',$selectedTemplate?->is_required ?? true)) data-om13-required><span></span><div><strong>Bắt buộc hoàn thành</strong><small>Thiếu mục sẽ không được lập báo cáo</small></div></label>
                    <label><input type="checkbox" name="requires_evidence" value="1" @checked(old('requires_evidence',$selectedTemplate?->requires_evidence ?? true)) data-om12-evidence-switch data-om13-evidence><span></span><div><strong>Yêu cầu file minh chứng</strong><small>File gắn trực tiếp vào bước này</small></div></label>
                </div>
                <label><span>Số file tối thiểu</span><div class="om12-number-field"><input type="number" name="min_evidence" value="{{ old('min_evidence',max(1,(int)($selectedTemplate?->min_evidence ?? 1))) }}" min="1" max="20" data-om12-min-evidence data-om13-min><b>file</b></div></label>
                <button class="om10-btn primary" type="submit" data-om13-save-label><i class="bi bi-check2-circle"></i> {{ $selectedTemplate?'Lưu thay đổi':'Thêm vào quy trình' }}</button>
            </form>
            <form method="POST" action="{{ $selectedTemplate ? route('ky-thuat.maintenance.checklist-settings.destroy',$selectedTemplate) : '#' }}" data-om13-delete-form data-om11-confirm="Xóa mục này khỏi mẫu checklist? Hồ sơ đã tạo trước đây vẫn được giữ nguyên." @if(!$selectedTemplate) hidden @endif>@csrf @method('DELETE')<button class="om13-delete-block" type="submit"><i class="bi bi-trash3"></i> Xóa mục đang chọn</button></form>
        </aside>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/technical-maintenance-v10.js') }}?v={{ file_exists(public_path('js/technical-maintenance-v10.js')) ? filemtime(public_path('js/technical-maintenance-v10.js')) : time() }}"></script>
@endsection
