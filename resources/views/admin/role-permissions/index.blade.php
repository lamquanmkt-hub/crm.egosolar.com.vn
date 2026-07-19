@extends('layouts.app')

@section('title', 'Vai trò & Phân quyền')

@section('content')
@php
    $activeTab = request('tab', 'permissions');
    $selectedRoleId = optional($selectedRole)->id;
@endphp

<style>
    .rp-shell {
        --rp-navy: #102f50;
        --rp-navy-2: #173d63;
        --rp-teal: #10a38f;
        --rp-cyan: #079bc8;
        --rp-bg: #f4f7fb;
        --rp-border: #dce5ee;
        --rp-soft: #e9eff5;
        --rp-muted: #70839a;

        min-height: calc(100vh - 70px);
        padding: 16px 18px 32px !important;
        color: #17324d;
        background: var(--rp-bg);
    }

    .rp-shell * {
        box-sizing: border-box;
    }

    /* ================= HEADER ================= */

    .rp-hero {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 112px;
        padding: 20px 22px 20px 25px;
        overflow: hidden;
        border: 1px solid var(--rp-border);
        border-radius: 15px;
        background: #ffffff;
        box-shadow: 0 8px 28px rgba(20, 48, 78, 0.06);
    }

    .rp-hero::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        background: linear-gradient(
            180deg,
            var(--rp-cyan),
            var(--rp-teal)
        );
    }

    .rp-hero::after {
        display: none;
    }

    .rp-eyebrow {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 7px;
        color: #71849a;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0;
        text-transform: none;
    }

    .rp-eyebrow::before {
        content: "\F3E8";
        color: var(--rp-cyan);
        font-family: "bootstrap-icons";
    }

    .rp-title {
        margin: 0;
        color: #102b49;
        font-size: 27px;
        font-weight: 900;
        line-height: 1.15;
        letter-spacing: -0.025em;
    }

    .rp-subtitle {
        margin: 6px 0 0;
        color: var(--rp-muted);
        font-size: 13px;
    }

    /* ================= BUTTON ================= */

    .rp-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 39px;
        padding: 9px 14px;
        border: 1px solid transparent;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 800;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        transition: 0.18s ease;
    }

    .rp-btn:hover {
        transform: translateY(-1px);
    }

    .rp-btn-primary {
        color: #ffffff;
        background: var(--rp-teal);
        box-shadow: 0 7px 18px rgba(16, 163, 143, 0.18);
    }

    .rp-btn-primary:hover {
        color: #ffffff;
        background: #0c927f;
    }

    .rp-btn-soft {
        color: #146b8a;
        background: #f3fbfe;
        border-color: #cdeaf4;
    }

    .rp-btn-soft:hover {
        color: #0a789f;
        background: #eaf8fd;
    }

    .rp-btn-danger {
        color: #bd2c41;
        background: #fff4f5;
        border-color: #ffd4da;
    }

    .rp-btn-dark {
        color: #ffffff;
        background: var(--rp-navy);
    }

    .rp-btn-dark:hover {
        color: #ffffff;
        background: #0c2845;
    }

    /* ================= THỐNG KÊ ================= */

    .rp-stats {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 11px;
        margin: 13px 0;
    }

    .rp-stat {
        min-height: 74px;
        padding: 13px 15px;
        border: 1px solid var(--rp-border);
        border-radius: 13px;
        background: #ffffff;
        box-shadow: 0 5px 18px rgba(20, 48, 78, 0.035);
    }

    .rp-stat b {
        display: block;
        color: var(--rp-navy);
        font-size: 21px;
        font-weight: 900;
        line-height: 1;
    }

    .rp-stat span {
        display: block;
        margin-top: 6px;
        color: var(--rp-muted);
        font-size: 11px;
        font-weight: 750;
        line-height: 1.25;
    }

    /* ================= TAB ================= */

    .rp-tabs {
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 6px;
        margin-bottom: 13px;
        overflow-x: auto;
        border: 1px solid var(--rp-border);
        border-radius: 12px;
        background: #ffffff;
    }

    .rp-tab {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 13px;
        border-radius: 9px;
        color: #62768b;
        font-size: 12.5px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
    }

    .rp-tab:hover {
        color: #087da4;
        background: #f2f9fc;
    }

    .rp-tab.active {
        color: #087da4;
        background: #eaf8fd;
        box-shadow: inset 0 0 0 1px #c9eaf4;
    }

    /* ================= LAYOUT ================= */

    .rp-layout {
        display: grid;
        grid-template-columns: 305px minmax(0, 1fr);
        gap: 13px;
        align-items: start;
    }

    .rp-card {
        overflow: hidden;
        border: 1px solid var(--rp-border);
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 7px 24px rgba(20, 48, 78, 0.045);
    }

    .rp-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 58px;
        padding: 13px 16px;
        border-bottom: 1px solid var(--rp-soft);
    }

    .rp-card-title {
        color: var(--rp-navy);
        font-size: 14.5px;
        font-weight: 900;
    }

    .rp-body {
        padding: 15px;
    }

    /* ================= DANH SÁCH ROLE ================= */

    .rp-layout > aside {
        position: sticky;
        top: 86px;
    }

    .rp-role-list {
        min-height: 430px;
        max-height: calc(100vh - 270px);
        padding: 7px 8px 10px;
        overflow-y: auto;
        scrollbar-width: thin;
    }

    .rp-role-link {
        display: block;
        margin: 4px 0;
        padding: 11px;
        border: 1px solid transparent;
        border-radius: 11px;
        color: #405973;
        text-decoration: none;
        transition: 0.16s ease;
    }

    .rp-role-link:hover {
        color: #0b6f93;
        background: #f5fafc;
    }

    .rp-role-link.active {
        color: #08789f;
        border-color: #bfe4ef;
        background: #eaf8fd;
        box-shadow: inset 3px 0 0 var(--rp-cyan);
    }

    .rp-role-name {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        font-size: 13px;
        font-weight: 900;
    }

    .rp-role-code {
        margin-top: 4px;
        color: #8495a6;
        font-size: 10.5px;
    }

    /* ================= BADGE ================= */

    .rp-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 7px;
        border-radius: 999px;
        color: #63778c;
        background: #eef3f7;
        font-size: 9.5px;
        font-weight: 850;
        line-height: 1;
    }

    .rp-pill-on {
        color: #087657;
        background: #ddf6ec;
    }

    .rp-pill-off {
        color: #6e7d8d;
        background: #edf1f5;
    }

    .rp-pill-system {
        color: #9c5f0b;
        background: #fff2d9;
    }

    /* ================= FORM ================= */

    .rp-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .rp-field label {
        display: block;
        margin-bottom: 6px;
        color: #516980;
        font-size: 11px;
        font-weight: 850;
    }

    .rp-input,
    .rp-select,
    .rp-textarea {
        width: 100%;
        border: 1px solid #d6e1ea;
        border-radius: 10px;
        background: #ffffff;
        color: #1d3853;
        font-size: 13px;
        outline: none;
        transition: 0.16s ease;
    }

    .rp-input,
    .rp-select {
        height: 40px;
        padding: 8px 11px;
    }

    .rp-textarea {
        min-height: 76px;
        padding: 10px 11px;
        resize: vertical;
    }

    .rp-input:focus,
    .rp-select:focus,
    .rp-textarea:focus {
        border-color: #61bdd4;
        box-shadow: 0 0 0 3px rgba(7, 155, 200, 0.09);
    }

    .rp-input[readonly] {
        color: #70839a;
        background: #f6f8fa;
    }

    .rp-note {
        padding: 10px 11px;
        border: 1px solid #cfe7f1;
        border-radius: 9px;
        color: #537085;
        background: #f2f9fc;
        font-size: 11px;
        line-height: 1.45;
    }

    /* ================= SWITCH ================= */

    .rp-switch-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 12px 13px;
        border: 1px solid #bfe5ee;
        border-radius: 11px;
        background: #f0fafc;
    }

    .rp-switch-copy strong {
        display: block;
        color: #0b6f91;
        font-size: 12.5px;
    }

    .rp-switch-copy span {
        display: block;
        margin-top: 3px;
        color: #6c8194;
        font-size: 10.5px;
    }

    .rp-switch {
        position: relative;
        width: 45px;
        height: 25px;
        flex: 0 0 45px;
    }

    .rp-switch input {
        width: 0;
        height: 0;
        opacity: 0;
    }

    .rp-slider {
        position: absolute;
        inset: 0;
        border-radius: 999px;
        background: #c7d2dc;
        cursor: pointer;
        transition: 0.2s;
    }

    .rp-slider::before {
        content: "";
        position: absolute;
        top: 3px;
        left: 3px;
        width: 19px;
        height: 19px;
        border-radius: 50%;
        background: #ffffff;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.18);
        transition: 0.2s;
    }

    .rp-switch input:checked + .rp-slider {
        background: var(--rp-teal);
    }

    .rp-switch input:checked + .rp-slider::before {
        transform: translateX(20px);
    }

    /* ================= TÌM KIẾM ================= */

    .rp-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin: 12px 0 10px;
    }

    .rp-search {
        position: relative;
        flex: 1;
        min-width: 230px;
    }

    .rp-search i {
        position: absolute;
        top: 50%;
        left: 12px;
        color: #8a9bad;
        font-size: 13px;
        transform: translateY(-50%);
    }

    .rp-search input {
        padding-left: 34px;
    }

    /* ================= MA TRẬN QUYỀN ================= */

    .rp-permission-group {
        margin-top: 9px;
        overflow: hidden;
        border: 1px solid #dde6ee;
        border-radius: 11px;
        background: #ffffff;
    }

    .rp-group-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 46px;
        padding: 10px 12px;
        border-bottom: 1px solid #e7edf2;
        background: #f8fafc;
    }

    .rp-group-name {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #23425f;
        font-size: 12.5px;
        font-weight: 900;
    }

    .rp-group-name > i:first-child {
        color: #078db8;
    }

    .rp-group-actions button {
        padding: 5px 7px;
        border: 0;
        border-radius: 7px;
        color: #087da4;
        background: transparent;
        font-size: 10px;
        font-weight: 850;
    }

    .rp-group-actions button:hover {
        background: #eaf7fb;
    }

    .rp-permission-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .rp-permission-item {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        min-height: 68px;
        padding: 11px 12px;
        border-right: 1px solid #edf1f5;
        border-bottom: 1px solid #edf1f5;
        cursor: pointer;
        transition: 0.14s ease;
    }

    .rp-permission-item:hover {
        background: #f7fbfd;
    }

    .rp-permission-item input {
        width: 16px;
        height: 16px;
        margin: 2px 0 0;
        flex: 0 0 16px;
        accent-color: var(--rp-teal);
    }

    .rp-permission-item strong {
        display: block;
        color: #294760;
        font-size: 11.5px;
        line-height: 1.35;
    }

    .rp-permission-item small {
        display: block;
        margin-top: 3px;
        color: #8495a6;
        font-size: 9.5px;
        line-height: 1.35;
        word-break: break-word;
    }

    .rp-permission-item.is-page {
        background: #f4fbfe;
    }

    .rp-permission-item.is-page strong {
        color: #08799f;
    }

    /* Thanh lưu cố định phía dưới */

    .rp-actions {
        position: sticky;
        bottom: 10px;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 12px;
        padding: 10px 11px;
        border: 1px solid #cfdde7;
        border-radius: 11px;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 10px 30px rgba(20, 48, 78, 0.13);
        backdrop-filter: blur(8px);
    }

    /* ================= NHÂN VIÊN ================= */

    .rp-user {
        margin-bottom: 8px;
        overflow: hidden;
        border: 1px solid #dee7ef;
        border-radius: 11px;
        background: #ffffff;
    }

    .rp-user summary {
        display: grid;
        grid-template-columns:
            minmax(210px, 1.35fr)
            minmax(180px, 1fr)
            auto;
        gap: 12px;
        align-items: center;
        padding: 12px 13px;
        list-style: none;
        cursor: pointer;
    }

    .rp-user summary::-webkit-details-marker {
        display: none;
    }

    .rp-user[open] summary {
        border-bottom: 1px solid #e4ebf1;
        background: #f7fafc;
    }

    .rp-user-name {
        color: #173b59;
        font-size: 12.5px;
        font-weight: 900;
    }

    .rp-user-email {
        margin-top: 2px;
        color: #8293a4;
        font-size: 10.5px;
    }

    .rp-user-roles {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }

    .rp-user-content {
        padding: 14px;
    }

    .rp-check-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 7px;
    }

    .rp-check {
        display: flex;
        align-items: center;
        gap: 7px;
        min-height: 38px;
        padding: 8px 9px;
        border: 1px solid #dfe7ee;
        border-radius: 9px;
        color: #425b72;
        font-size: 11px;
        font-weight: 750;
        cursor: pointer;
    }

    .rp-check:hover {
        color: #08789f;
        background: #f4fafc;
    }

    .rp-check input {
        accent-color: var(--rp-teal);
    }

    /* ================= NHẬT KÝ ================= */

    .rp-audit-table {
        width: 100%;
        border-collapse: collapse;
    }

    .rp-audit-table th,
    .rp-audit-table td {
        padding: 11px 12px;
        border-bottom: 1px solid #e8eef3;
        text-align: left;
        vertical-align: top;
        font-size: 11px;
    }

    .rp-audit-table th {
        color: #61778b;
        background: #f8fafc;
        font-weight: 900;
        white-space: nowrap;
    }

    .rp-empty {
        padding: 42px 20px;
        color: #7b8e9f;
        text-align: center;
    }

    /* ================= ALERT ================= */

    .rp-alert {
        padding: 12px 14px;
        margin-bottom: 12px;
        border-radius: 11px;
        font-size: 13px;
        font-weight: 700;
    }

    .rp-alert-success {
        color: #11755c;
        background: #eaf9f4;
        border: 1px solid #c7ebdf;
    }

    .rp-alert-danger {
        color: #a83243;
        background: #fff1f3;
        border: 1px solid #ffd1d8;
    }

    /* ================= RESPONSIVE ================= */

    @media (max-width: 1350px) {
        .rp-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .rp-permission-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 1024px) {
        .rp-layout {
            grid-template-columns: 1fr;
        }

        .rp-layout > aside {
            position: static;
        }

        .rp-role-list {
            min-height: auto;
            max-height: 310px;
        }

        .rp-permission-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .rp-check-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .rp-shell {
            padding: 10px 10px 24px !important;
        }

        .rp-hero {
            padding: 17px 15px 17px 19px;
        }

        .rp-title {
            font-size: 23px;
        }

        .rp-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .rp-grid-2 {
            grid-template-columns: 1fr;
        }

        .rp-permission-grid {
            grid-template-columns: 1fr;
        }

        .rp-user summary {
            grid-template-columns: 1fr;
        }

        .rp-check-grid {
            grid-template-columns: 1fr;
        }

        .rp-actions {
            align-items: stretch;
            flex-direction: column;
        }

        .rp-actions .rp-btn {
            width: 100%;
        }
    }
