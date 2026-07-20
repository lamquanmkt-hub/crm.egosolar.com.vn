@extends('layouts.app')

@section('content')
<style>
    .noti-page{
        min-height:100vh;
        background:#f4f7fb;
        padding-bottom:48px;
        font-size:13px;
        font-family:inherit;
    }

    .noti-shell{
        padding:22px;
        max-width:1080px;
        margin:0 auto;
    }

    .noti-hero{
        border-radius:24px;
        padding:24px;
        color:#fff;
        background:
            radial-gradient(700px 280px at 88% 0%, rgba(34,211,238,.24), transparent 60%),
            linear-gradient(135deg,#020617,#075985 58%,#0f766e);
        box-shadow:0 18px 44px rgba(15,23,42,.18);
        margin-bottom:16px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
    }

    .noti-hero h1{
        font-size:26px;
        font-weight:950;
        letter-spacing:-.03em;
        margin:0;
    }

    .noti-hero p{
        margin:5px 0 0;
        opacity:.84;
        font-size:13px;
    }

    .btn-pill{
        border-radius:999px;
        font-weight:800;
        font-size:13px;
        padding:8px 16px;
    }

    .noti-card{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:22px;
        overflow:hidden;
        box-shadow:0 12px 32px rgba(15,23,42,.065);
    }

    .noti-toolbar{
        padding:14px 16px;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        border-bottom:1px solid #e5eaf1;
        background:#fff;
    }

    .noti-title{
        font-size:15px;
        font-weight:950;
        color:#0f172a;
        margin:0;
    }

    .noti-desc{
        color:#64748b;
        font-size:12px;
        margin-top:2px;
    }

    .noti-list{
        display:grid;
    }

    .noti-item{
        display:grid;
        grid-template-columns:48px 1fr auto;
        gap:12px;
        padding:14px 16px;
        border-bottom:1px solid #e5eaf1;
        align-items:flex-start;
        transition:.15s ease;
        position:relative;
    }

    .noti-item:last-child{
        border-bottom:0;
    }

    .noti-item:hover{
        background:#f8fafc;
    }

    .noti-item.unread{
        background:#eff6ff;
    }

    .noti-item.unread:hover{
        background:#dbeafe;
    }

    .noti-icon{
        width:48px;
        height:48px;
        border-radius:999px;
        display:flex;
        align-items:center;
        justify-content:center;
        color:#fff;
        background:linear-gradient(135deg,#0ea5e9,#2563eb);
        box-shadow:0 8px 20px rgba(37,99,235,.22);
        font-size:20px;
    }

    .noti-body{
        min-width:0;
    }

    .noti-main-title{
        font-size:14px;
        font-weight:950;
        color:#0f172a;
        margin-bottom:4px;
    }

    .noti-message{
        color:#475569;
        font-size:13px;
        line-height:1.45;
    }

    .noti-time{
        margin-top:6px;
        color:#2563eb;
        font-size:12px;
        font-weight:800;
    }

    .noti-order-link{
        display:inline-flex;
        align-items:center;
        gap:5px;
        margin-top:8px;
        color:#0369a1;
        background:#e0f2fe;
        text-decoration:none;
        border-radius:999px;
        padding:6px 10px;
        font-size:12px;
        font-weight:900;
    }

    .noti-order-link:hover{
        background:#bae6fd;
        color:#075985;
    }

    .noti-actions{
        display:flex;
        flex-direction:column;
        align-items:flex-end;
        gap:8px;
    }

    .noti-dot{
        width:10px;
        height:10px;
        border-radius:999px;
        background:#2563eb;
        box-shadow:0 0 0 4px rgba(37,99,235,.12);
    }

    .empty-state{
        padding:54px 20px;
        text-align:center;
        color:#64748b;
    }

    .empty-icon{
        width:62px;
        height:62px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius:22px;
        background:#eef6ff;
        color:#0369a1;
        font-size:30px;
        margin-bottom:12px;
    }

    .pagination-wrap{
        padding:14px 16px;
        border-top:1px solid #e5eaf1;
        background:#fff;
    }

    @media(max-width:768px){
        .noti-shell{padding:14px}
        .noti-hero{flex-direction:column;align-items:flex-start}
        .noti-toolbar{flex-direction:column;align-items:flex-start}
        .noti-item{grid-template-columns:44px 1fr}
        .noti-actions{
            grid-column:2;
            align-items:flex-start;
            flex-direction:row;
        }
    }
</style>

<div class="noti-page">
    <div class="noti-shell">

        <div class="noti-hero">
            <div>
                <h1>Thông báo</h1>
                <p>Theo dõi đơn hàng, công việc, đề xuất và các cập nhật quan trọng trong hệ thống.</p>
            </div>

            <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                @csrf
                <button class="btn btn-light btn-pill" type="submit">
                    <i class="bi bi-check2-all"></i> Đọc tất cả
                </button>
            </form>
        </div>

        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-4">
                {{ session('success') }}
            </div>
        @endif

        <div class="noti-card">
            <div class="noti-toolbar">
                <div>
                    <h2 class="noti-title">
                        <i class="bi bi-bell"></i> Danh sách thông báo
                    </h2>
                    <div class="noti-desc">
                        Thông báo chưa đọc sẽ được tô nền xanh nhạt giống Facebook.
                    </div>
                </div>

                <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                    @csrf
                    <button class="btn btn-outline-primary btn-pill" type="submit">
                        Đánh dấu đã đọc
                    </button>
                </form>
            </div>

            @if(empty($notifications) || (is_object($notifications) && method_exists($notifications,'count') && $notifications->count()==0))
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-bell-slash"></i>
                    </div>
                    <div class="fw-bold text-dark">Chưa có thông báo</div>
                    <div class="mt-1">Khi có thông báo mới, nó sẽ xuất hiện tại đây.</div>
                </div>
            @else
                <div class="noti-list">
                    {{-- NotificationController@index luôn truyền Collection.
                         KHÔNG dùng $notifications->data: trên Collection, truy cập
                         thuộc tính không tồn tại sẽ NÉM exception chứ không trả null,
                         nên `??` không đỡ được và trang trả 500 khi có thông báo. --}}
                    @foreach($notifications as $n)
                        @php
                            $id = is_array($n) ? ($n['id'] ?? null) : ($n->id ?? null);

                            $title = is_array($n)
                                ? ($n['title'] ?? 'Thông báo')
                                : ($n->title ?? 'Thông báo');

                            $msg = is_array($n)
                                ? ($n['message'] ?? '')
                                : ($n->message ?? '');

                            $isRead = is_array($n)
                                ? ($n['is_read'] ?? false)
                                : ($n->is_read ?? false);

                            $orderId = is_array($n)
                                ? ($n['order_id'] ?? null)
                                : ($n->order_id ?? null);

                            $link = $orderId ? url("/orders/$orderId") : '#';

                            $createdAt = is_array($n)
                                ? ($n['created_at'] ?? null)
                                : ($n->created_at ?? null);
                        @endphp

                        <div class="noti-item {{ $isRead ? '' : 'unread' }}">
                            <div class="noti-icon">
                                @if($orderId)
                                    <i class="bi bi-receipt"></i>
                                @else
                                    <i class="bi bi-bell-fill"></i>
                                @endif
                            </div>

                            <div class="noti-body">
                                <div class="noti-main-title">{{ $title }}</div>

                                @if($msg)
                                    <div class="noti-message">{{ $msg }}</div>
                                @endif

                                @if($createdAt)
                                    <div class="noti-time">
                                        <i class="bi bi-clock"></i> {{ $createdAt }}
                                    </div>
                                @endif

                                @if($orderId)
                                    <a href="{{ $link }}" class="noti-order-link">
                                        <i class="bi bi-box-arrow-up-right"></i> Mở đơn hàng
                                    </a>
                                @endif
                            </div>

                            <div class="noti-actions">
                                @if(!$isRead)
                                    <div class="noti-dot" title="Chưa đọc"></div>
                                @endif

                                @if($id)
                                    <form method="POST" action="{{ route('notifications.mark-read', $id) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary btn-pill" type="submit">
                                            Đã đọc
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if(is_object($notifications) && method_exists($notifications, 'links'))
                    <div class="pagination-wrap">
                        {{ $notifications->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>
</div>
@endsection