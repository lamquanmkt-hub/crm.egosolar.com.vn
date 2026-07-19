@extends('layouts.app')

@section('content')
<style>
    :root{
        --ego:#06b6d4;
        --ego2:#0891b2;
        --ink:#0f172a;
        --muted: rgba(15,23,42,.62);
        --card: rgba(255,255,255,.86);
        --border: rgba(15,23,42,.10);
        --shadow: 0 18px 50px rgba(15,23,42,.08);
        --shadow2: 0 10px 30px rgba(15,23,42,.08);
        --radius: 18px;
    }

    .page-shell{
        background: radial-gradient(900px 300px at 15% 0%, rgba(6,182,212,.14), transparent 60%),
                    radial-gradient(900px 300px at 85% 10%, rgba(59,130,246,.10), transparent 55%),
                    linear-gradient(180deg, #f7fbff, #f7fbff);
        border-radius: 22px;
        padding: 10px 6px 22px;
    }

    .page-head{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap: 12px;
        margin: 6px 6px 14px;
        flex-wrap: wrap;
    }

    .page-title{
        display:flex;
        align-items:center;
        gap: 12px;
    }

    .title-badge{
        width: 44px; height: 44px;
        border-radius: 16px;
        display:flex; align-items:center; justify-content:center;
        background: rgba(6,182,212,.16);
        border: 1px solid rgba(6,182,212,.25);
        color: var(--ego2);
        box-shadow: var(--shadow2);
        flex: 0 0 auto;
        font-size: 20px;
    }

    .page-title h1{
        margin:0;
        font-weight: 950;
        letter-spacing:.2px;
        color: var(--ink);
        line-height: 1.15;
    }

    .subtitle{
        margin-top: 6px;
        font-weight: 700;
        color: var(--muted);
        font-size: 13px;
    }

    .btn-ego{
        border: none;
        border-radius: 14px;
        padding: 10px 14px;
        font-weight: 950;
        background: linear-gradient(135deg, var(--ego), var(--ego2));
        box-shadow: 0 14px 34px rgba(8,145,178,.18);
        transition: .15s ease;
        color: #fff;
        text-decoration: none;
        display:inline-flex;
        align-items:center;
        gap:6px;
    }
    .btn-ego:hover{ transform: translateY(-1px); box-shadow: 0 18px 44px rgba(8,145,178,.24); color:#fff; }

    .card-glass{
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        overflow:hidden;
    }

    /* Alerts */
    .alert{
        border-radius: 16px;
        border: 1px solid rgba(15,23,42,.08);
        box-shadow: var(--shadow2);
    }

    /* ======= Modern table ======= */
    .table-wrap{
        border-radius: var(--radius);
        overflow: hidden;
        border: 1px solid rgba(15,23,42,.10);
        box-shadow: var(--shadow);
        background: rgba(255,255,255,.88);
    }

    .table-modern{
        margin: 0;
        font-size: 12.5px;
        white-space: nowrap;
    }

    .table-modern thead th{
        position: sticky;
        top: 0;
        z-index: 5;
        background: rgba(15,23,42,.92) !important;
        color: rgba(255,255,255,.92) !important;
        font-weight: 900;
        border-bottom: 1px solid rgba(255,255,255,.12);
        padding: 12px 10px;
        text-align: left;
    }

    .table-modern tbody td{
        vertical-align: middle;
        padding: 10px 10px;
        border-color: rgba(15,23,42,.08);
        color: rgba(15,23,42,.82);
        font-weight: 650;
    }

    .table-modern tbody tr:hover{
        background: rgba(6,182,212,.08) !important; /* hover xanh nước */
    }

    .badge-role{
        border-radius: 999px;
        padding: 6px 10px;
        font-weight: 950;
        background: rgba(6,182,212,.14);
        border: 1px solid rgba(6,182,212,.28);
        color: #075985;
        display:inline-flex;
        align-items:center;
        gap:6px;
        margin: 2px 4px 2px 0;
    }

    .btn-icon{
        width: 34px;
        height: 34px;
        border-radius: 12px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:0;
    }

    .cell-id{ width: 70px; text-align:center; }
    .cell-actions{ width: 180px; text-align:center; }
</style>

<div class="container-fluid px-4 py-3">
    <div class="page-shell">

        <div class="page-head">
            <div class="page-title">
                <div class="title-badge"><i class="bi bi-people"></i></div>
                <div>
                    <h1>USERS</h1>
                    <div class="subtitle">Quản lý tài khoản • phân quyền • chỉnh sửa thông tin</div>
                </div>
            </div>

            @can('create', App\Models\User::class)
                <a href="{{ route('users.create') }}" class="btn btn-ego">
                    <i class="bi bi-plus-lg"></i> Add User
                </a>
            @endcan
        </div>

        @if(session('success'))
            <div class="alert alert-success mx-1">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger mx-1">
                <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
            </div>
        @endif

        <div class="card-glass">
            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle table-modern">
                        <thead>
                            <tr>
                                <th class="cell-id">#</th>
                                <th>Name</th>
                                <th>Email</th>
                                @role('admin')
                                    <th>Roles</th>
                                @endrole
                                <th class="cell-actions">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td class="cell-id">{{ $user->id }}</td>

                                    <td class="fw-semibold">
                                        <i class="bi bi-person-circle me-1 text-muted"></i>
                                        {{ $user->name }}
                                    </td>

                                    <td>
                                        <i class="bi bi-envelope me-1 text-muted"></i>
                                        {{ $user->email }}
                                    </td>

                                    @role('admin')
                                        <td style="white-space: normal;">
                                            @forelse($user->roles as $role)
                                                <span class="badge-role">
                                                    <i class="bi bi-shield-lock"></i> {{ $role->name }}
                                                </span>
                                            @empty
                                                <span class="text-muted">—</span>
                                            @endforelse
                                        </td>
                                    @endrole

                                    <td class="cell-actions">
                                        <div class="d-flex gap-1 justify-content-center">
                                            @can('update', $user)
                                                <a class="btn btn-outline-warning btn-icon"
                                                   href="{{ route('users.edit', $user) }}"
                                                   title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endcan

                                            @can('delete', $user)
                                                <form action="{{ route('users.destroy', $user) }}"
                                                      method="POST"
                                                      class="d-inline"
                                                      onsubmit="return confirm('Delete this user?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-outline-danger btn-icon" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="@role('admin')5 @else 4 @endrole" class="text-center py-4 text-muted">
                                        No users found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="px-3 py-3 d-flex justify-content-end">
                {{ $users->links('pagination::bootstrap-5') }}
            </div>
        </div>

    </div>
</div>
@endsection
