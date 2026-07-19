@extends('layouts.app')

@section('title', 'Chat công việc')

@section('content')
<div class="msn-chat-page"
     data-myid="{{ (int) auth()->id() }}"
     data-initial="{{ $initialConversationId ? (int) $initialConversationId : '' }}"
     data-csrf="{{ csrf_token() }}">

    <div class="msn-shell">
        <aside class="msn-left">
            <div class="msn-left-head">
                <div class="msn-title-row">
                    <div>
                        <div class="msn-title">Đoạn chat</div>
                        <div class="msn-subtitle">CRM Messenger</div>
                    </div>

                    <div class="msn-head-actions">
                        <button type="button" class="msn-mini-btn" onclick="crmChatOpenCreateGroup()" title="Tạo nhóm">✎</button>
                        <button type="button" class="msn-mini-btn" onclick="crmChatReloadAll()" title="Làm mới">↻</button>
                    </div>
                </div>

                <div class="msn-search">
                    <span>⌕</span>
                    <input id="chatSearch" type="text" placeholder="Tìm kiếm trong đoạn chat">
                </div>

                <div class="msn-tabs">
                    <button type="button" class="active" data-tab="conversations">Tất cả</button>
                    <button type="button" data-tab="users">Nhân sự</button>
                    <button type="button" data-tab="departments">Phòng ban</button>
                </div>
            </div>

            <div class="msn-list active" id="conversationList"></div>
            <div class="msn-list" id="userList"></div>
            <div class="msn-list" id="departmentList"></div>
        </aside>

        <main class="msn-main">
            <div class="msn-chat-head">
                <div class="msn-room-avatar" id="roomAvatar">💬</div>

                <div class="msn-room-info">
                    <div class="msn-room-name" id="roomName">Chọn đoạn chat</div>
                    <div class="msn-room-sub" id="roomSub">Tin nhắn nội bộ CRM</div>
                </div>

                <div class="msn-room-actions">
                    <button type="button" class="msn-circle-btn" title="Gọi nội bộ">☎</button>
                    <button type="button" class="msn-circle-btn" title="Video">▣</button>
                    <button type="button" class="msn-circle-btn" onclick="crmChatToggleRightPanel()" title="Thông tin">ⓘ</button>
                </div>
            </div>

            <div class="msn-chat-body">
                <div class="msn-messages" id="chatMessages">
                    <div class="msn-empty">
                        <div class="msn-empty-avatar">💬</div>
                        <b>Chọn người, nhóm hoặc phòng ban</b>
                        <span>Gửi tin nhắn, ảnh, file và tạo công việc nhanh ngay trong CRM.</span>
                    </div>
                </div>

                <div class="msn-floating-down" id="scrollDownBtn" onclick="crmChatScrollBottom()">↓</div>
            </div>

            <div class="msn-file-preview" id="filePreview"></div>

            <form class="msn-composer" id="chatComposer">
                <button class="msn-compose-icon" type="button" onclick="document.getElementById('chatFiles').click()" title="Gửi file">📎</button>
                <button class="msn-compose-icon" type="button" onclick="document.getElementById('chatFiles').click()" title="Gửi ảnh">🖼</button>

                <input id="chatFiles" name="attachments[]" type="file" multiple hidden>

                <div class="msn-input-wrap">
                    <textarea id="chatInput" name="body" rows="1" placeholder="Aa"></textarea>
                </div>

                <button class="msn-like-btn" type="submit">➤</button>
            </form>
        </main>

        <aside class="msn-right" id="rightPanel">
            <div class="msn-profile-card">
                <div class="msn-profile-avatar" id="rightAvatar">💬</div>
                <div class="msn-profile-name" id="rightName">CRM Chat</div>
                <div class="msn-profile-type" id="rightType">Chọn đoạn chat để xem thông tin</div>

                <div class="msn-profile-actions">
                    <button type="button">
                        <span>🔔</span>
                        <b>Tắt thông báo</b>
                    </button>
                    <button type="button" onclick="crmChatToggleTask()">
                        <span>📌</span>
                        <b>Việc nhanh</b>
                    </button>
                    <button type="button">
                        <span>🔍</span>
                        <b>Tìm kiếm</b>
                    </button>
                </div>
            </div>

            <div class="msn-info-section">
                <button type="button" onclick="crmToggleInfo('infoBox')">
                    Thông tin về đoạn chat
                    <span>⌄</span>
                </button>
                <div id="infoBox" class="msn-info-box open">
                    <div class="msn-info-line">
                        <span>Loại</span>
                        <b id="infoType">—</b>
                    </div>
                    <div class="msn-info-line">
                        <span>Thành viên</span>
                        <b id="infoMembers">—</b>
                    </div>
                    <div class="msn-info-line">
                        <span>Trạng thái</span>
                        <b>Đang hoạt động</b>
                    </div>
                </div>
            </div>

            <div class="msn-info-section">
                <button type="button" onclick="crmToggleInfo('quickTaskPanel')">
                    Tạo công việc nhanh
                    <span>⌄</span>
                </button>

                <div id="quickTaskPanel" class="msn-task-panel">
                    <form id="quickTaskForm">
                        <label>Tiêu đề việc</label>
                        <input name="title" type="text" placeholder="VD: Chốt phương án..." required>

                        <label>Giao cho</label>
                        <select name="assignee_id" id="quickTaskAssignee">
                            <option value="">Chưa giao</option>
                        </select>

                        <label>Độ ưu tiên</label>
                        <select name="priority">
                            <option value="normal">Bình thường</option>
                            <option value="high">Cao</option>
                            <option value="urgent">Gấp</option>
                        </select>

                        <label>Hạn xử lý</label>
                        <input name="due_at" type="date">

                        <label>Mô tả</label>
                        <textarea name="description" rows="3" placeholder="Nội dung cần xử lý..."></textarea>

                        <button class="msn-task-submit" type="submit">Tạo công việc</button>
                    </form>
                </div>
            </div>

            <div class="msn-info-section">
                <button type="button" onclick="crmToggleInfo('fileBox')">
                    File phương tiện và file
                    <span>⌄</span>
                </button>
                <div id="fileBox" class="msn-info-box">
                    <div class="msn-muted">Ảnh và file đã gửi sẽ hiển thị trong đoạn chat.</div>
                </div>
            </div>
        </aside>
    </div>
