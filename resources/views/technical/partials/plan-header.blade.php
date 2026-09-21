{{--
    HEADER DÙNG CHUNG CHO BA TRANG KẾ HOẠCH
    =======================================
    Một partial duy nhất cho:
      (a) Admin / Giám đốc  — `/ky-thuat/dashboard/ke-hoach`   (variant = 'admin')
      (b) Nhân viên         — `/ky-thuat/ke-hoach-tuan`        (variant = 'staff')
      (c) Trưởng phòng      — `/ky-thuat/quan-ly/ke-hoach`     (variant = 'manager')

    Nhờ vậy ba vai trò nhìn CÙNG một hệ giao diện: cùng tiêu đề, phụ đề, CTA,
    khoảng cách. Nhãn CTA tự đổi theo vai trò:
      - nhân viên                    : "Tạo kế hoạch của tôi"
      - trưởng phòng / Admin / GĐ    : "Tạo kế hoạch" (chính) + "Giao việc" (phụ)

    2026-09: nút gộp "Tạo / Giao kế hoạch" của trưởng phòng ĐÃ BỊ BỎ. Hai vai
    trò quản lý dùng chung đúng HAI nút riêng, mở thẳng form thật (drawer).

    NGHIỆP VỤ MỚI 2026-09 (đảo ngược đặc tả "Admin chỉ xem" của đợt trước):
    Admin / Giám đốc được TẠO kế hoạch và GIAO việc như trưởng phòng. Quyền
    backend vốn đã cho phép (`TechnicalAccess::canManage()` trả true cho Admin,
    `TechnicalPlanBoardController` chỉ kiểm `canManage`), trước đây chỉ bị CHẶN Ở
    GIAO DIỆN. Nay mở đúng chỗ đó và TÁI SỬ DỤNG nguyên luồng giao việc của
    trưởng phòng (`technical.manager.*`) — không dựng form thứ hai.

    BẢO MẬT: các CTA ở đây đều là LIÊN KẾT GET; trang Kế hoạch của Admin vẫn
    không chứa form POST / `_method` / `@csrf`. Mọi thao tác ghi thực hiện ở
    đúng màn hình điều phối, nơi controller kiểm quyền lại lần nữa.

    Tham số:
      $variant     'admin' | 'staff' | 'manager'
      $title       tiêu đề H1 (mặc định "Kế hoạch Kỹ thuật")
      $subtitle    phụ đề
      $weekParam   chuỗi Y-m-d của thứ Hai đầu tuần đang xem (tuỳ chọn)
      $memberId    id nhân viên đang xem, dùng cho CTA của trưởng phòng (tuỳ chọn)
      $extra       HTML thêm vào cụm nút bên phải (tuỳ chọn)
      $guideSlug   slug bài "Cách sử dụng" của trang (tuỳ chọn). Khi có, một nút
                   phụ `btn-light` được chèn NGOÀI CÙNG BÊN TRÁI cụm CTA — cố ý
                   ít nổi bật để không cạnh tranh với nút nghiệp vụ màu primary.
--}}
@php
    $tphVariant = (string) ($variant ?? 'admin');
    $tphTitle = (string) ($title ?? 'Kế hoạch Kỹ thuật');
    $tphSubtitle = (string) ($subtitle ?? 'Lập và theo dõi kế hoạch làm việc của đội Kỹ thuật theo tuần.');
    $tphWeek = ($weekParam ?? null) ? (string) $weekParam : null;
    $tphMember = ($memberId ?? null) ? (int) $memberId : null;

    $tphUser = auth()->user();

    /* Quyền quản trị kế hoạch — dùng lại đúng lớp phân quyền sẵn có. */
    $tphCanManage = false;
    try {
        $tphCanManage = app(\App\Services\Technical\TechnicalAccess::class)->canManage($tphUser);
    } catch (\Throwable $e) {
        $tphCanManage = false;
    }

    $tphCtaLabel = null;
    $tphCtaUrl = null;
    $tphCtaHint = null;

    /* Nút PHỤ ("Giao việc"). */
    $tphSecondLabel = null;
    $tphSecondUrl = null;
    $tphSecondHint = null;

    /* Khi trang tự chứa drawer, hai nút là nút mở offcanvas tại chỗ. */
    $tphDrawer = (bool) ($drawer ?? false);
    $tphCtaTarget = null;
    $tphSecondTarget = null;

    /* Cho phép trang truyền thẳng URL đích (ví dụ neo tới khung giao việc ngay trên trang). */
    $tphCtaUrlOverride = ($ctaUrl ?? null) ? (string) $ctaUrl : null;

    if ($tphVariant === 'staff') {
        $tphCtaLabel = 'Tạo kế hoạch của tôi';
        $tphCtaUrl = \Illuminate\Support\Facades\Route::has('technical.week-plan.index')
            ? route('technical.week-plan.index', array_filter(['week' => $tphWeek, 'add' => 1])).'#tp-add-form'
            : null;
        $tphCtaHint = 'Mở sẵn khung thêm công việc vào kế hoạch tuần của bạn.';
    } elseif (($tphVariant === 'manager' || $tphVariant === 'admin') && $tphCanManage) {
        /*
            2026-09 — LUỒNG MỚI: HAI NÚT RIÊNG, mở THẲNG form thật.
            Không còn nút gộp "Tạo / Giao kế hoạch" và không còn bước chọn nhân
            viên trung gian (`?focus=assign` cũ chỉ cuộn tới khung chọn người).

              "+ Tạo kế hoạch" -> drawer tạo kế hoạch nhiều dòng
              "Giao việc"      -> drawer giao một việc nhanh

            Trên chính trang ma trận (`$drawer = true`) hai nút là nút bấm mở
            offcanvas ngay tại chỗ; ở các trang khác chúng là LIÊN KẾT GET
            deep-link `?open=create` / `?open=assign` về đúng drawer đó, nên
            các trang đó vẫn không chứa form ghi nào.
        */
        $tphCtaLabel = 'Tạo kế hoạch';
        $tphCtaHint = 'Mở form tạo kế hoạch tuần cho nhân viên.';
        $tphSecondLabel = 'Giao việc';
        $tphSecondHint = 'Mở form giao một công việc cho nhân viên.';

        if ($tphDrawer) {
            $tphCtaTarget = '#tpCreateDrawer';
            $tphSecondTarget = '#tpAssignDrawer';
        } elseif (\Illuminate\Support\Facades\Route::has('technical.manager.board')) {
            $tphCtaUrl = route('technical.manager.board', array_filter([
                'open' => 'create',
                'week' => $tphWeek,
                'user_id' => $tphMember,
            ]));
            $tphSecondUrl = route('technical.manager.board', array_filter([
                'open' => 'assign',
                'week' => $tphWeek,
                'user_id' => $tphMember,
            ]));
        }
    }

    if ($tphCtaLabel !== null && $tphCtaUrlOverride !== null) {
        $tphCtaUrl = $tphCtaUrlOverride;
    }
