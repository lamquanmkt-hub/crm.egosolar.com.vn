@extends('layouts.app')

@section('title', 'EGO AI Copilot')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-ai-copilot.css') }}?v={{ file_exists(public_path('css/ego-ai-copilot.css')) ? filemtime(public_path('css/ego-ai-copilot.css')) : '2.0.0' }}">
@endpush

@section('content')
@php
    $modules = collect($accessProfile['modules'] ?? []);
    $promptMap = [
        'orders' => 'Tóm tắt các đơn hàng và công nợ cần ưu tiên xử lý trong tháng này.',
        'customers' => 'Tìm các khách hàng đang thuộc phạm vi tôi phụ trách.',
        'inventory' => 'Kiểm tra các sản phẩm sắp hết hoặc đã hết hàng trong kho.',
        'tasks' => 'Tìm các công việc của tôi đang quá hạn hoặc sắp đến hạn.',
        'sites' => 'Tóm tắt các công trình và lịch bảo hành cần chú ý.',
        'payment_requests' => 'Tìm các đề nghị thanh toán đang chờ xử lý trong phạm vi của tôi.',
        'attendance' => 'Hôm nay tôi đã chấm công chưa?',
        'marketing' => 'Tóm tắt lead Marketing và chiến dịch trong tháng này.',
        'hr' => 'Tóm tắt tình hình nhân sự, nghỉ phép và nhu cầu tuyển dụng tháng này.',
    ];