</style>

<div class="container-fluid py-3 rp-shell">
    @if(session('success'))
        <div class="rp-alert rp-alert-success"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rp-alert rp-alert-danger">
            <strong>Chưa thể lưu:</strong> {{ $errors->first() }}
        </div>
    @endif

    <section class="rp-hero">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap position-relative" style="z-index:1">
            <div>
                <div class="rp-eyebrow">Cài đặt hệ thống</div>
                <h1 class="rp-title">Vai trò &amp; Phân quyền</h1>
                <p class="rp-subtitle">Quản lý trang được truy cập, quyền thao tác và vai trò của từng nhân viên.</p>
            </div>
            <button type="button" class="rp-btn rp-btn-primary" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                <i class="bi bi-plus-circle"></i> Tạo vai trò mới
            </button>
        </div>
    </section>

    <div class="rp-stats">
        <div class="rp-stat"><b>{{ $stats['roles'] }}</b><span>Vai trò hệ thống</span></div>
        <div class="rp-stat"><b>{{ $stats['permissions'] }}</b><span>Quyền đang quản lý</span></div>
        <div class="rp-stat"><b>{{ $stats['managed_roles'] }}</b><span>Role đã bật kiểm soát trang</span></div>
        <div class="rp-stat"><b>{{ $stats['users'] }}</b><span>Tài khoản nhân viên</span></div>
        <div class="rp-stat"><b>{{ $stats['unassigned_users'] }}</b><span>Chưa được gán role</span></div>
    </div>

    <nav class="rp-tabs">
        <a class="rp-tab {{ $activeTab === 'permissions' ? 'active' : '' }}" href="{{ route('admin.role-permissions.index', ['role' => $selectedRoleId, 'tab' => 'permissions']) }}"><i class="bi bi-shield-check me-1"></i> Ma trận quyền</a>
        <a class="rp-tab {{ $activeTab === 'users' ? 'active' : '' }}" href="{{ route('admin.role-permissions.index', ['role' => $selectedRoleId, 'tab' => 'users']) }}"><i class="bi bi-people me-1"></i> Gán role nhân viên</a>
        <a class="rp-tab {{ $activeTab === 'audit' ? 'active' : '' }}" href="{{ route('admin.role-permissions.index', ['role' => $selectedRoleId, 'tab' => 'audit']) }}"><i class="bi bi-clock-history me-1"></i> Nhật ký thay đổi</a>
    </nav>

    @if($activeTab === 'permissions')
        <div class="rp-layout">
            <aside class="rp-card">
                <div class="rp-card-head">
                    <div class="rp-card-title">Danh sách vai trò</div>
                    <span class="rp-pill">{{ $roles->count() }}</span>
                </div>
                <div class="rp-role-list">
                    @foreach($roles as $role)
                        <a class="rp-role-link {{ optional($selectedRole)->id === $role->id ? 'active' : '' }}" href="{{ route('admin.role-permissions.index', ['role' => $role->id, 'tab' => 'permissions']) }}">
                            <div class="rp-role-name">
                                <span>{{ $role->ui_name }}</span>
                                <span class="rp-pill">{{ $role->users_count }} nhân sự</span>
                            </div>
                            <div class="rp-role-code">{{ $role->name }} · {{ $role->permissions->count() }} quyền</div>
                            <div class="mt-2 d-flex gap-1 flex-wrap">
                                @if($role->is_system ?? false)<span class="rp-pill rp-pill-system">Hệ thống</span>@endif
                                <span class="rp-pill {{ ($role->page_access_enabled ?? false) ? 'rp-pill-on' : 'rp-pill-off' }}">{{ ($role->page_access_enabled ?? false) ? 'Đang kiểm soát trang' : 'Chế độ tương thích' }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </aside>

            <main>
                @if($selectedRole)
                    <section class="rp-card mb-3">
                        <div class="rp-card-head">
                            <div>
                                <div class="rp-card-title">Thông tin vai trò</div>
                                <div class="text-muted" style="font-size:11px">Role ID #{{ $selectedRole->id }}</div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <form method="POST" action="{{ route('admin.role-permissions.roles.clone', $selectedRole) }}">
                                    @csrf
                                    <button class="rp-btn rp-btn-soft" type="submit"><i class="bi bi-copy"></i> Sao chép</button>
                                </form>
                                @if(!($selectedRole->is_system ?? false) && ($selectedRole->users_count ?? 0) === 0)
                                    <form method="POST" action="{{ route('admin.role-permissions.roles.destroy', $selectedRole) }}" onsubmit="return confirm('Xóa vai trò này?')">
                                        @csrf @method('DELETE')
                                        <button class="rp-btn rp-btn-danger" type="submit"><i class="bi bi-trash3"></i> Xóa</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        <div class="rp-body">
                            <form method="POST" action="{{ route('admin.role-permissions.roles.update', $selectedRole) }}">
                                @csrf @method('PUT')
                                <div class="rp-grid-2">
                                    <div class="rp-field">
                                        <label>Tên hiển thị</label>
                                        <input class="rp-input" name="display_name" value="{{ old('display_name', $selectedRole->display_name ?: $selectedRole->ui_name) }}" required>
                                    </div>
                                    <div class="rp-field">
                                        <label>Mã role</label>
                                        <input class="rp-input" name="name" value="{{ old('name', $selectedRole->name) }}" {{ ($selectedRole->is_system ?? false) ? 'readonly' : '' }} required>
                                    </div>
                                </div>
                                <div class="rp-field mt-3">
                                    <label>Mô tả</label>
                                    <textarea class="rp-textarea" name="description" placeholder="Mô tả trách nhiệm và phạm vi sử dụng role...">{{ old('description', $selectedRole->description) }}</textarea>
                                </div>
                                @if($selectedRole->is_system ?? false)
                                    <div class="rp-note mt-3"><i class="bi bi-lock me-1"></i>Mã role hệ thống được khóa vì route hiện tại đang tham chiếu trực tiếp tên <strong>{{ $selectedRole->name }}</strong>.</div>
                                @endif
                                <div class="text-end mt-3"><button class="rp-btn rp-btn-dark" type="submit"><i class="bi bi-save"></i> Lưu thông tin</button></div>
                            </form>
                        </div>
                    </section>

                    <section class="rp-card">
                        <div class="rp-card-head">
                            <div>
                                <div class="rp-card-title">Ma trận quyền: {{ $selectedRole->ui_name }}</div>
                                <div class="text-muted" style="font-size:11px">Chọn quyền truy cập trang trước, sau đó chọn các quyền thao tác chi tiết.</div>
                            </div>
                            <span class="rp-pill"><span id="selectedPermissionCount">{{ count($selectedPermissionNames) }}</span> quyền đã chọn</span>
                        </div>
                        <div class="rp-body">
                            <form method="POST" action="{{ route('admin.role-permissions.roles.permissions', $selectedRole) }}" id="permissionMatrixForm">
                                @csrf @method('PUT')

                                <div class="rp-switch-row">
                                    <div class="rp-switch-copy">
                                        <strong>Bật kiểm soát trang cho role này</strong>
                                        <span>Tắt: giữ nguyên phân quyền cũ. Bật: chỉ những trang được tích bên dưới mới truy cập được.</span>
                                    </div>
                                    <label class="rp-switch">
                                        <input type="checkbox" name="page_access_enabled" value="1" @checked($selectedRole->page_access_enabled ?? false)>
                                        <span class="rp-slider"></span>
                                    </label>
                                </div>

                                <div class="rp-toolbar">
                                    <div class="rp-search"><i class="bi bi-search"></i><input id="permissionSearch" class="rp-input" placeholder="Tìm module hoặc mã quyền..."></div>
                                    <button class="rp-btn rp-btn-soft" type="button" id="checkAllPermissions">Chọn tất cả</button>
                                    <button class="rp-btn rp-btn-soft" type="button" id="uncheckAllPermissions">Bỏ chọn</button>
                                </div>

                                @foreach($permissionGroups as $group)
                                    <div class="rp-permission-group" data-permission-group>
                                        <div class="rp-group-head">
                                            <div class="rp-group-name"><i class="bi {{ $group['icon'] }}"></i>{{ $group['label'] }} <span class="rp-pill">{{ count($group['permissions']) }}</span></div>
                                            <div class="rp-group-actions">
                                                <button type="button" data-group-check>Chọn nhóm</button>
                                                <button type="button" data-group-uncheck>Bỏ nhóm</button>
                                            </div>
                                        </div>
                                        <div class="rp-permission-grid">
                                            @foreach($group['permissions'] as $item)
                                                @php($permission = $item['permission'])
                                                <label class="rp-permission-item {{ $item['is_page'] ? 'is-page' : '' }}" data-search="{{ \Illuminate\Support\Str::lower($item['label'].' '.$item['description'].' '.$permission->name) }}">
                                                    <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, $selectedPermissionNames, true))>
                                                    <span>
                                                        <strong>@if($item['is_page'])<i class="bi {{ $item['icon'] }} me-1"></i>@endif{{ $item['label'] }}</strong>
                                                        <small>{{ $item['description'] }}</small>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach

                                <div class="rp-actions">
                                    <div class="rp-note"><i class="bi bi-info-circle me-1"></i>Khi bật kiểm soát trang, quyền <strong>Dashboard</strong> được hệ thống giữ lại để nhân viên đăng nhập không bị kẹt.</div>
                                    <button class="rp-btn rp-btn-primary" type="submit"><i class="bi bi-shield-check"></i> Lưu ma trận phân quyền</button>
                                </div>
                            </form>
                        </div>
                    </section>
                @else
                    <div class="rp-card rp-empty">Chưa có vai trò nào trong hệ thống.</div>
                @endif
            </main>
        </div>
    @elseif($activeTab === 'users')
        <section class="rp-card">
            <div class="rp-card-head">
                <div>
                    <div class="rp-card-title">Gán vai trò cho nhân viên</div>
                    <div class="text-muted" style="font-size:11px">Mỗi tài khoản có thể có nhiều role. Quyền riêng được cộng thêm ngoài quyền từ role.</div>
                </div>
                <div class="rp-search" style="max-width:330px"><i class="bi bi-search"></i><input class="rp-input" id="userSearch" placeholder="Tìm tên hoặc email..."></div>
            </div>
            <div class="rp-body" id="userList">
                @foreach($users as $user)
                    <details class="rp-user" data-user-search="{{ \Illuminate\Support\Str::lower($user->name.' '.$user->email) }}">
                        <summary>
                            <div><div class="rp-user-name">{{ $user->name }}</div><div class="rp-user-email">{{ $user->email }}</div></div>
                            <div class="rp-user-roles">
                                @forelse($user->roles as $role)
                                    <span class="rp-pill rp-pill-on">{{ $role->display_name ?: $role->name }}</span>
                                @empty
                                    <span class="rp-pill rp-pill-off">Chưa có role</span>
                                @endforelse
                            </div>
                            <span class="rp-pill">{{ $user->getAllPermissions()->count() }} quyền hiệu lực <i class="bi bi-chevron-down"></i></span>
                        </summary>
                        <div class="rp-user-content">
                            <form method="POST" action="{{ route('admin.role-permissions.users.roles', $user) }}">
                                @csrf @method('PUT')
                                <div class="rp-card-title mb-2">Vai trò được gán</div>
                                <div class="rp-check-grid">
                                    @foreach($roles as $role)
                                        <label class="rp-check"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains('id', $role->id))><span>{{ $role->ui_name }}</span></label>
                                    @endforeach
                                </div>
                                <div class="text-end mt-3"><button class="rp-btn rp-btn-primary" type="submit"><i class="bi bi-save"></i> Lưu role</button></div>
                            </form>

                            <hr class="my-4">

                            <form method="POST" action="{{ route('admin.role-permissions.users.permissions', $user) }}">
                                @csrf @method('PUT')
                                <div class="rp-card-title mb-1">Quyền riêng của nhân viên</div>
                                <div class="text-muted mb-2" style="font-size:11px">Giữ Ctrl/Command để chọn nhiều quyền. Chỉ dùng khi cần ngoại lệ so với role chung.</div>
                                <select class="rp-select" name="permissions[]" multiple size="9">
                                    @foreach($permissions as $permission)
                                        <option value="{{ $permission->name }}" @selected($user->permissions->contains('name', $permission->name))>{{ $permission->name }}</option>
                                    @endforeach
                                </select>
                                <div class="text-end mt-3"><button class="rp-btn rp-btn-soft" type="submit"><i class="bi bi-person-check"></i> Lưu quyền riêng</button></div>
                            </form>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    @else
        <section class="rp-card">
            <div class="rp-card-head"><div><div class="rp-card-title">Nhật ký thay đổi phân quyền</div><div class="text-muted" style="font-size:11px">Lưu người thao tác, đối tượng và dữ liệu trước/sau.</div></div></div>
            <div style="overflow:auto">
                <table class="rp-audit-table">
                    <thead><tr><th>Thời gian</th><th>Người thao tác</th><th>Hành động</th><th>Đối tượng</th><th>IP</th></tr></thead>
                    <tbody>
                    @forelse($audits as $audit)
                        <tr>
                            <td>{{ optional($audit->created_at)->format('d/m/Y H:i:s') }}</td>
                            <td>{{ optional($audit->actor)->name ?: 'Hệ thống' }}</td>
                            <td><span class="rp-pill">{{ $audit->action }}</span></td>
                            <td><strong>{{ $audit->subject_name ?: $audit->subject_type }}</strong><div class="text-muted">#{{ $audit->subject_id }}</div></td>
                            <td>{{ $audit->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="rp-empty">Chưa có lịch sử thay đổi.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>

<div class="modal fade" id="createRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('admin.role-permissions.roles.store') }}" style="border:0;border-radius:18px;overflow:hidden">
            @csrf
            <div class="modal-header" style="background:#f3fbfe;border-bottom-color:#dcecf3"><div><h5 class="modal-title fw-bold">Tạo vai trò mới</h5><div class="text-muted" style="font-size:12px">Role mới mặc định bật kiểm soát trang và có quyền Dashboard.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="rp-field"><label>Tên vai trò</label><input class="rp-input" name="display_name" placeholder="Ví dụ: Trưởng phòng kinh doanh" required></div>
                <div class="rp-field mt-3"><label>Mã role (không bắt buộc)</label><input class="rp-input" name="name" placeholder="sales_director"></div>
                <div class="rp-field mt-3"><label>Mô tả</label><textarea class="rp-textarea" name="description" placeholder="Phạm vi trách nhiệm..."></textarea></div>
                <div class="rp-field mt-3"><label>Sao chép quyền từ role</label><select class="rp-select" name="clone_from"><option value="">Không sao chép</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->ui_name }} ({{ $role->name }})</option>@endforeach</select></div>
            </div>
            <div class="modal-footer" style="border-top-color:#e5edf2"><button type="button" class="rp-btn rp-btn-soft" data-bs-dismiss="modal">Hủy</button><button class="rp-btn rp-btn-primary" type="submit"><i class="bi bi-plus-circle"></i> Tạo vai trò</button></div>
        </form>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('permissionMatrixForm');
    if (form) {
        const boxes = [...form.querySelectorAll('input[name="permissions[]"]')];
        const count = document.getElementById('selectedPermissionCount');
        const refresh = () => { if (count) count.textContent = boxes.filter(box => box.checked).length; };
        boxes.forEach(box => box.addEventListener('change', refresh));

        document.getElementById('checkAllPermissions')?.addEventListener('click', () => { boxes.forEach(box => box.checked = true); refresh(); });
        document.getElementById('uncheckAllPermissions')?.addEventListener('click', () => { boxes.forEach(box => box.checked = false); refresh(); });

        form.querySelectorAll('[data-permission-group]').forEach(group => {
            const scoped = [...group.querySelectorAll('input[name="permissions[]"]')];
            group.querySelector('[data-group-check]')?.addEventListener('click', () => { scoped.forEach(box => box.checked = true); refresh(); });
            group.querySelector('[data-group-uncheck]')?.addEventListener('click', () => { scoped.forEach(box => box.checked = false); refresh(); });
        });

        document.getElementById('permissionSearch')?.addEventListener('input', (event) => {
            const term = event.target.value.trim().toLowerCase();
            form.querySelectorAll('.rp-permission-item').forEach(item => {
                item.style.display = !term || (item.dataset.search || '').includes(term) ? '' : 'none';
            });
            form.querySelectorAll('[data-permission-group]').forEach(group => {
                const visible = [...group.querySelectorAll('.rp-permission-item')].some(item => item.style.display !== 'none');
                group.style.display = visible ? '' : 'none';
            });
        });
    }

    document.getElementById('userSearch')?.addEventListener('input', (event) => {
        const term = event.target.value.trim().toLowerCase();
        document.querySelectorAll('[data-user-search]').forEach(item => {
            item.style.display = !term || (item.dataset.userSearch || '').includes(term) ? '' : 'none';
        });
    });
})();
</script>
@endsection