@endphp

<div class="tw-head tp-head">
    <div>
        <h1 class="tw-head__title">{{ $tphTitle }}</h1>
        <p class="tw-head__sub">{{ $tphSubtitle }}</p>
    </div>

    <div class="tw-head__actions tp-head__actions">
        @if(!empty($guideSlug))
            @include('technical.guides.partials.help-button', ['slug' => $guideSlug])
        @endif

        {!! $extra ?? '' !!}

        @if($tphSecondLabel && $tphSecondTarget)
            <button type="button"
                    class="btn btn-outline-primary btn-sm tp-cta-assign"
                    data-tp-cta="assign-work"
                    data-bs-toggle="offcanvas"
                    data-bs-target="{{ $tphSecondTarget }}"
                    title="{{ $tphSecondHint }}">
                <i class="bi bi-person-plus"></i> {{ $tphSecondLabel }}
            </button>
        @elseif($tphSecondLabel && $tphSecondUrl)
            <a href="{{ $tphSecondUrl }}"
               class="btn btn-outline-primary btn-sm tp-cta-assign"
               data-tp-cta="assign-work"
               title="{{ $tphSecondHint }}">
                <i class="bi bi-person-plus"></i> {{ $tphSecondLabel }}
            </a>
        @endif

        @if($tphCtaLabel && $tphCtaTarget)
            <button type="button"
                    class="btn btn-primary btn-sm tp-cta-create"
                    data-tp-cta="create-plan"
                    data-bs-toggle="offcanvas"
                    data-bs-target="{{ $tphCtaTarget }}"
                    title="{{ $tphCtaHint }}">
                <i class="bi bi-plus-lg"></i> {{ $tphCtaLabel }}
            </button>
        @elseif($tphCtaLabel && $tphCtaUrl)
            <a href="{{ $tphCtaUrl }}"
               class="btn btn-primary btn-sm tp-cta-create"
               data-tp-cta="create-plan"
               title="{{ $tphCtaHint }}">
                <i class="bi bi-plus-lg"></i> {{ $tphCtaLabel }}
            </a>
        @endif
    </div>
</div>
