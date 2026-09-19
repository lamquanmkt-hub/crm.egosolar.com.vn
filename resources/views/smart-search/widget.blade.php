@if(auth()->check())
    <div
        class="ego-smart-search"
        id="egoSmartSearch"
        data-bootstrap-url="{{ route('smart-search.bootstrap') }}"
        data-query-url="{{ route('smart-search.query') }}"
        aria-live="polite"
    >
        <button
            type="button"
            class="ego-smart-search__launcher"
            id="egoSmartSearchLauncher"
            aria-controls="egoSmartSearchPanel"
            aria-expanded="false"
            title="Mở Hỏi Đáp AI (Ctrl + K)"
        >
            <span class="ego-smart-search__launcher-icon">
                <i class="bi bi-search"></i>
            </span>
            <span class="ego-smart-search__launcher-text">Hỏi Đáp AI</span>
            <span class="ego-smart-search__launcher-key">⌘K</span>
        </button>

        <div class="ego-smart-search__backdrop" id="egoSmartSearchBackdrop"></div>

        <aside
            class="ego-smart-search__panel"
            id="egoSmartSearchPanel"
            role="dialog"
            aria-modal="true"
            aria-label="Hỏi Đáp AI"
        >
            <header class="ego-smart-search__header">
                <div class="ego-smart-search__brand">
                    <div class="ego-smart-search__brand-icon">
                        <i class="bi bi-search-heart"></i>
                    </div>
                    <div>
                        <strong>Hỏi Đáp AI</strong>
                        <span>Tra cứu dữ liệu CRM theo quyền tài khoản</span>
                    </div>
                </div>

                <button
                    type="button"
                    class="ego-smart-search__close"
                    id="egoSmartSearchClose"
                    aria-label="Đóng tìm kiếm"
                >
                    <i class="bi bi-x-lg"></i>
                </button>
            </header>

            <div class="ego-smart-search__scope" id="egoSmartSearchScope">
                <i class="bi bi-shield-check"></i>
                <span>Đang kiểm tra phạm vi quyền...</span>
            </div>

            <div class="ego-smart-search__conversation" id="egoSmartSearchConversation">
                <section class="ego-smart-search__welcome" id="egoSmartSearchWelcome">
                    <div class="ego-smart-search__welcome-mark">
                        <i class="bi bi-command"></i>
                    </div>
                    <h3>Bạn cần tìm gì?</h3>
                    <p>
                        Nhập mã đơn, tên khách hàng, SKU hoặc câu hỏi ngắn như
                        “doanh thu tháng này”, “công việc quá hạn”.
                    </p>

                    <div class="ego-smart-search__suggestions" id="egoSmartSearchSuggestions"></div>
                </section>
            </div>

            <form class="ego-smart-search__composer" id="egoSmartSearchForm">
                <label class="visually-hidden" for="egoSmartSearchInput">
                    Nội dung tìm kiếm
                </label>
                <div class="ego-smart-search__input-wrap">
                    <i class="bi bi-search"></i>
                    <textarea
                        id="egoSmartSearchInput"
                        name="q"
                        rows="1"
                        maxlength="180"
                        placeholder="Hỏi hoặc nhập mã đơn, khách hàng, sản phẩm..."
                        autocomplete="off"
                    ></textarea>
                    <button
                        type="submit"
                        class="ego-smart-search__send"
                        aria-label="Tìm kiếm"
                    >
                        <i class="bi bi-arrow-up"></i>
                    </button>
                </div>
                <div class="ego-smart-search__composer-note">
                    <span>
                        <i class="bi bi-lock"></i>
                        Chỉ đọc dữ liệu được cấp quyền
                    </span>
                    <span>Enter để tìm · Shift+Enter xuống dòng</span>
                </div>
            </form>
        </aside>
    </div>
@endif
