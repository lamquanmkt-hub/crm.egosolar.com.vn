@extends('layouts.app')

@section('title', 'Giao diện & thương hiệu')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-appearance-settings.css') }}?v={{ filemtime(public_path('css/ego-appearance-settings.css')) }}">
@endpush

@section('content')
<div id="egoAppearanceSettings">
    <div class="eas-shell">
        @if(session('success'))
            <div class="eas-alert"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>
        @endif

        @if($errors->any())
            <div class="eas-alert eas-alert--danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            </div>
        @endif

        <header class="eas-hero">
            <div class="eas-hero__icon"><i class="bi bi-palette2"></i></div>
            <div class="eas-hero__copy">
                <span>CÀI ĐẶT HỆ THỐNG</span>
                <h1>Giao diện & thương hiệu</h1>
                <p>Đổi logo, favicon, màu chủ đạo, sidebar, topbar và mật độ hiển thị toàn bộ CRM.</p>
            </div>
            <a href="{{ route('admin.settings.index') }}" class="eas-btn eas-btn--soft"><i class="bi bi-arrow-left"></i> Trung tâm cài đặt</a>
        </header>

        <nav class="eas-settings-nav">
            <a href="{{ route('admin.settings.index') }}"><i class="bi bi-grid-1x2"></i>Tổng quan</a>
            <a class="active" href="{{ route('admin.settings.appearance') }}"><i class="bi bi-palette"></i>Giao diện</a>
            <a href="{{ route('admin.settings.workspace') }}"><i class="bi bi-grid-3x3-gap"></i>Ứng dụng theo vai trò</a>
            @if(\Illuminate\Support\Facades\Route::has('payment-methods.index'))
                <a href="{{ route('payment-methods.index') }}"><i class="bi bi-credit-card"></i>Thanh toán</a>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('companies.index'))
                <a href="{{ route('companies.index') }}"><i class="bi bi-building"></i>Thông tin công ty</a>
            @endif
            <a href="{{ route('admin.settings.roles') }}"><i class="bi bi-people"></i>Vai trò</a>
            <a href="{{ route('admin.settings.menus') }}"><i class="bi bi-layout-sidebar"></i>Phân quyền menu</a>
        </nav>

        <form method="POST" action="{{ route('admin.settings.appearance.update') }}" enctype="multipart/form-data" id="egoAppearanceForm">
            @csrf
            <div class="eas-layout">
                <main class="eas-main">
                    <section class="eas-card">
                        <div class="eas-card__head"><div><span>01</span><h2>Nhận diện thương hiệu</h2><p>Tên hệ thống, logo trên nền sáng, logo sidebar và favicon.</p></div></div>
                        <div class="eas-card__body">
                            <div class="eas-grid eas-grid--2">
                                <label class="eas-field"><span>Tên hệ thống</span><input name="brand_name" value="{{ old('brand_name', $settings['brand_name']) }}" required></label>
                                <label class="eas-field"><span>Tên rút gọn</span><input name="brand_short_name" value="{{ old('brand_short_name', $settings['brand_short_name']) }}" required></label>
                            </div>

                            <div class="eas-upload-grid">
                                <label class="eas-upload">
                                    <div class="eas-upload__preview is-light"><img src="{{ asset($settings['logo_light']) }}" data-preview-logo-light alt="Logo nền sáng"></div>
                                    <div><strong>Logo nền sáng</strong><small>Trang đăng nhập và khu vực nền trắng.</small><input type="file" name="logo_light_file" accept=".png,.jpg,.jpeg,.webp,.svg" data-input-logo-light></div>
                                </label>

                                <label class="eas-upload">
                                    <div class="eas-upload__preview is-dark" data-preview-sidebar-bg><img src="{{ asset($settings['logo_sidebar']) }}" data-preview-logo-sidebar alt="Logo sidebar"></div>
                                    <div><strong>Logo sidebar</strong><small>Nên dùng logo trắng hoặc màu sáng.</small><input type="file" name="logo_sidebar_file" accept=".png,.jpg,.jpeg,.webp,.svg" data-input-logo-sidebar></div>
                                </label>

                                <label class="eas-upload">
                                    <div class="eas-upload__preview is-favicon">
                                        @if($settings['favicon'])
                                            <img src="{{ asset($settings['favicon']) }}" data-preview-favicon alt="Favicon">
                                        @else
                                            <i class="bi bi-globe2" data-favicon-placeholder></i><img src="" data-preview-favicon alt="Favicon" hidden>
                                        @endif
                                    </div>
                                    <div><strong>Favicon</strong><small>Ảnh vuông 64×64 hoặc 128×128px.</small><input type="file" name="favicon_file" accept=".png,.jpg,.jpeg,.webp,.ico" data-input-favicon></div>
                                </label>
                            </div>
                        </div>
                    </section>

                    <section class="eas-card">
                        <div class="eas-card__head"><div><span>02</span><h2>Hệ màu website</h2><p>Màu sẽ được áp dụng trực tiếp cho toàn bộ CRM sau khi lưu.</p></div></div>
                        <div class="eas-card__body">
                            <div class="eas-colors">
                                @foreach([
                                    'primary_color' => ['Màu chủ đạo', 'Nút chính và trạng thái active'],
                                    'secondary_color' => ['Màu phụ', 'Gradient và điểm nhấn'],
                                    'sidebar_color' => ['Màu sidebar', 'Thanh menu bên trái'],
                                    'topbar_color' => ['Màu topbar', 'Thanh công cụ phía trên'],
                                    'page_background' => ['Màu nền trang', 'Nền chung của nội dung'],
                                ] as $key => [$label, $note])
                                    <label class="eas-color">
                                        <span><strong>{{ $label }}</strong><small>{{ $note }}</small></span>
                                        <div><input type="color" name="{{ $key }}" value="{{ old($key, $settings[$key]) }}" data-theme-color="{{ $key }}"><code data-color-code="{{ $key }}">{{ old($key, $settings[$key]) }}</code></div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    <section class="eas-card">
                        <div class="eas-card__head"><div><span>03</span><h2>Kiểu hiển thị</h2><p>Điều chỉnh độ bo và khoảng cách của giao diện.</p></div></div>
                        <div class="eas-card__body">
                            <div class="eas-grid eas-grid--2">
                                <label class="eas-field"><span>Độ bo góc</span><div class="eas-range"><input type="range" name="card_radius" min="8" max="30" value="{{ old('card_radius', $settings['card_radius']) }}" data-radius-input><b data-radius-value>{{ old('card_radius', $settings['card_radius']) }}px</b></div></label>
                                <label class="eas-field"><span>Mật độ giao diện</span><select name="ui_density"><option value="comfortable" @selected(old('ui_density', $settings['ui_density']) === 'comfortable')>Thoải mái</option><option value="compact" @selected(old('ui_density', $settings['ui_density']) === 'compact')>Nhỏ gọn</option></select></label>
                            </div>
                        </div>
                    </section>
                </main>

                <aside class="eas-aside">
                    <section class="eas-preview" data-theme-preview>
                        <div class="eas-preview__bar"><i></i><i></i><i></i></div>
                        <div class="eas-preview__app">
                            <div class="eas-preview__side" data-preview-sidebar-bg><img src="{{ asset($settings['logo_sidebar']) }}" data-preview-logo-sidebar alt="Logo"><span class="active"></span><span></span><span></span><span></span></div>
                            <div class="eas-preview__page">
                                <div class="eas-preview__top" data-preview-topbar></div>
                                <div class="eas-preview__body" data-preview-page>
                                    <article data-preview-card><small></small><strong></strong><p></p><button type="button" data-preview-button>Nút chính</button></article>
                                    <div class="eas-preview__rows"><i></i><i></i><i></i></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <button type="submit" class="eas-btn eas-btn--primary eas-save"><i class="bi bi-check2-circle"></i><span>Lưu và áp dụng toàn website</span></button>
                    <div class="eas-note"><i class="bi bi-info-circle"></i><span>Thay đổi có hiệu lực cho tất cả tài khoản sau khi tải lại trang.</span></div>

                    <button
                        type="submit"
                        form="egoAppearanceResetForm"
                        class="eas-btn eas-btn--soft eas-reset-button"
                        onclick="return confirm('Khôi phục giao diện mặc định?')"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Khôi phục mặc định
                    </button>
                </aside>
            </div>
        </form>

        <form
            id="egoAppearanceResetForm"
            method="POST"
            action="{{ route('admin.settings.appearance.reset') }}"
            hidden
        >
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ego-appearance-settings.js') }}?v={{ filemtime(public_path('js/ego-appearance-settings.js')) }}" defer></script>
@endpush