@endphp
<div class="ego-ai-page"
     id="egoAiApp"
     data-create-url="{{ route('ai.conversations.create') }}"
     data-send-url="{{ route('ai.messages.send') }}"
     data-current-path="{{ request()->getRequestUri() }}"
     data-daily-limit="{{ (int) ($accessProfile['daily_limit'] ?? 0) }}"
     data-used-today="{{ (int) $todayUsage }}">
    <div class="ego-ai-shell">
        <aside class="ego-ai-history" id="egoAiHistory">
            <div class="ego-ai-history__head">
                <div>
                    <span>TRỢ LÝ CÔNG VIỆC</span>
                    <strong>EGO AI Copilot</strong>
                </div>
                <button type="button" class="ego-ai-icon-btn" id="egoAiNew" title="Cuộc trò chuyện mới">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>

            <label class="ego-ai-searchbox">
                <i class="bi bi-search"></i>
                <input type="search" id="egoAiConversationSearch" placeholder="Tìm cuộc trò chuyện...">
            </label>

            <div class="ego-ai-history__list" id="egoAiConversationList">
                @forelse($conversations as $conversation)
                    <button type="button"
                            class="ego-ai-conversation-item"
                            data-conversation-id="{{ $conversation->id }}"
                            data-show-url="{{ route('ai.conversations.show', $conversation) }}"
                            data-delete-url="{{ route('ai.conversations.destroy', $conversation) }}"
                            data-title="{{ $conversation->title }}">
                        <span class="ego-ai-conversation-item__icon"><i class="bi bi-chat-square-text"></i></span>
                        <span class="ego-ai-conversation-item__copy">
                            <strong>{{ $conversation->title }}</strong>
                            <small>{{ optional($conversation->last_message_at)->format('d/m H:i') }} · {{ $conversation->messages_count }} tin</small>
                        </span>
                        <span class="ego-ai-conversation-item__delete" data-delete-conversation title="Xóa"><i class="bi bi-trash3"></i></span>
                    </button>
                @empty
                    <div class="ego-ai-history__empty" id="egoAiHistoryEmpty">
                        <i class="bi bi-chat-dots"></i>
                        <span>Chưa có cuộc trò chuyện</span>
                    </div>
                @endforelse
            </div>

            <div class="ego-ai-history__foot">
                <div class="ego-ai-security-note">
                    <i class="bi bi-shield-check"></i>
                    <span>CRM không cho xem thì AI tuyệt đối không được biết.</span>
                </div>
                @if($canManageProviders)
                    <a href="{{ route('admin.settings.ai.index') }}" class="ego-ai-settings-link">
                        <i class="bi bi-sliders"></i> Cấu hình API AI
                    </a>
                @endif
                @if($canViewAudit)
                    <a href="{{ route('admin.settings.ai.governance.index') }}" class="ego-ai-settings-link ego-ai-settings-link--audit">
                        <i class="bi bi-shield-lock"></i> Phân quyền & nhật ký AI
                    </a>
                @endif
            </div>
        </aside>

        <main class="ego-ai-workspace">
            <header class="ego-ai-top">
                <button type="button" class="ego-ai-mobile-history" id="egoAiHistoryToggle" title="Lịch sử">
                    <i class="bi bi-layout-sidebar-inset"></i>
                </button>
                <div class="ego-ai-top__identity">
                    <div class="ego-ai-orb"><i class="bi bi-stars"></i></div>
                    <div>
                        <span>EGO AI COPILOT · PERMISSION AWARE</span>
                        <h1 id="egoAiConversationTitle">Cuộc trò chuyện mới</h1>
                    </div>
                </div>
                <div class="ego-ai-top__tools">
                    <div class="ego-ai-scope-chip" title="Phạm vi dữ liệu được Laravel kiểm soát">
                        <i class="bi bi-shield-check"></i>
                        <span>{{ count($modules) }} module được cấp</span>
                    </div>
                    <label class="ego-ai-provider-select">
                        <i class="bi bi-cpu"></i>
                        <select id="egoAiProvider" @disabled($providers->isEmpty() || !$canManageProviders)>
                            @forelse($providers as $provider)
                                <option value="{{ $provider->id }}" @selected(optional($defaultProvider)->id === $provider->id)>
                                    {{ $provider->name }} · {{ $provider->model }}
                                </option>
                            @empty
                                <option value="">Chưa có API</option>
                            @endforelse
                        </select>
                    </label>
                    <button type="button" class="ego-ai-icon-btn" id="egoAiClear" title="Cuộc trò chuyện mới"><i class="bi bi-plus-lg"></i></button>
                </div>
            </header>

            <section class="ego-ai-messages" id="egoAiMessages" aria-live="polite">
                <div class="ego-ai-welcome" id="egoAiWelcome">
                    <div class="ego-ai-welcome__mark"><i class="bi bi-stars"></i></div>
                    <span>TRỢ LÝ AI THEO ĐÚNG ROLE & PERMISSION</span>
                    <h2>Hôm nay bạn cần xử lý việc gì?</h2>
                    <p>AI chỉ tra cứu đúng module và phạm vi dữ liệu mà tài khoản của bạn được cấp trong CRM.</p>

                    <div class="ego-ai-access-summary">
                        <div>
                            <span>Role</span>
                            <strong>{{ collect($accessProfile['roles'] ?? [])->map(fn($r) => str_replace('_', ' ', $r))->implode(', ') ?: 'Nhân viên' }}</strong>
                        </div>
                        <div>
                            <span>Phòng ban</span>
                            <strong>{{ $accessProfile['department_name'] ?? 'Chưa xác định' }}</strong>
                        </div>
                        <div>
                            <span>Hạn mức hôm nay</span>
                            <strong id="egoAiUsageText">{{ $todayUsage }}/{{ $accessProfile['daily_limit'] ?? 0 }}</strong>
                        </div>
                    </div>

                    @if($providers->isEmpty())
                        <div class="ego-ai-no-provider">
                            <i class="bi bi-plug"></i>
                            <div>
                                <strong>Chưa có kết nối AI</strong>
                                <span>Admin cần thêm API và kiểm tra kết nối trước.</span>
                            </div>
                            @if($canManageProviders)
                                <a href="{{ route('admin.settings.ai.index') }}">Cấu hình ngay</a>
                            @endif
                        </div>
                    @endif

                    <div class="ego-ai-suggestions">
                        @forelse($modules->take(6) as $module)
                            <button type="button" data-ai-prompt="{{ $promptMap[$module['key']] ?? ('Tóm tắt '.$module['label'].' trong phạm vi của tôi.') }}">
                                <i class="bi {{ $module['icon'] ?? 'bi-stars' }}"></i>
                                <span>
                                    <strong>{{ $module['label'] }}</strong>
                                    <small>{{ $module['scope_label'] }}</small>
                                </span>
                            </button>
                        @empty
                            <div class="ego-ai-no-access">
                                <i class="bi bi-shield-x"></i>
                                <span>Tài khoản chưa được cấp module dữ liệu AI nào.</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>

            <footer class="ego-ai-composer-wrap">
                <form class="ego-ai-composer" id="egoAiForm">
                    @csrf
                    <textarea id="egoAiInput" rows="1" maxlength="5000" placeholder="Hỏi EGO AI hoặc yêu cầu xử lý công việc..." @disabled($providers->isEmpty())></textarea>
                    <button type="submit" id="egoAiSend" @disabled($providers->isEmpty()) title="Gửi">
                        <i class="bi bi-arrow-up"></i>
                    </button>
                </form>
                <div class="ego-ai-composer-meta">
                    <span><i class="bi bi-shield-lock"></i> Phân quyền được chặn tại Laravel server</span>
                    <span>Enter để gửi · Shift + Enter xuống dòng</span>
                </div>
            </footer>
        </main>

        <aside class="ego-ai-context">
            <div class="ego-ai-context-card ego-ai-context-card--status">
                <div class="ego-ai-context-card__head">
                    <span>KẾT NỐI</span>
                    <i class="bi bi-broadcast-pin"></i>
                </div>
                @if($defaultProvider)
                    <strong>{{ $defaultProvider->name }}</strong>
                    <p>{{ $defaultProvider->model }}</p>
                    <div class="ego-ai-live"><span></span> Sẵn sàng</div>
                @else
                    <strong>Chưa cấu hình</strong>
                    <p>Thêm một API đang hoạt động.</p>
                    <div class="ego-ai-live ego-ai-live--off"><span></span> Chưa kết nối</div>
                @endif
            </div>

            <div class="ego-ai-context-card">
                <div class="ego-ai-context-card__head">
                    <span>PHẠM VI ĐƯỢC CẤP</span>
                    <i class="bi bi-key"></i>
                </div>
                <div class="ego-ai-module-list">
                    @forelse($modules as $module)
                        <div class="ego-ai-module-row">
                            <i class="bi {{ $module['icon'] ?? 'bi-check2-circle' }}"></i>
                            <span><strong>{{ $module['label'] }}</strong><small>{{ $module['scope_label'] }}</small></span>
                        </div>
                    @empty
                        <p>Không có module dữ liệu.</p>
                    @endforelse
                </div>
            </div>

            <div class="ego-ai-context-card ego-ai-context-card--warning">
                <div class="ego-ai-context-card__head">
                    <span>KIỂM SOÁT AN TOÀN</span>
                    <i class="bi bi-shield-exclamation"></i>
                </div>
                <p>AI không được tự mở rộng role, chạy SQL tự do, duyệt, xóa, xuất kho hoặc ghi nhận thanh toán.</p>
            </div>

            <div class="ego-ai-context-card ego-ai-context-card--live" id="egoAiLiveContext" hidden>
                <div class="ego-ai-context-card__head">
                    <span>NGỮ CẢNH VỪA DÙNG</span>
                    <i class="bi bi-database-check"></i>
                </div>
                <strong id="egoAiLiveContextTitle"></strong>
                <p id="egoAiLiveContextMeta"></p>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/ego-ai-copilot.js') }}?v={{ file_exists(public_path('js/ego-ai-copilot.js')) ? filemtime(public_path('js/ego-ai-copilot.js')) : '2.0.0' }}" defer></script>
@endpush