</div>

<div class="msn-modal" id="groupModal">
    <div class="msn-modal-card">
        <div class="msn-modal-head">
            <b>Tạo nhóm xử lý công việc</b>
            <button type="button" onclick="crmChatCloseCreateGroup()">×</button>
        </div>

        <form id="groupForm">
            <label>Tên nhóm</label>
            <input name="name" type="text" placeholder="VD: Team triển khai dự án" required>

            <label>Thành viên</label>
            <div class="msn-user-checks" id="groupUsers"></div>

            <button class="msn-task-submit" type="submit">Tạo nhóm</button>
        </form>
    </div>
</div>

<style>
    .msn-chat-page {
        --bg: #18191a;
        --panel: #242526;
        --panel2: #1f2023;
        --hover: #303136;
        --line: rgba(255,255,255,.08);
        --text: #e4e6eb;
        --muted: #b0b3b8;
        --muted2: #7f858d;
        --blue: #2374e1;
        --violet: #8b5cf6;
        --green: #22c55e;
        height: calc(100vh - 72px);
        overflow: hidden;
        background: var(--bg);
        color: var(--text);
        padding: 0;
        margin: -1px 0 0;
    }

    .msn-shell {
        display: grid;
        grid-template-columns: 320px minmax(0, 1fr) 320px;
        height: 100%;
        min-height: 0;
        background: var(--bg);
    }

    .msn-left {
        min-width: 0;
        border-right: 1px solid var(--line);
        background: var(--panel);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .msn-left-head {
        padding: 14px 12px 10px;
        border-bottom: 1px solid rgba(255,255,255,.04);
    }

    .msn-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }

    .msn-title {
        font-size: 22px;
        line-height: 1.1;
        font-weight: 950;
        letter-spacing: -.03em;
    }

    .msn-subtitle {
        color: var(--muted);
        font-size: 12px;
        margin-top: 3px;
    }

    .msn-head-actions {
        display: flex;
        gap: 8px;
    }

    .msn-mini-btn,
    .msn-circle-btn,
    .msn-compose-icon {
        border: 0;
        background: #3a3b3c;
        color: var(--text);
        cursor: pointer;
        display: inline-grid;
        place-items: center;
        transition: .15s;
    }

    .msn-mini-btn {
        width: 34px;
        height: 34px;
        border-radius: 999px;
        font-weight: 900;
    }

    .msn-mini-btn:hover,
    .msn-circle-btn:hover,
    .msn-compose-icon:hover {
        background: #4b4c50;
    }

    .msn-search {
        height: 38px;
        border-radius: 999px;
        background: #3a3b3c;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0 12px;
        color: var(--muted);
    }

    .msn-search input {
        flex: 1;
        min-width: 0;
        border: 0;
        outline: 0;
        background: transparent;
        color: var(--text);
        font-size: 13px;
    }

    .msn-search input::placeholder {
        color: var(--muted);
    }

    .msn-tabs {
        display: flex;
        gap: 8px;
        margin-top: 12px;
        overflow-x: auto;
        padding-bottom: 2px;
    }

    .msn-tabs button {
        border: 0;
        background: transparent;
        color: var(--muted);
        height: 32px;
        border-radius: 999px;
        padding: 0 12px;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        white-space: nowrap;
    }

    .msn-tabs button.active {
        background: rgba(35,116,225,.18);
        color: #71a7ff;
    }

    .msn-list {
        display: none;
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 8px;
        scrollbar-width: thin;
    }

    .msn-list.active {
        display: block;
    }

    .msn-list::-webkit-scrollbar,
    .msn-messages::-webkit-scrollbar {
        width: 8px;
    }

    .msn-list::-webkit-scrollbar-thumb,
    .msn-messages::-webkit-scrollbar-thumb {
        background: #55575f;
        border-radius: 999px;
    }

    .msn-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px;
        border-radius: 12px;
        cursor: pointer;
        color: var(--text);
        text-decoration: none;
        margin-bottom: 4px;
        position: relative;
    }

    .msn-item:hover {
        background: var(--hover);
    }

    .msn-item.active {
        background: #263951;
    }

    .msn-avatar {
        width: 48px;
        height: 48px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        background: linear-gradient(135deg, #dbeafe, #93c5fd);
        color: #0f172a;
        font-weight: 950;
        position: relative;
        box-shadow: inset 0 0 0 2px rgba(255,255,255,.15);
    }

    .msn-avatar.group {
        background: linear-gradient(135deg, #ede9fe, #a78bfa);
    }

    .msn-avatar.department {
        background: linear-gradient(135deg, #dcfce7, #34d399);
    }

    .msn-online-dot {
        position: absolute;
        right: 1px;
        bottom: 2px;
        width: 13px;
        height: 13px;
        border-radius: 999px;
        background: var(--green);
        border: 2px solid var(--panel);
    }

    .msn-unread {
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 999px;
        background: #ef4444;
        color: #fff;
        font-size: 10px;
        font-weight: 950;
        display: inline-grid;
        place-items: center;
    }

    .msn-item-main {
        min-width: 0;
        flex: 1;
    }

    .msn-item-top {
        display: flex;
        gap: 8px;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 3px;
    }

    .msn-item-name {
        min-width: 0;
        font-weight: 900;
        font-size: 14px;
        color: var(--text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .msn-item-time {
        color: var(--muted2);
        font-size: 11px;
        white-space: nowrap;
    }

    .msn-item-sub {
        color: var(--muted);
        font-size: 12px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .msn-main {
        min-width: 0;
        display: flex;
        flex-direction: column;
        background: var(--bg);
        overflow: hidden;
    }

    .msn-chat-head {
        height: 64px;
        border-bottom: 1px solid var(--line);
        background: var(--panel);
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        flex: 0 0 auto;
    }

    .msn-room-avatar {
        width: 42px;
        height: 42px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, #dbeafe, #93c5fd);
        color: #0f172a;
        font-weight: 950;
        flex: 0 0 auto;
    }

    .msn-room-info {
        min-width: 0;
        flex: 1;
    }

    .msn-room-name {
        font-size: 15px;
        font-weight: 950;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .msn-room-sub {
        font-size: 12px;
        color: var(--muted);
        margin-top: 2px;
    }

    .msn-room-actions {
        display: flex;
        gap: 8px;
    }

    .msn-circle-btn {
        width: 36px;
        height: 36px;
        border-radius: 999px;
        color: #a78bfa;
        font-weight: 950;
    }

    .msn-chat-body {
        flex: 1;
        min-height: 0;
        position: relative;
        overflow: hidden;
        background:
            radial-gradient(720px 320px at 20% 8%, rgba(37,99,235,.08), transparent 60%),
            radial-gradient(640px 300px at 80% 28%, rgba(139,92,246,.08), transparent 55%),
            var(--bg);
    }

    .msn-messages {
        height: 100%;
        overflow-y: auto;
        padding: 18px 28px;
        scroll-behavior: smooth;
    }

    .msn-empty {
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        color: var(--muted);
        text-align: center;
        gap: 8px;
    }

    .msn-empty-avatar {
        width: 76px;
        height: 76px;
        border-radius: 999px;
        background: #3a3b3c;
        display: grid;
        place-items: center;
        font-size: 30px;
        margin-bottom: 4px;
    }

    .msn-empty b {
        color: var(--text);
        font-size: 16px;
    }

    .msn-msg-row {
        display: flex;
        gap: 8px;
        align-items: flex-end;
        margin-bottom: 3px;
    }

    .msn-msg-row.me {
        justify-content: flex-end;
    }

    .msn-msg-avatar {
        width: 28px;
        height: 28px;
        border-radius: 999px;
        background: #d1d5db;
        color: #111827;
        display: grid;
        place-items: center;
        font-size: 11px;
        font-weight: 950;
        flex: 0 0 auto;
    }

    .msn-bubble {
        max-width: min(68%, 680px);
        border-radius: 18px;
        padding: 8px 12px;
        background: #303136;
        color: var(--text);
        font-size: 14px;
        line-height: 1.45;
        position: relative;
        word-break: break-word;
    }

    .msn-msg-row.me .msn-bubble {
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        color: #fff;
        border-bottom-right-radius: 6px;
    }

    .msn-msg-row:not(.me) .msn-bubble {
        border-bottom-left-radius: 6px;
    }

    .msn-msg-name {
        font-size: 11px;
        color: var(--muted);
        margin: 8px 0 3px 38px;
        font-weight: 800;
    }

    .msn-msg-row.me + .msn-msg-name,
    .msn-msg-row.me .msn-msg-name {
        display: none;
    }

    .msn-text {
        white-space: pre-wrap;
    }

    .msn-time {
        font-size: 10px;
        opacity: .68;
        margin-top: 4px;
        text-align: right;
    }

    .msn-file {
        margin-top: 8px;
        overflow: hidden;
        border-radius: 14px;
    }

    .msn-file img {
        display: block;
        max-width: 340px;
        max-height: 280px;
        border-radius: 14px;
        object-fit: cover;
    }

    .msn-file-link {
        display: flex;
        align-items: center;
        gap: 10px;
        border-radius: 14px;
        padding: 10px 12px;
        background: rgba(255,255,255,.12);
        color: inherit;
        text-decoration: none;
        font-weight: 850;
    }

    .msn-floating-down {
        position: absolute;
        left: 50%;
        bottom: 18px;
        transform: translateX(-50%);
        width: 34px;
        height: 34px;
        border-radius: 999px;
        background: #3a3b3c;
        color: var(--text);
        display: none;
        place-items: center;
        cursor: pointer;
        box-shadow: 0 12px 28px rgba(0,0,0,.35);
    }

    .msn-floating-down.show {
        display: grid;
    }

    .msn-file-preview {
        display: none;
        flex-wrap: wrap;
        gap: 8px;
        padding: 9px 14px;
        border-top: 1px solid var(--line);
        background: var(--panel);
        flex: 0 0 auto;
    }

    .msn-file-preview.show {
        display: flex;
    }

    .msn-file-chip {
        border-radius: 999px;
        padding: 6px 10px;
        background: #3a3b3c;
        color: var(--text);
        font-size: 12px;
        font-weight: 800;
    }

    .msn-composer {
        min-height: 56px;
        border-top: 1px solid var(--line);
        background: var(--panel);
        display: flex;
        align-items: flex-end;
        gap: 8px;
        padding: 8px 12px;
        flex: 0 0 auto;
    }

    .msn-compose-icon {
        width: 36px;
        height: 36px;
        border-radius: 999px;
        color: #60a5fa;
        font-size: 16px;
        flex: 0 0 auto;
    }

    .msn-input-wrap {
        flex: 1;
        min-width: 0;
        background: #3a3b3c;
        border-radius: 20px;
        display: flex;
        align-items: center;
    }

    .msn-input-wrap textarea {
        width: 100%;
        min-height: 36px;
        max-height: 130px;
        resize: none;
        border: 0;
        outline: 0;
        background: transparent;
        color: var(--text);
        padding: 9px 13px;
        font-size: 14px;
    }

    .msn-input-wrap textarea::placeholder {
        color: var(--muted);
    }

    .msn-like-btn {
        width: 38px;
        height: 38px;
        border-radius: 999px;
        border: 0;
        cursor: pointer;
        color: #fff;
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        display: grid;
        place-items: center;
        font-weight: 950;
        flex: 0 0 auto;
    }

    .msn-right {
        min-width: 0;
        border-left: 1px solid var(--line);
        background: var(--panel);
        overflow-y: auto;
        padding: 14px;
    }

    .msn-profile-card {
        text-align: center;
        padding: 12px 8px 18px;
    }

    .msn-profile-avatar {
        width: 86px;
        height: 86px;
        border-radius: 999px;
        background: linear-gradient(135deg, #dbeafe, #93c5fd);
        color: #0f172a;
        display: grid;
        place-items: center;
        margin: 0 auto 10px;
        font-size: 24px;
        font-weight: 950;
    }

    .msn-profile-name {
        font-size: 16px;
        font-weight: 950;
        margin-bottom: 4px;
    }

    .msn-profile-type {
        color: var(--muted);
        font-size: 12px;
    }

    .msn-profile-actions {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin-top: 18px;
    }

    .msn-profile-actions button {
        border: 0;
        background: transparent;
        color: var(--muted);
        cursor: pointer;
        display: flex;
        align-items: center;
        flex-direction: column;
        gap: 5px;
        font-size: 11px;
        font-weight: 800;
    }

    .msn-profile-actions span {
        width: 36px;
        height: 36px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        background: #3a3b3c;
        color: var(--text);
    }

    .msn-info-section {
        border-top: 1px solid var(--line);
        padding: 10px 0;
    }

    .msn-info-section > button {
        width: 100%;
        height: 36px;
        border: 0;
        background: transparent;
        color: var(--text);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 900;
        cursor: pointer;
        text-align: left;
    }

    .msn-info-box,
    .msn-task-panel {
        display: none;
        padding: 8px 0;
    }

    .msn-info-box.open,
    .msn-task-panel.open {
        display: block;
    }

    .msn-info-line {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        color: var(--muted);
        font-size: 12px;
        padding: 7px 0;
    }

    .msn-info-line b {
        color: var(--text);
        text-align: right;
    }

    .msn-muted {
        color: var(--muted);
        font-size: 12px;
        line-height: 1.45;
    }

    .msn-task-panel label,
    .msn-modal-card label {
        display: block;
        color: var(--muted);
        font-size: 12px;
        font-weight: 900;
        margin: 10px 0 5px;
    }

    .msn-task-panel input,
    .msn-task-panel select,
    .msn-task-panel textarea,
    .msn-modal-card input {
        width: 100%;
        border: 0;
        outline: 0;
        border-radius: 12px;
        background: #3a3b3c;
        color: var(--text);
        padding: 10px 12px;
        font-size: 13px;
    }

    .msn-task-submit {
        width: 100%;
        height: 40px;
        border: 0;
        border-radius: 12px;
        margin-top: 12px;
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        color: #fff;
        font-weight: 950;
        cursor: pointer;
    }

    .msn-modal {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.56);
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
    }

    .msn-modal.open {
        display: flex;
    }

    .msn-modal-card {
        width: min(560px, 100%);
        background: var(--panel);
        color: var(--text);
        border: 1px solid var(--line);
        border-radius: 20px;
        padding: 16px;
        box-shadow: 0 32px 90px rgba(0,0,0,.45);
    }

    .msn-modal-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .msn-modal-head button {
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 999px;
        background: #3a3b3c;
        color: var(--text);
        cursor: pointer;
    }

    .msn-user-checks {
        max-height: 260px;
        overflow-y: auto;
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 8px;
    }

    .msn-user-check {
        display: flex;
        gap: 9px;
        align-items: center;
        padding: 8px;
        border-radius: 10px;
        color: var(--text);
    }

    .msn-user-check:hover {
        background: var(--hover);
    }

    @media (max-width: 1280px) {
        .msn-shell {
            grid-template-columns: 320px minmax(0, 1fr);
        }

        .msn-right {
            position: fixed;
            right: 16px;
            top: 92px;
            bottom: 16px;
            width: 320px;
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: 0 28px 80px rgba(0,0,0,.45);
            z-index: 99;
            display: none;
        }

        .msn-right.open {
            display: block;
        }
    }

    @media (max-width: 768px) {
        .msn-chat-page {
            height: auto;
            min-height: calc(100vh - 72px);
            overflow: visible;
        }

        .msn-shell {
            grid-template-columns: 1fr;
            height: auto;
            min-height: calc(100vh - 72px);
        }

        .msn-left {
            height: 330px;
            border-right: 0;
            border-bottom: 1px solid var(--line);
        }

        .msn-main {
            height: calc(100vh - 402px);
            min-height: 520px;
        }

        .msn-right {
            width: calc(100vw - 24px);
            left: 12px;
            right: 12px;
        }

        .msn-messages {
            padding: 14px;
        }

        .msn-bubble {
            max-width: 82%;
        }
    }

/* CHAT_REAL_AVATAR_START */
.msn-avatar-img,
.msn-room-avatar-img,
.msn-profile-avatar-img,
.msn-msg-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: inherit;
    display: block;
}

.msn-msg-avatar-img {
    border-radius: 999px;
}
/* CHAT_REAL_AVATAR_END */

</style>

<script>
(function () {
    const root = document.querySelector('.msn-chat-page');
    const csrf = root.dataset.csrf;
    const myId = Number(root.dataset.myid || 0);
    const initialConversationId = root.dataset.initial ? Number(root.dataset.initial) : null;

    let conversations = [];
    let users = [];
    let departments = [];
    let activeConversationId = null;
    let activeDepartmentId = null;
    let activeUserId = null;
    let activeConversationMeta = null;
    let lastMessageId = 0;
    let pollingTimer = null;
    let selectedFiles = [];

    const conversationList = document.getElementById('conversationList');
    const userList = document.getElementById('userList');
    const departmentList = document.getElementById('departmentList');
    const chatMessages = document.getElementById('chatMessages');
    const roomName = document.getElementById('roomName');
    const roomSub = document.getElementById('roomSub');
    const roomAvatar = document.getElementById('roomAvatar');
    const rightAvatar = document.getElementById('rightAvatar');
    const rightName = document.getElementById('rightName');
    const rightType = document.getElementById('rightType');
    const infoType = document.getElementById('infoType');
    const infoMembers = document.getElementById('infoMembers');
    const chatInput = document.getElementById('chatInput');
    const chatFiles = document.getElementById('chatFiles');
    const filePreview = document.getElementById('filePreview');
    const composer = document.getElementById('chatComposer');
    const searchInput = document.getElementById('chatSearch');
    const quickTaskForm = document.getElementById('quickTaskForm');
    const quickTaskAssignee = document.getElementById('quickTaskAssignee');
    const scrollDownBtn = document.getElementById('scrollDownBtn');

    function esc(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function headers(json = true) {
        const h = {
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (json) {
            h['Accept'] = 'application/json';
        }

        return h;
    }

    async function getJson(url) {
        const res = await fetch(url, { headers: headers() });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return await res.json();
    }

    async function postJson(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { ...headers(), 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            const text = await res.text();
            throw new Error(text || ('HTTP ' + res.status));
        }

        return await res.json();
    }

    async function crmChatReloadAll() {
        const [c, u, d] = await Promise.all([
            getJson('/chat/conversations/json'),
            getJson('/chat/users/json'),
            getJson('/chat/departments/json'),
        ]);

        conversations = c.conversations || [];
        users = u.users || [];
        departments = d.departments || [];

        renderLists();
        renderAssignees();
        renderGroupUsers();

        if (initialConversationId && !activeConversationId) {
            openConversation(initialConversationId);
        }
    }

    window.crmChatReloadAll = crmChatReloadAll;

    function filtered(items, fields) {
        const q = (searchInput.value || '').trim().toLowerCase();
        if (!q) return items;

        return items.filter(item => fields.some(f => String(item[f] || '').toLowerCase().includes(q)));
    }

    function avatarHtml(url, fallback, className = 'msn-avatar-img') {
        if (url) {
            return `<img class="${className}" src="${esc(url)}" alt="${esc(fallback || 'Avatar')}" loading="lazy">`;
        }

        return esc(fallback || 'CH');
    }
    function avatarClass(type) {
        if (type === 'department') return 'department';
        if (type === 'group') return 'group';
        return '';
    }

    function typeLabel(type) {
        if (type === 'department') return 'Phòng ban';
        if (type === 'group') return 'Nhóm';
        return 'Chat cá nhân';
    }

    function renderLists() {
        const convs = filtered(conversations, ['name', 'last_message']);

        conversationList.innerHTML = convs.map(c => `
            <div class="msn-item ${Number(c.id) === Number(activeConversationId) ? 'active' : ''}" onclick="crmChatOpenConversation(${Number(c.id)})">
                <div class="msn-avatar ${avatarClass(c.type)}">
                    ${avatarHtml(c.avatar_url, c.initials || 'CH')}
                    <span class="msn-online-dot"></span>
                </div>
                <div class="msn-item-main">
                    <div class="msn-item-top">
                        <div class="msn-item-name">${esc(c.name)}</div>
                        <div class="msn-item-time">${esc(c.last_message_at || '')}</div>
                    </div>
                    <div class="msn-item-sub">
                        <span>${esc(c.last_message || 'Chưa có tin nhắn')}</span>
                        ${Number(c.unread || 0) > 0 ? `<span class="msn-unread">${Number(c.unread)}</span>` : ''}
                    </div>
                </div>
            </div>
        `).join('');

        const us = filtered(users, ['name', 'email', 'department_name']);

        userList.innerHTML = us.map(u => `
            <div class="msn-item ${Number(u.id) === Number(activeUserId) ? 'active' : ''}" onclick="crmChatStartDirect(${Number(u.id)})">
                <div class="msn-avatar">
                    ${avatarHtml(u.avatar_url, (u.name || 'U').slice(0,2).toUpperCase())}
                    <span class="msn-online-dot"></span>
                </div>
                <div class="msn-item-main">
                    <div class="msn-item-top">
                        <div class="msn-item-name">${esc(u.name)}</div>
                    </div>
                    <div class="msn-item-sub">${esc(u.department_name || u.email || 'Nhân sự')}</div>
                </div>
            </div>
        `).join('');

        const deps = filtered(departments, ['name', 'code']);

        departmentList.innerHTML = deps.map(d => `
            <div class="msn-item ${Number(d.id) === Number(activeDepartmentId) ? 'active' : ''}" onclick="crmChatStartDepartment(${Number(d.id)})">
                <div class="msn-avatar department">${esc((d.code || d.name || 'PB').slice(0,2).toUpperCase())}</div>
                <div class="msn-item-main">
                    <div class="msn-item-top">
                        <div class="msn-item-name">${esc(d.name)}</div>
                    </div>
                    <div class="msn-item-sub">${Number(d.users_count || 0)} nhân sự · phòng ban</div>
                </div>
            </div>
        `).join('');
    }

    function renderAssignees() {
        quickTaskAssignee.innerHTML = `<option value="">Chưa giao</option>` + users.map(u => `
            <option value="${Number(u.id)}">${esc(u.name)}${u.department_name ? ' - ' + esc(u.department_name) : ''}</option>
        `).join('');
    }

    function renderGroupUsers() {
        const box = document.getElementById('groupUsers');
        box.innerHTML = users.map(u => `
            <label class="msn-user-check">
                <input type="checkbox" name="user_ids[]" value="${Number(u.id)}">
                <span>${esc(u.name)}${u.department_name ? ' · ' + esc(u.department_name) : ''}</span>
            </label>
        `).join('');
    }

    window.crmChatOpenConversation = openConversation;

    async function openConversation(id) {
        activeConversationId = Number(id);
        lastMessageId = 0;
        activeConversationMeta = conversations.find(x => Number(x.id) === Number(id)) || null;
        renderLists();

        chatMessages.innerHTML = `
            <div class="msn-empty">
                <div class="msn-empty-avatar">⏳</div>
                <b>Đang tải tin nhắn...</b>
                <span>Vui lòng chờ trong giây lát.</span>
            </div>
        `;

        await loadMessages(true);

        if (pollingTimer) clearInterval(pollingTimer);
        pollingTimer = setInterval(() => loadMessages(false), 2500);
    }

    async function loadMessages(reset) {
        if (!activeConversationId) return;

        const data = await getJson(`/chat/${activeConversationId}/messages/json?after_id=${reset ? 0 : lastMessageId}`);

        if (reset) {
            chatMessages.innerHTML = '';

            const name = data.conversation?.name || 'Cuộc trò chuyện';
            const type = data.conversation?.type || 'direct';
            const initials = (name || 'CH').slice(0, 2).toUpperCase();

            roomName.textContent = name;
            roomSub.textContent = typeLabel(type) + ' · đang hoạt động';
            if (data.conversation?.avatar_url) {
                roomAvatar.innerHTML = avatarHtml(data.conversation.avatar_url, initials, 'msn-room-avatar-img');
            } else {
                roomAvatar.textContent = initials;
            }

            if (data.conversation?.avatar_url) {
                rightAvatar.innerHTML = avatarHtml(data.conversation.avatar_url, initials, 'msn-profile-avatar-img');
            } else {
                rightAvatar.textContent = initials;
            }
            rightName.textContent = name;
            rightType.textContent = typeLabel(type);
            infoType.textContent = typeLabel(type);
            infoMembers.textContent = activeConversationMeta?.members_count ? activeConversationMeta.members_count + ' thành viên' : '—';
        }

        const messages = data.messages || [];

        if (reset && messages.length === 0) {
            chatMessages.innerHTML = `
                <div class="msn-empty">
                    <div class="msn-empty-avatar">✨</div>
                    <b>Chưa có tin nhắn</b>
                    <span>Gửi tin nhắn, ảnh hoặc file để bắt đầu.</span>
                </div>
            `;
        } else if (messages.length > 0) {
            const empty = chatMessages.querySelector('.msn-empty');
            if (empty) empty.remove();

            messages.forEach(appendMessage);
            crmChatScrollBottom();
        }

        messages.forEach(m => {
            lastMessageId = Math.max(lastMessageId, Number(m.id || 0));
        });
    }

    function appendMessage(m) {
        const me = Number(m.user_id) === myId;

        const row = document.createElement('div');
        row.className = `msn-msg-row ${me ? 'me' : ''}`;

        let fileHtml = '';

        if (m.attachment_url) {
            if (m.is_image) {
                fileHtml = `
                    <div class="msn-file">
                        <a href="${esc(m.attachment_url)}" target="_blank">
                            <img src="${esc(m.attachment_inline_url || m.attachment_url)}" alt="${esc(m.attachment_name || 'image')}">
                        </a>
                    </div>
                `;
            } else {
                fileHtml = `
                    <div class="msn-file">
                        <a class="msn-file-link" href="${esc(m.attachment_url)}" target="_blank">
                            <span>📎</span>
                            <span>${esc(m.attachment_name || 'Tải file')}</span>
                        </a>
                    </div>
                `;
            }
        }

        row.innerHTML = `
            ${me ? '' : `<div class="msn-msg-avatar">${avatarHtml(m.avatar_url, (m.sender_name || 'U').slice(0,2).toUpperCase(), 'msn-msg-avatar-img')}</div>`}
            <div class="msn-bubble">
                ${!me ? `<div class="msn-msg-name">${esc(m.sender_name || 'Unknown')}</div>` : ''}
                ${m.body ? `<div class="msn-text">${esc(m.body)}</div>` : ''}
                ${fileHtml}
                <div class="msn-time">${esc(m.created_at || '')}</div>
            </div>
        `;

        chatMessages.appendChild(row);
    }

    window.crmChatStartDirect = async function (userId) {
        activeUserId = Number(userId);
        activeDepartmentId = null;

        const data = await postJson('/chat/direct/json', { user_id: Number(userId) });
        await crmChatReloadAll();
        await openConversation(data.conversation_id);

        switchTab('users');
        renderLists();
    };

    window.crmChatStartDepartment = async function (departmentId) {
        activeDepartmentId = Number(departmentId);
        activeUserId = null;

        const data = await postJson('/chat/department/json', { department_id: Number(departmentId) });
        await crmChatReloadAll();
        await openConversation(data.conversation_id);

        switchTab('departments');
        renderLists();
    };

    composer.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (!activeConversationId) {
            alert('Chọn đoạn chat trước.');
            return;
        }

        const body = chatInput.value.trim();

        if (!body && selectedFiles.length === 0) {
            return;
        }

        const form = new FormData();
        form.append('body', body);

        selectedFiles.forEach(file => form.append('attachments[]', file));

        const res = await fetch(`/chat/${activeConversationId}/send/json`, {
            method: 'POST',
            headers: headers(false),
            body: form,
        });

        if (!res.ok) {
            alert('Gửi tin nhắn thất bại.');
            return;
        }

        chatInput.value = '';
        chatInput.style.height = 'auto';
        selectedFiles = [];
        chatFiles.value = '';
        renderFilePreview();

        const data = await res.json();

        (data.messages || []).forEach(m => {
            appendMessage(m);
            lastMessageId = Math.max(lastMessageId, Number(m.id || 0));
        });

        crmChatScrollBottom();
        await crmChatReloadAll();
    });

    chatFiles.addEventListener('change', function () {
        selectedFiles = Array.from(chatFiles.files || []);
        renderFilePreview();
    });

    function renderFilePreview() {
        if (!selectedFiles.length) {
            filePreview.classList.remove('show');
            filePreview.innerHTML = '';
            return;
        }

        filePreview.classList.add('show');
        filePreview.innerHTML = selectedFiles.map(file => `
            <span class="msn-file-chip">📎 ${esc(file.name)}</span>
        `).join('');
    }

    chatInput.addEventListener('input', function () {
        chatInput.style.height = 'auto';
        chatInput.style.height = Math.min(chatInput.scrollHeight, 130) + 'px';
    });

    chatInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            composer.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    chatMessages.addEventListener('scroll', function () {
        const nearBottom = chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 180;
        scrollDownBtn.classList.toggle('show', !nearBottom);
    });

    window.crmChatScrollBottom = function () {
        chatMessages.scrollTop = chatMessages.scrollHeight;
        scrollDownBtn.classList.remove('show');
    };

    quickTaskForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (!activeConversationId) {
            alert('Chọn đoạn chat trước.');
            return;
        }

        const form = new FormData(quickTaskForm);

        const res = await fetch(`/chat/${activeConversationId}/tasks/quick`, {
            method: 'POST',
            headers: headers(false),
            body: form,
        });

        if (!res.ok) {
            alert('Tạo công việc thất bại.');
            return;
        }

        quickTaskForm.reset();
        await loadMessages(false);
        alert('Đã tạo công việc nhanh.');
    });

    function switchTab(tab) {
        document.querySelectorAll('.msn-tabs button').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });

        document.querySelectorAll('.msn-list').forEach(list => list.classList.remove('active'));

        if (tab === 'conversations') conversationList.classList.add('active');
        if (tab === 'users') userList.classList.add('active');
        if (tab === 'departments') departmentList.classList.add('active');
    }

    document.querySelectorAll('.msn-tabs button').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });

    searchInput.addEventListener('input', renderLists);

    window.crmChatToggleTask = function () {
        const panel = document.getElementById('quickTaskPanel');
        panel.classList.toggle('open');

        const right = document.getElementById('rightPanel');
        if (window.innerWidth <= 1280) {
            right.classList.add('open');
        }
    };

    window.crmChatToggleRightPanel = function () {
        document.getElementById('rightPanel').classList.toggle('open');
    };

    window.crmToggleInfo = function (id) {
        document.getElementById(id)?.classList.toggle('open');
    };

    window.crmChatOpenCreateGroup = function () {
        document.getElementById('groupModal').classList.add('open');
    };

    window.crmChatCloseCreateGroup = function () {
        document.getElementById('groupModal').classList.remove('open');
    };

    document.getElementById('groupForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const form = new FormData(e.target);
        const name = form.get('name');
        const user_ids = form.getAll('user_ids[]').map(Number);

        if (!user_ids.length) {
            alert('Chọn ít nhất 1 thành viên.');
            return;
        }

        const data = await postJson('/chat/group/json', { name, user_ids });

        crmChatCloseCreateGroup();
        e.target.reset();
        await crmChatReloadAll();
        await openConversation(data.conversation_id);
        switchTab('conversations');
    });

    crmChatReloadAll().catch(() => {
        conversationList.innerHTML = '<div class="msn-item"><div class="msn-item-main"><div class="msn-item-name">Không tải được chat</div><div class="msn-item-sub">Kiểm tra route hoặc đăng nhập lại.</div></div></div>';
    });
})();
</script>
@endsection