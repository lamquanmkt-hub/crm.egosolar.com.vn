@extends('layouts.app')

@section('content')
<style>
    .org-page{
        --bg:#f6f8fc;
        --card:#ffffff;
        --text:#0f172a;
        --muted:#64748b;
        --line:#dbe4f0;
        --soft:#eef4ff;
        --primary:#2563eb;
        --success:#10b981;
        --shadow:0 20px 50px rgba(15, 23, 42, .08);
    }

    .org-page .page-shell{
        background: linear-gradient(180deg, #f8fbff 0%, #f6f8fc 100%);
        border-radius: 28px;
        padding: 24px;
    }

    .org-page .hero{
        background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 60%, #38bdf8 100%);
        color: #fff;
        border-radius: 28px;
        padding: 28px;
        box-shadow: 0 20px 50px rgba(29, 78, 216, .18);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }

    .org-page .hero-title{
        font-size: 38px;
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: 8px;
    }

    .org-page .hero-sub{
        color: rgba(255,255,255,.88);
        max-width: 760px;
    }

    .org-page .surface-card{
        background: var(--card);
        border: 1px solid #eef2f7;
        border-radius: 24px;
        box-shadow: var(--shadow);
    }

    .org-page .section-title{
        font-size: 24px;
        font-weight: 800;
        color: var(--text);
        margin: 0 0 4px;
    }

    .org-page .section-sub{
        color: var(--muted);
        font-size:14px;
    }

    .org-page .board-grid{
        display:grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 18px;
    }

    .org-page .person-card{
        background:#fff;
        border:1px solid #eef2f7;
        border-radius:22px;
        padding:20px;
        box-shadow:0 12px 30px rgba(15,23,42,.05);
        text-align:center;
    }

    .org-page .avatar{
        width:64px;
        height:64px;
        border-radius:50%;
        object-fit:cover;
        display:block;
        margin:0 auto 12px;
        border:3px solid #e2e8f0;
    }

    .org-page .avatar-fallback{
        width:64px;
        height:64px;
        border-radius:50%;
        display:flex;
        align-items:center;
        justify-content:center;
        margin:0 auto 12px;
        background:linear-gradient(135deg,#dbeafe,#bfdbfe);
        color:#0f172a;
        font-weight:800;
        border:3px solid #e2e8f0;
    }

    .org-page .person-name{
        font-size:18px;
        font-weight:800;
        color:#0f172a;
        margin-bottom:2px;
    }

    .org-page .person-role{
        color:#64748b;
        font-size:14px;
    }

    .org-page .tree{
        margin-top:28px;
        overflow:auto;
        padding-bottom:20px;
    }

    .org-page .tree-root{
        min-width:1200px;
    }

    .org-page .connector-v{
        width:2px;
        height:36px;
        background:var(--line);
        margin:0 auto;
    }

    .org-page .connector-h{
        height:2px;
        background:var(--line);
        width:100%;
    }

    .org-page .leaders-row{
        display:grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap:28px;
        align-items:start;
    }

    .org-page .leader-col{
        display:flex;
        flex-direction:column;
        align-items:center;
    }

    .org-page .leader-card{
        width:100%;
        max-width:320px;
        background:#fff;
        border:1px solid #e8eef7;
        border-radius:24px;
        box-shadow:0 16px 40px rgba(15,23,42,.06);
        overflow:hidden;
    }

    .org-page .leader-head{
        padding:12px 16px;
        text-align:center;
        font-weight:800;
        font-size:15px;
        background:#eff6ff;
        color:#1d4ed8;
        border-bottom:1px solid #dbeafe;
    }

    .org-page .leader-body{
        padding:18px;
        text-align:center;
    }

    .org-page .meta-row{
        margin-top:12px;
        display:flex;
        justify-content:center;
        gap:14px;
        flex-wrap:wrap;
        color:#6d28d9;
        font-size:13px;
        font-weight:700;
    }

    .org-page .child-grid{
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
        gap:18px;
        width:100%;
        margin-top:18px;
    }

    .org-page .staff-card{
        background:#fff;
        border:1px solid #eef2f7;
        border-radius:20px;
        padding:16px;
        box-shadow:0 10px 28px rgba(15,23,42,.05);
    }

    .org-page .staff-head{
        display:flex;
        align-items:center;
        gap:12px;
        margin-bottom:12px;
    }

    .org-page .staff-avatar,
    .org-page .staff-avatar-fallback{
        width:42px;
        height:42px;
        border-radius:50%;
        flex:0 0 42px;
    }

    .org-page .staff-avatar{
        object-fit:cover;
        border:2px solid #e2e8f0;
    }

    .org-page .staff-avatar-fallback{
        display:flex;
        align-items:center;
        justify-content:center;
        font-weight:700;
        background:#ede9fe;
        color:#4c1d95;
        border:2px solid #ddd6fe;
    }

    .org-page .staff-name{
        font-weight:700;
        color:#0f172a;
        line-height:1.2;
    }

    .org-page .staff-pos{
        color:#64748b;
        font-size:13px;
        margin-top:2px;
    }

    .org-page .sub-branch-wrap{
        width:100%;
        margin-top:18px;
    }

    .org-page .sub-branches{
        display:grid;
        grid-template-columns:repeat(2, minmax(260px, 1fr));
        gap:22px;
        width:100%;
    }

    .org-page .sub-branch{
        background:#fff;
        border:1px solid #e8eef7;
        border-radius:22px;
        overflow:hidden;
        box-shadow:0 14px 34px rgba(15,23,42,.05);
    }

    .org-page .sub-branch-head{
        background:#f8fafc;
        padding:12px 16px;
        border-bottom:1px solid #eef2f7;
        text-align:center;
        font-weight:800;
        color:#0f172a;
    }

    .org-page .sub-branch-body{
        padding:16px;
    }

    .org-page .empty-note{
        color:#94a3b8;
        font-size:13px;
        text-align:center;
        padding:18px 8px;
    }

    @media (max-width: 991px){
        .org-page .tree-root{
            min-width:900px;
        }
        .org-page .sub-branches{
            grid-template-columns:1fr;
        }
    }
</style>

<div class="container-fluid py-3 org-page">
    <div class="page-shell">

        <div class="hero">
            <div class="hero-title">Sơ đồ tổ chức</div>
            <div class="hero-sub">
                Hiển thị theo đúng cấu trúc: Ban giám đốc, trưởng nhóm các phòng ban và nhân viên của từng phòng ban.
                Riêng khối Marketing & Sales được tách thành 2 nhánh con độc lập.
            </div>
        </div>

        {{-- BAN GIÁM ĐỐC --}}
        <div class="surface-card p-4 mb-4">
            <div class="section-title">Ban giám đốc</div>
            <div class="section-sub mb-4">Nhóm điều hành cấp cao của công ty</div>

            <div class="board-grid">
                @forelse($boardUsers as $user)
                    @php
                        $avatarPath = data_get($user, 'avatar.path') ?? data_get($user, 'avatar.url');
                        $avatarUrl = $avatarPath
                            ? (str_starts_with($avatarPath, 'http') ? $avatarPath : asset('storage/' . ltrim($avatarPath, '/')))
                            : null;

                        $parts = preg_split('/\s+/', trim($user->name ?? ''));
                        $initials = '';
                        if (!empty($parts[0])) $initials .= mb_substr($parts[0], 0, 1);
                        if (count($parts) > 1) $initials .= mb_substr($parts[count($parts)-1], 0, 1);
                    @endphp

                    <div class="person-card">
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" class="avatar" alt="{{ $user->name }}">
                        @else
                            <div class="avatar-fallback">{{ $initials ?: 'GD' }}</div>
                        @endif

                        <div class="person-name">{{ $user->name }}</div>
                        <div class="person-role">{{ $user->position->name ?? 'Ban giám đốc' }}</div>
                    </div>
                @empty
                    <div class="text-muted">Chưa có dữ liệu ban giám đốc</div>
                @endforelse
            </div>
        </div>

        {{-- NHÁNH PHÒNG BAN --}}
        <div class="surface-card p-4">
            <div class="section-title">Trưởng nhóm các phòng ban</div>
            <div class="section-sub mb-4">
                Mỗi khối hiển thị trưởng nhóm trước, sau đó là nhân viên thuộc phòng ban đó.
            </div>

            <div class="tree">
                <div class="tree-root">

                    <div class="leaders-row">
                        @foreach($departmentNodes as $node)
                            @php
                                $isVirtual = isset($node['virtual_name']);
                                $leader = $node['leader'] ?? null;

                                $avatarPath = data_get($leader, 'avatar.path') ?? data_get($leader, 'avatar.url');
                                $avatarUrl = $avatarPath
                                    ? (str_starts_with($avatarPath, 'http') ? $avatarPath : asset('storage/' . ltrim($avatarPath, '/')))
                                    : null;

                                $parts = preg_split('/\s+/', trim($leader->name ?? ''));
                                $initials = '';
                                if (!empty($parts[0])) $initials .= mb_substr($parts[0], 0, 1);
                                if (count($parts) > 1) $initials .= mb_substr($parts[count($parts)-1], 0, 1);
                            @endphp

                            <div class="leader-col">
                                <div class="leader-card">
                                    <div class="leader-head">
                                        {{ $isVirtual ? $node['virtual_name'] : ($node['department']->name ?? 'Phòng ban') }}
                                    </div>

                                    <div class="leader-body">
                                        @if($leader)
                                            @if($avatarUrl)
                                                <img src="{{ $avatarUrl }}" class="avatar" alt="{{ $leader->name }}">
                                            @else
                                                <div class="avatar-fallback">{{ $initials ?: 'TP' }}</div>
                                            @endif

                                            <div class="person-name">{{ $leader->name }}</div>
                                            <div class="person-role">
                                                {{ $leader->position->name ?? 'Trưởng nhóm' }}
                                            </div>
                                        @else
                                            <div class="empty-note">Chưa có trưởng nhóm</div>
                                        @endif

                                        <div class="meta-row">
                                            @if($isVirtual)
                                                <span>👥 {{ $node['staff_count'] ?? 0 }} nhân sự</span>
                                                <span>🏢 {{ count($node['children'] ?? []) }} nhánh con</span>
                                            @else
                                                <span>👥 {{ $node['staffs']->count() + ($leader ? 1 : 0) }} nhân sự</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="connector-v"></div>

                                @if($isVirtual)
                                    <div class="sub-branch-wrap">
                                        <div class="sub-branches">
                                            @foreach(($node['children'] ?? []) as $child)
                                                @php
                                                    $childLeader = $child['leader'] ?? null;

                                                    $cAvatarPath = data_get($childLeader, 'avatar.path') ?? data_get($childLeader, 'avatar.url');
                                                    $cAvatarUrl = $cAvatarPath
                                                        ? (str_starts_with($cAvatarPath, 'http') ? $cAvatarPath : asset('storage/' . ltrim($cAvatarPath, '/')))
                                                        : null;

                                                    $cParts = preg_split('/\s+/', trim($childLeader->name ?? ''));
                                                    $cInitials = '';
                                                    if (!empty($cParts[0])) $cInitials .= mb_substr($cParts[0], 0, 1);
                                                    if (count($cParts) > 1) $cInitials .= mb_substr($cParts[count($cParts)-1], 0, 1);
                                                @endphp

                                                <div class="sub-branch">
                                                    <div class="sub-branch-head">
                                                        {{ $child['department']->name ?? 'Nhánh con' }}
                                                    </div>

                                                    <div class="sub-branch-body">
                                                        @if($childLeader)
                                                            <div class="staff-head">
                                                                @if($cAvatarUrl)
                                                                    <img src="{{ $cAvatarUrl }}" class="staff-avatar" alt="{{ $childLeader->name }}">
                                                                @else
                                                                    <div class="staff-avatar-fallback">{{ $cInitials ?: 'NV' }}</div>
                                                                @endif

                                                                <div>
                                                                    <div class="staff-name">{{ $childLeader->name }}</div>
                                                                    <div class="staff-pos">{{ $childLeader->position->name ?? 'Trưởng nhóm' }}</div>
                                                                </div>
                                                            </div>
                                                        @endif

                                                        <div class="child-grid">
                                                            @forelse($child['staffs'] as $staff)
                                                                @php
                                                                    $sAvatarPath = data_get($staff, 'avatar.path') ?? data_get($staff, 'avatar.url');
                                                                    $sAvatarUrl = $sAvatarPath
                                                                        ? (str_starts_with($sAvatarPath, 'http') ? $sAvatarPath : asset('storage/' . ltrim($sAvatarPath, '/')))
                                                                        : null;

                                                                    $sParts = preg_split('/\s+/', trim($staff->name ?? ''));
                                                                    $sInitials = '';
                                                                    if (!empty($sParts[0])) $sInitials .= mb_substr($sParts[0], 0, 1);
                                                                    if (count($sParts) > 1) $sInitials .= mb_substr($sParts[count($sParts)-1], 0, 1);
                                                                @endphp

                                                                <div class="staff-card">
                                                                    <div class="staff-head">
                                                                        @if($sAvatarUrl)
                                                                            <img src="{{ $sAvatarUrl }}" class="staff-avatar" alt="{{ $staff->name }}">
                                                                        @else
                                                                            <div class="staff-avatar-fallback">{{ $sInitials ?: 'NV' }}</div>
                                                                        @endif

                                                                        <div>
                                                                            <div class="staff-name">{{ $staff->name }}</div>
                                                                            <div class="staff-pos">{{ $staff->position->name ?? 'Nhân viên' }}</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @empty
                                                                <div class="empty-note">Chưa có nhân viên</div>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div class="child-grid">
                                        @forelse($node['staffs'] as $staff)
                                            @php
                                                $sAvatarPath = data_get($staff, 'avatar.path') ?? data_get($staff, 'avatar.url');
                                                $sAvatarUrl = $sAvatarPath
                                                    ? (str_starts_with($sAvatarPath, 'http') ? $sAvatarPath : asset('storage/' . ltrim($sAvatarPath, '/')))
                                                    : null;

                                                $sParts = preg_split('/\s+/', trim($staff->name ?? ''));
                                                $sInitials = '';
                                                if (!empty($sParts[0])) $sInitials .= mb_substr($sParts[0], 0, 1);
                                                if (count($sParts) > 1) $sInitials .= mb_substr($sParts[count($sParts)-1], 0, 1);
                                            @endphp

                                            <div class="staff-card">
                                                <div class="staff-head">
                                                    @if($sAvatarUrl)
                                                        <img src="{{ $sAvatarUrl }}" class="staff-avatar" alt="{{ $staff->name }}">
                                                    @else
                                                        <div class="staff-avatar-fallback">{{ $sInitials ?: 'NV' }}</div>
                                                    @endif

                                                    <div>
                                                        <div class="staff-name">{{ $staff->name }}</div>
                                                        <div class="staff-pos">{{ $staff->position->name ?? 'Nhân viên' }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="empty-note">Chưa có nhân viên</div>
                                        @endforelse
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>
@endsection