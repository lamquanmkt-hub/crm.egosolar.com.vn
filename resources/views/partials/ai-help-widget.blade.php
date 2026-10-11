{{-- Trợ lý hướng dẫn sử dụng: nút nổi góc phải mọi trang (chỉ hướng dẫn thao tác, không đọc dữ liệu kinh doanh). --}}
@auth
@php
    $egoHelpCss = public_path('css/ai-help-widget.css');
    $egoHelpJs = public_path('js/ai-help-widget.js');
@endphp
<link rel="stylesheet" href="{{ asset('css/ai-help-widget.css') }}?v={{ file_exists($egoHelpCss) ? filemtime($egoHelpCss) : '1' }}">
<div id="egoHelp" class="ego-help"
     data-endpoint="{{ route('ai.help.ask') }}"
     data-route="{{ optional(request()->route())->getName() }}"
     data-user="{{ auth()->id() }}">
    <button type="button" class="ego-help__fab" aria-label="Mở trợ lý hướng dẫn sử dụng" aria-expanded="false" aria-controls="egoHelpPanel">
        <span class="ego-help__fab-icon" aria-hidden="true"><i class="bi bi-chat-dots-fill"></i></span>
        <span class="ego-help__fab-label">Hỏi cách dùng</span>
    </button>

    <section id="egoHelpPanel" class="ego-help__panel" role="dialog" aria-label="Trợ lý hướng dẫn sử dụng" hidden>
        <header class="ego-help__head">
            <span class="ego-help__avatar" aria-hidden="true"><i class="bi bi-stars"></i></span>
            <div class="ego-help__title">
                <strong>Trợ lý hướng dẫn</strong>
                <small>Hỏi cách thao tác trên CRM</small>
            </div>
            <div class="ego-help__head-actions">
                <button type="button" class="ego-help__icon" data-help-reset title="Cuộc trò chuyện mới" aria-label="Cuộc trò chuyện mới"><i class="bi bi-arrow-counterclockwise"></i></button>
                <button type="button" class="ego-help__icon" data-help-close title="Đóng" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
            </div>
        </header>

        <div class="ego-help__body" data-help-log aria-live="polite">
            <div class="ego-help__welcome" data-help-welcome>
                <p>Xin chào {{ auth()->user()->name }}! Mình hướng dẫn cách dùng phần mềm, ví dụ:</p>
                <div class="ego-help__chips">
                    <button type="button" data-help-suggest>Trang này dùng để làm gì?</button>
                    <button type="button" data-help-suggest>Tạo đơn hàng và gửi duyệt thế nào?</button>
                    <button type="button" data-help-suggest>Quên chấm công thì làm sao?</button>
                    <button type="button" data-help-suggest>Tạo đề nghị thanh toán như thế nào?</button>
                </div>
                <p class="ego-help__note">Trợ lý chỉ hướng dẫn thao tác, không xem dữ liệu đơn hàng, công nợ hay chấm công của bạn.</p>
            </div>
        </div>

        <form class="ego-help__form" data-help-form>
            <textarea rows="1" maxlength="2000" placeholder="Nhập câu hỏi… (Enter để gửi)" data-help-input aria-label="Câu hỏi"></textarea>
            <button type="submit" class="ego-help__send" aria-label="Gửi"><i class="bi bi-send-fill"></i></button>
        </form>
    </section>
</div>
<script src="{{ asset('js/ai-help-widget.js') }}?v={{ file_exists($egoHelpJs) ? filemtime($egoHelpJs) : '1' }}" defer></script>
@endauth
