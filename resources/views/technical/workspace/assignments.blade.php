@extends('layouts.app')

@section('title', 'Phân công nhân sự - Workspace Kỹ thuật')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/technical-workspace.css') }}?v={{ file_exists(public_path('css/technical-workspace.css')) ? filemtime(public_path('css/technical-workspace.css')) : time() }}">
<style>
    .tw-assign-card {
        background: #fff;
        border: 1px solid var(--tw-border);
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
    }
    .tw-assign-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }
    .tw-assign-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--tw-text-main);
    }
    .tw-assign-meta {
        font-size: 0.875rem;
        color: var(--tw-text-muted);
        display: flex;
        gap: 16px;
        margin-bottom: 16px;
    }
    .tw-assign-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }
</style>
@endpush

@section('content')
<div class="tw-layout">
    @include('technical.workspace.partials-nav')

    <main class="tw-main">
        <header class="tw-header">
            <div>
                <h1 class="tw-h1">Phân công nhân sự</h1>
                <p class="tw-sub">Danh sách công trình chờ phân công thi công (Chỉ dành cho Quản lý / Điều phối)</p>
            </div>
        </header>

        <div class="tw-content">
            @if(session('success'))
                <div class="tw-alert tw-alert--success" style="margin-bottom: 20px;">
                    <i class="bi bi-check-circle"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="tw-alert tw-alert--danger" style="margin-bottom: 20px;">
                    <i class="bi bi-exclamation-octagon"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            @if(! $isManager)
                <div class="tw-alert tw-alert--danger">
                    <i class="bi bi-shield-lock"></i>
                    <div>
                        <strong>Truy cập bị từ chối</strong>
                        <span>Bạn không có quyền phân công nhân sự. Vui lòng vào "Việc của tôi" để xem các công việc được giao.</span>
                    </div>
                </div>
            @else
                @forelse($projects as $project)
                    <div class="tw-assign-card">
                        <div class="tw-assign-header">
                            <div>
                                <div class="tw-assign-title">{{ $project->code }} - {{ $project->name }}</div>
                                <div class="tw-assign-meta">
                                    <span><i class="bi bi-geo-alt"></i> {{ $project->address ?: 'Chưa cập nhật địa chỉ' }}</span>
                                    <span><i class="bi bi-calendar"></i> Lắp đặt dự kiến: {{ $project->proposed_installation_at ? \Carbon\Carbon::parse($project->proposed_installation_at)->format('d/m/Y') : 'Chưa có' }}</span>
                                    <span><i class="bi bi-info-circle"></i> Trạng thái: {{ $project->status }}</span>
                                </div>
                            </div>
                            <span class="tw-status tw-status--pending">Chờ phân công</span>
                        </div>
                        
                        <div class="tw-assign-actions">
                            <form action="{{ route('technical-workspace.coordination.assignments.store') }}" method="POST" style="display: flex; gap: 8px; align-items: center;">
                                @csrf
                                <input type="hidden" name="project_id" value="{{ $project->id }}">
                                <select name="assigned_to" class="tw-select" required>
                                    <option value="">-- Chọn kỹ thuật viên --</option>
                                    @foreach($teamMembers as $member)
                                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="tw-btn tw-btn--primary">Phân công</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="tw-empty">
                        <i class="bi bi-check2-all"></i>
                        <p>Không có công trình nào đang chờ phân công lúc này.</p>
                    </div>
                @endforelse

                @if($projects->hasPages())
                    <div style="margin-top: 20px;">
                        {{ $projects->links() }}
                    </div>
                @endif
            @endif
        </div>
    </main>
</div>
@endsection
