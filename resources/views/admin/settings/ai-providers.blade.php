@extends('layouts.app')

@section('title', 'Cấu hình API AI')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-ai-settings.css') }}?v={{ file_exists(public_path('css/ego-ai-settings.css')) ? filemtime(public_path('css/ego-ai-settings.css')) : '1.0.0' }}">
@endpush

@section('content')
<div class="ego-ai-settings" id="egoAiSettings">
    <div class="ego-ai-settings__shell">
        @if(session('success'))
            <div class="ego-ai-settings-alert"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>
        @endif

        @if($errors->any())
            <div class="ego-ai-settings-alert ego-ai-settings-alert--danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            </div>
        @endif

        <header class="ego-ai-settings-hero">
            <div class="ego-ai-settings-hero__icon"><i class="bi bi-cpu"></i></div>
            <div class="ego-ai-settings-hero__copy">
                <span>CÀI ĐẶT HỆ THỐNG</span>
                <h1>API & mô hình AI</h1>
                <p>Quản lý nhiều nhà cung cấp, mã hóa API key, chọn model mặc định và kiểm tra kết nối trực tiếp từ server.</p>
            </div>
            <div class="ego-ai-settings-hero__actions">
                <a href="{{ route('ai.index') }}" class="ego-ai-settings-btn ego-ai-settings-btn--primary"><i class="bi bi-stars"></i> Mở EGO AI</a>
                <a href="{{ route('admin.settings.index') }}" class="ego-ai-settings-btn"><i class="bi bi-arrow-left"></i> Trung tâm cài đặt</a>
            </div>
        </header>

        <nav class="ego-ai-settings-nav">
            <a href="{{ route('admin.settings.index') }}"><i class="bi bi-grid-1x2"></i>Tổng quan</a>
            <a href="{{ route('admin.settings.appearance') }}"><i class="bi bi-palette"></i>Giao diện</a>
            <a class="active" href="{{ route('admin.settings.ai.index') }}"><i class="bi bi-cpu"></i>API AI</a>
            <a href="{{ route('admin.settings.ai.governance.index') }}"><i class="bi bi-shield-lock"></i>Quản trị AI</a>
            <a href="{{ route('admin.settings.roles') }}"><i class="bi bi-people"></i>Vai trò</a>
            <a href="{{ route('admin.settings.menus') }}"><i class="bi bi-layout-sidebar"></i>Phân quyền menu</a>
        </nav>

        <section class="ego-ai-settings-kpis">
            <article><span><i class="bi bi-plug"></i>Kết nối</span><strong>{{ $providers->count() }}</strong><small>{{ $providers->where('is_active', true)->count() }} đang bật</small></article>
            <article><span><i class="bi bi-send"></i>Yêu cầu 30 ngày</span><strong>{{ number_format((int) ($usage->requests ?? 0), 0, ',', '.') }}</strong><small>Chat và kiểm tra API</small></article>
            <article><span><i class="bi bi-braces"></i>Token 30 ngày</span><strong>{{ number_format((int) ($usage->tokens ?? 0), 0, ',', '.') }}</strong><small>Tổng input + output</small></article>
            <article><span><i class="bi bi-speedometer2"></i>Độ trễ TB</span><strong>{{ number_format((float) ($usage->latency ?? 0), 0, ',', '.') }} ms</strong><small>Thời gian phản hồi server</small></article>
        </section>

        <div class="ego-ai-settings-layout">
            <main class="ego-ai-settings-main">
                <section class="ego-ai-settings-section">
                    <div class="ego-ai-settings-section__head">
                        <div><span>KẾT NỐI ĐÃ LƯU</span><h2>Nhà cung cấp AI</h2><p>API key chỉ được hiển thị dạng che và được mã hóa bằng APP_KEY của Laravel.</p></div>
                        <button type="button" class="ego-ai-settings-btn ego-ai-settings-btn--primary" data-open-add-provider><i class="bi bi-plus-lg"></i> Thêm kết nối</button>
                    </div>

                    <div class="ego-ai-provider-list">
                        @forelse($providers as $provider)
                            <article class="ego-ai-provider-card {{ $provider->is_default ? 'is-default' : '' }}" data-provider-card>
                                <div class="ego-ai-provider-card__summary">
                                    <div class="ego-ai-provider-card__logo"><i class="bi bi-cpu-fill"></i></div>
                                    <div class="ego-ai-provider-card__identity">
                                        <div><strong>{{ $provider->name }}</strong>@if($provider->is_default)<span class="ego-ai-badge">MẶC ĐỊNH</span>@endif</div>
                                        <p>{{ $providerTypes[$provider->provider_type] ?? $provider->provider_type }} · {{ $provider->model }}</p>
                                    </div>
                                    <div class="ego-ai-provider-card__state {{ $provider->is_active ? '' : 'is-off' }}"><span></span>{{ $provider->is_active ? 'Đang bật' : 'Đã tắt' }}</div>
                                    <div class="ego-ai-provider-card__actions">
                                        <button type="button" class="ego-ai-settings-btn ego-ai-settings-btn--test" data-test-url="{{ route('admin.settings.ai.providers.test', $provider) }}"><i class="bi bi-broadcast"></i> Kiểm tra</button>
                                        <button type="button" class="ego-ai-settings-icon" data-toggle-provider><i class="bi bi-chevron-down"></i></button>
                                    </div>
                                </div>

                                <div class="ego-ai-provider-card__details">
                                    <form method="POST" action="{{ route('admin.settings.ai.providers.update', $provider) }}" class="ego-ai-provider-form">
                                        @csrf @method('PUT')
                                        <div class="ego-ai-form-grid">
                                            <label><span>Tên kết nối</span><input type="text" name="name" value="{{ $provider->name }}" required maxlength="100"></label>
                                            <label><span>Nhà cung cấp</span><select name="provider_type" required data-provider-type>@foreach($providerTypes as $key => $label)<option value="{{ $key }}" @selected($provider->provider_type === $key)>{{ $label }}</option>@endforeach</select></label>
                                            <label class="ego-ai-col-2"><span>Base URL <small>Để trống nếu dùng URL chuẩn của nhà cung cấp</small></span><input type="url" name="base_url" value="{{ $provider->base_url }}" placeholder="https://api.example.com/v1" data-base-url></label>
                                            <label><span>Model chat</span><input type="text" name="model" value="{{ $provider->model }}" required maxlength="160" placeholder="Tên model trên tài khoản API"></label>
                                            <label><span>Model nhanh <small>không bắt buộc</small></span><input type="text" name="fast_model" value="{{ $provider->fast_model }}" maxlength="160"></label>
                                            <label class="ego-ai-col-2"><span>API key mới <small>để trống để giữ key hiện tại: {{ $provider->maskedKey() }}</small></span><div class="ego-ai-secret"><input type="password" name="api_key" autocomplete="new-password" maxlength="2000" placeholder="Không nhập nếu không đổi"><button type="button" data-toggle-secret><i class="bi bi-eye"></i></button></div></label>
                                            <label><span>Organization <small>OpenAI, tùy chọn</small></span><input type="text" name="organization" value="{{ $provider->organization }}" maxlength="160"></label>
                                            <label><span>Project <small>OpenAI, tùy chọn</small></span><input type="text" name="project" value="{{ $provider->project }}" maxlength="160"></label>
                                            <label><span>Timeout</span><div class="ego-ai-input-unit"><input type="number" name="timeout_seconds" value="{{ $provider->timeout_seconds }}" min="10" max="180" required><b>giây</b></div></label>
                                            <label><span>Max output token</span><input type="number" name="max_output_tokens" value="{{ $provider->max_output_tokens }}" min="128" max="16000" required></label>
                                            <label><span>Temperature</span><input type="number" name="temperature" value="{{ $provider->temperature }}" min="0" max="2" step="0.05" required></label>
                                            <div class="ego-ai-switches">
                                                <label><input type="checkbox" name="is_active" value="1" @checked($provider->is_active)><span></span><b>Bật kết nối</b></label>
                                                <label><input type="checkbox" name="is_default" value="1" @checked($provider->is_default)><span></span><b>Dùng mặc định</b></label>
                                            </div>
                                        </div>
                                        <div class="ego-ai-provider-form__footer">
                                            <span>Tạo bởi {{ optional($provider->creator)->name ?? 'Admin' }} · {{ $provider->updated_at->format('d/m/Y H:i') }}</span>
                                            <div>
                                                @unless($provider->is_default)
                                                    <span class="ego-ai-inline-hint"><i class="bi bi-star"></i> Bật “Dùng mặc định” rồi lưu</span>
                                                @endunless
                                                <button type="submit" class="ego-ai-settings-btn ego-ai-settings-btn--primary"><i class="bi bi-floppy"></i> Lưu thay đổi</button>
                                            </div>
                                        </div>
                                    </form>
                                    <form method="POST" action="{{ route('admin.settings.ai.providers.destroy', $provider) }}" onsubmit="return confirm('Xóa kết nối {{ addslashes($provider->name) }}?')" class="ego-ai-provider-delete">
                                        @csrf @method('DELETE')
                                        <button type="submit"><i class="bi bi-trash3"></i> Xóa kết nối</button>
                                    </form>
                                </div>
                            </article>
                        @empty
                            <div class="ego-ai-provider-empty"><i class="bi bi-plug"></i><strong>Chưa có kết nối AI</strong><span>Thêm OpenAI, Gemini, Claude, OpenRouter, DeepSeek, Groq hoặc API tương thích.</span><button type="button" class="ego-ai-settings-btn ego-ai-settings-btn--primary" data-open-add-provider>Thêm kết nối đầu tiên</button></div>
                        @endforelse
                    </div>
                </section>
            </main>

            <aside class="ego-ai-settings-side">
                <section><div class="ego-ai-settings-side__head"><i class="bi bi-shield-lock"></i><strong>Bảo mật API key</strong></div><p>Key được gửi từ server đến nhà cung cấp AI. Giao diện chat không nhận hoặc hiển thị key.</p></section>
                <section><div class="ego-ai-settings-side__head"><i class="bi bi-diagram-3"></i><strong>Hỗ trợ nhiều chuẩn</strong></div><ul><li>OpenAI Responses API</li><li>OpenAI-compatible Chat Completions</li><li>Google Gemini generateContent</li><li>Anthropic Messages API</li></ul></section>
                <section class="ego-ai-settings-side--warn"><div class="ego-ai-settings-side__head"><i class="bi bi-exclamation-triangle"></i><strong>Lưu ý vận hành</strong></div><p>Chỉ bật một kết nối mặc định đã kiểm tra thành công. Không dùng key cá nhân chung cho nhiều hệ thống.</p></section>
            </aside>
        </div>
    </div>

    <div class="ego-ai-modal" id="egoAiProviderModal" aria-hidden="true">
        <div class="ego-ai-modal__backdrop" data-close-provider></div>
        <div class="ego-ai-modal__dialog">
            <header><div><span>KẾT NỐI MỚI</span><h2>Thêm API AI</h2><p>API key sẽ được mã hóa ngay khi lưu.</p></div><button type="button" data-close-provider><i class="bi bi-x-lg"></i></button></header>
            <form method="POST" action="{{ route('admin.settings.ai.providers.store') }}" class="ego-ai-provider-form">
                @csrf
                <div class="ego-ai-form-grid">
                    <label><span>Tên kết nối</span><input type="text" name="name" value="{{ old('name') }}" placeholder="Ví dụ: OpenAI công ty" required maxlength="100"></label>
                    <label><span>Nhà cung cấp</span><select name="provider_type" required data-provider-type>@foreach($providerTypes as $key => $label)<option value="{{ $key }}" @selected(old('provider_type', 'openai') === $key)>{{ $label }}</option>@endforeach</select></label>
                    <label class="ego-ai-col-2"><span>Base URL <small>có thể để trống với OpenAI, Gemini, Claude, OpenRouter, DeepSeek, Groq</small></span><input type="url" name="base_url" value="{{ old('base_url') }}" placeholder="https://api.example.com/v1" data-base-url></label>
                    <label><span>Model chat</span><input type="text" name="model" value="{{ old('model') }}" placeholder="Nhập đúng model được cấp" required maxlength="160"></label>
                    <label><span>Model nhanh <small>không bắt buộc</small></span><input type="text" name="fast_model" value="{{ old('fast_model') }}" maxlength="160"></label>
                    <label class="ego-ai-col-2"><span>API key</span><div class="ego-ai-secret"><input type="password" name="api_key" required autocomplete="new-password" maxlength="2000" placeholder="Dán API key tại đây"><button type="button" data-toggle-secret><i class="bi bi-eye"></i></button></div></label>
                    <label><span>Organization <small>tùy chọn</small></span><input type="text" name="organization" value="{{ old('organization') }}" maxlength="160"></label>
                    <label><span>Project <small>tùy chọn</small></span><input type="text" name="project" value="{{ old('project') }}" maxlength="160"></label>
                    <label><span>Timeout</span><div class="ego-ai-input-unit"><input type="number" name="timeout_seconds" value="{{ old('timeout_seconds', 60) }}" min="10" max="180" required><b>giây</b></div></label>
                    <label><span>Max output token</span><input type="number" name="max_output_tokens" value="{{ old('max_output_tokens', 1600) }}" min="128" max="16000" required></label>
                    <label><span>Temperature</span><input type="number" name="temperature" value="{{ old('temperature', '0.20') }}" min="0" max="2" step="0.05" required></label>
                    <div class="ego-ai-switches"><label><input type="checkbox" name="is_active" value="1" checked><span></span><b>Bật kết nối</b></label><label><input type="checkbox" name="is_default" value="1" {{ $providers->isEmpty() ? 'checked' : '' }}><span></span><b>Dùng mặc định</b></label></div>
                </div>
                <footer><button type="button" class="ego-ai-settings-btn" data-close-provider>Hủy</button><button type="submit" class="ego-ai-settings-btn ego-ai-settings-btn--primary"><i class="bi bi-shield-check"></i> Mã hóa & lưu API</button></footer>
            </form>
        </div>
    </div>

    <div class="ego-ai-test-toast" id="egoAiTestToast"><i class="bi bi-arrow-repeat"></i><div><strong>Đang kiểm tra kết nối...</strong><span>Vui lòng chờ phản hồi từ API.</span></div></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/ego-ai-settings.js') }}?v={{ file_exists(public_path('js/ego-ai-settings.js')) ? filemtime(public_path('js/ego-ai-settings.js')) : '1.0.0' }}" defer></script>
@endpush
