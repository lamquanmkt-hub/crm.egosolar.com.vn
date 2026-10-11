{{--
    Hướng dẫn sử dụng Công trình → Nghiệm thu → KPI (trang riêng, chỉ đọc).
    Route: projects-unified.guide (/du-an/{site}/huong-dan) — cùng quyền xem công trình.
    Tái dùng CSS + partials của bộ hướng dẫn Kỹ thuật (step, ảnh phóng to, sơ đồ, ghi chú).
    Ảnh: public/images/guides/projects/ — chụp giao diện thật với dữ liệu TEST.
--}}
@extends('layouts.app')

@section('title', 'Hướng dẫn công trình và nghiệm thu tính KPI')

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('css/ego-technical-guide.css') }}?v={{ file_exists(public_path('css/ego-technical-guide.css')) ? filemtime(public_path('css/ego-technical-guide.css')) : '1.0.0' }}">
    <style>
        .pg-topbar{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:10px;margin-bottom:6px}
        .pg-roles{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:6px 0 16px}
        .pg-role{display:flex;flex-direction:column;gap:6px;padding:14px 16px;border:1px solid #d7e3ea;border-radius:12px;background:#fff;color:inherit;text-decoration:none}
        .pg-role:hover{border-color:#07988a;background:#f6fbfb}
        .pg-role.is-mine{border-color:#07988a;box-shadow:0 0 0 2px rgba(7,152,138,.15)}
        .pg-role strong{font-size:.98rem}
        .pg-role span{font-size:.85rem;color:#5b6f80;line-height:1.5}
        .pg-role .pg-mine{font-size:.72rem;font-weight:700;color:#07786f;text-transform:uppercase;letter-spacing:.04em}
        .pg-table{width:100%;border-collapse:collapse;font-size:.88rem;margin:4px 0 10px}
        .pg-table th,.pg-table td{border:1px solid #dfe8ee;padding:8px 10px;vertical-align:top;text-align:left}
        .pg-table th{background:#f5f9fb;font-weight:700}
        .pg-table td.num{white-space:nowrap;font-weight:700;color:#07786f}
        .pg-tablewrap{overflow-x:auto}
        .pg-kpi-state{display:inline-block;font-size:.75rem;font-weight:700;border-radius:999px;padding:2px 9px;margin-left:4px}
        .pg-kpi-state.no{background:#fff4e5;color:#9a5b00}
        .pg-kpi-state.yes{background:#e8f7ee;color:#15803d}
        @media (max-width: 767.98px){
            .pg-roles{grid-template-columns:1fr}
            .pg-topbar .btn{width:100%}
        }
    </style>
@endpush

@php
    $pgCode = (string) ($site->project_code ?: 'CT-'.$site->id);
    $pgRoleCards = [
        'staff' => ['ky-thuat', 'Kỹ thuật được giao', 'Tải hồ sơ, nhập kết quả nghiệm thu (chất lượng, HSE, EVN/App), Lưu nháp → Gửi kiểm tra, sửa khi bị trả bổ sung, Gửi duyệt bước.'],
        'manager' => ['truong-phong', 'Trưởng phòng Kỹ thuật', 'Xem bản kỹ thuật đã gửi và minh chứng, xác nhận từng nhóm đủ dữ liệu hoặc trả bổ sung có lý do.'],
        'admin' => ['admin', 'Admin', 'Duyệt nghiệm thu cuối cùng. Chỉ sau bước này công trình mới được đưa vào KPI của tháng duyệt.'],
    ];
    $pgSources = [
        'timeline' => 'Việc trưởng phòng giao → Báo cáo ngày. Hạn 24 giờ tính từ lúc giao, xét lần gửi báo cáo đầu tiên. Không lấy vòng tròn tiến độ công trình.',
        'quality' => 'Nghiệm thu: phản ánh của khách hàng đã được trưởng phòng xác nhận và lịch sử “Trả lại” ở Thi công/Nghiệm thu.',
        'survey' => 'Hồ sơ Khảo sát & PA đủ theo danh mục và % sai lệch vật tư dưới 3%. Tại Nghiệm thu, Trưởng phòng nhập tay, ghi cách tính, tải Bảng quyết toán vật tư rồi bấm Xác nhận kết quả vật tư. Lưu nháp chưa tính KPI; xác nhận này không thay Admin duyệt nghiệm thu. Nguồn cũ trước 08/10/2026 giữ cách đọc lịch sử cho tới khi được sửa bằng form mới.',
        'hse' => 'Nghiệm thu: 5 mục an toàn – vệ sinh đã được xác nhận.',
        'evn_app' => 'Nghiệm thu: đóng điện thử EVN (nếu công trình cần đấu nối) và bàn giao App, đã được xác nhận.',
        'warranty' => 'Phiếu Bảo trì/Bảo hành: phản hồi khách, thời gian xử lý xong và khiếu nại — nguồn riêng, không lấy từ công trình.',
    ];
    $pgMonth = \Illuminate\Support\Carbon::parse($kpiMonth.'-01')->format('m/Y');
    // Cấu hình đã duyệt nhưng có hiệu lực trước bộ tiêu chí mới → trang KPI báo cần duyệt bản mới; nói đúng như vậy.
    $pgRuleText = ! empty($kpiRule['has_config']) && empty($kpiRule['config_stale']) && $kpiConfig
        ? 'đang dùng '.$kpiRule['label'].', tiền KPI '.mb_strtolower(\App\Models\TechnicalKpiConfig::PAYOUT_MODE_LABELS[$kpiConfig->payoutMode()] ?? '').'.'
        : (string) ($kpiRule['label'] ?? 'chưa có cấu hình đã duyệt cho kỳ này.').(str_ends_with((string) ($kpiRule['label'] ?? ''), '.') ? '' : '.');
    $pgToc = [
        'ban-can-lam-gi' => 'Bạn cần làm gì?',
        'quy-trinh' => 'Các bước của công trình',
        'nguon-kpi' => 'Công trình góp vào KPI thế nào',
        'ky-thuat' => 'Kỹ thuật nhập nghiệm thu',
        'truong-phong' => 'Trưởng phòng kiểm tra',
        'admin' => 'Admin duyệt nghiệm thu',
        'xem-diem' => 'Xem điểm và nguồn',
        'nham-lan' => 'Những điểm hay nhầm',
        'nguon-cu' => 'Nguồn cũ, sửa lại, kỳ đã chốt',
    ];
@endphp

@section('content')
<div class="container-fluid py-3 tg-wrap" data-project-guide>

    <nav class="tg-breadcrumb" aria-label="Đường dẫn">
        <a href="{{ route('projects-unified.index') }}">Công trình</a>
        <span class="sep">/</span>
        <a href="{{ $returnUrl }}">{{ $pgCode }}</a>
        <span class="sep">/</span>
        <span>Hướng dẫn sử dụng</span>
    </nav>

    <div class="pg-topbar">
        <a href="{{ $returnUrl }}" class="btn btn-primary btn-sm" data-pg-return>
            <i class="bi bi-arrow-left"></i> Quay lại công trình {{ $pgCode }}
        </a>
    </div>

    <div class="tg-layout">
        <div>
            <div class="tg-head">
                <h1 class="tg-head__title">Hướng dẫn công trình và nghiệm thu tính KPI</h1>
                <p class="tg-head__sub mb-0">
                    Dành cho người mới: mỗi bước ghi rõ <strong>ai làm, bấm đâu, nhập gì, thấy gì</strong> và <strong>đã được tính KPI chưa</strong>.
                    Ảnh minh hoạ chụp từ màn hình thật với dữ liệu <strong>TEST</strong> — bấm ảnh để phóng to. Đọc bài không cấp thêm quyền; nút nào hiện ra do quyền của bạn quyết định.
                </p>
            </div>

            {{-- 1. Bạn cần làm gì --}}
            <section class="tg-section" id="ban-can-lam-gi">
                <h2 class="tg-section__title"><span class="num">1</span> Bạn cần làm gì?</h2>
                <div class="pg-roles">
                    @foreach($pgRoleCards as $pgKey => [$pgAnchor, $pgTitle, $pgText])
                        <a href="#{{ $pgAnchor }}" class="pg-role {{ $viewerRole === $pgKey ? 'is-mine' : '' }}" data-pg-role="{{ $pgKey }}">
                            @if($viewerRole === $pgKey)<span class="pg-mine">Vai trò của bạn</span>@endif
                            <strong>{{ $pgTitle }}</strong>
                            <span>{{ $pgText }}</span>
                        </a>
                    @endforeach
                </div>
                @include('technical.guides.partials.flow-diagram', [
                    'steps' => [
                        ['icon' => 'bi-cloud-arrow-up', 'label' => 'Kỹ thuật', 'description' => 'Tải hồ sơ, nhập kết quả, Gửi kiểm tra'],
                        ['icon' => 'bi-clipboard-check', 'label' => 'Trưởng phòng', 'description' => 'Xác nhận nhóm hoặc trả bổ sung', 'color' => 'warn'],
                        ['icon' => 'bi-patch-check', 'label' => 'Admin', 'description' => 'Duyệt nghiệm thu cuối', 'color' => 'accent'],
                        ['icon' => 'bi-graph-up', 'label' => 'Trang KPI', 'description' => 'Tính theo tháng duyệt nghiệm thu', 'color' => 'ok'],
                    ],
                ])
            </section>

            {{-- 2. Quy trình công trình --}}
            <section class="tg-section" id="quy-trinh">
                <h2 class="tg-section__title"><span class="num">2</span> Công trình đi qua những bước nào?</h2>
                <p>Cột <strong>QUY TRÌNH</strong> bên trái trang công trình là các bước theo thứ tự. Bấm một bước để mở phần việc của bước đó.</p>
                <ul>
                    <li><strong>Tổng quan</strong> — thông tin chung, người phụ trách, tiến độ.</li>
                    <li><strong>Khảo sát &amp; PA</strong> — hồ sơ khảo sát, phương án.</li>
                    <li><strong>HĐ &amp; Pháp lý</strong> — hợp đồng, hồ sơ pháp lý và quyết định <em>Công trình cần đấu nối điện lực</em> (dùng cho EVN/App).</li>
                    <li><strong>Đề xuất vật tư</strong>, <strong>Thi công</strong> — vật tư và báo cáo thi công.</li>
                    <li><strong>Nghiệm thu</strong> — hồ sơ nghiệm thu, khối <em>Kết quả nghiệm thu tính KPI</em> và nút duyệt. Đây là phần chính của bài.</li>
                    <li><strong>Tài chính công trình</strong> — chỉ hiện với người được xem tài chính.</li>
                </ul>
                @include('technical.guides.partials.note', ['tone' => 'warning', 'title' => 'Vòng tròn % trên công trình không phải % KPI',
                    'text' => 'Vòng tròn <strong>TIẾN ĐỘ CÔNG TRÌNH</strong> (ví dụ 86% trong ảnh dưới) chỉ cho biết công trình đã đi qua bao nhiêu bước quy trình. Nó <strong>không phải</strong> điểm KPI của ai và không phải % tiền lương.'])
                @include('technical.guides.partials.step', [
                    'num' => '•', 'title' => 'Mở công trình và tìm bước Nghiệm thu',
                    'desc' => 'Vào <strong>Công trình → Danh sách công trình</strong>, bấm tên công trình. Bên trái chọn <strong>Nghiệm thu</strong>. Nút <strong>Hướng dẫn sử dụng</strong> ở góc phải phía trên mở lại bài này.',
                    'shot' => 'images/guides/projects/p01-cong-trinh-nut-huong-dan.png',
                    'alt' => 'Trang công trình: nút Hướng dẫn sử dụng, vòng tròn tiến độ công trình và bước Nghiệm thu',
                    'caption' => 'Dữ liệu minh hoạ TEST. (1) Nút Hướng dẫn sử dụng · (2) Vòng tròn tiến độ công trình — không phải KPI · (3) Bước Nghiệm thu.',
                ])
            </section>

            {{-- 3. Nguồn KPI --}}
            <section class="tg-section" id="nguon-kpi">
                <h2 class="tg-section__title"><span class="num">3</span> Công trình góp vào KPI kỹ thuật thế nào?</h2>
                <p>Bộ tiêu chí đang dùng cho kỳ <strong>{{ $pgMonth }}</strong> (đọc từ hệ thống lúc bạn mở trang):</p>
                <div class="pg-tablewrap">
                    <table class="pg-table" data-pg-criteria>
                        <thead><tr><th>Tiêu chí</th><th>Trọng số</th><th>Lấy từ đâu</th></tr></thead>
                        <tbody>
                        @foreach($kpiCriteria as $pgCriterion)
                            <tr>
                                <td>{{ $pgCriterion['name'] }}</td>
                                <td class="num">{{ $pgCriterion['weight'] !== null ? rtrim(rtrim(number_format($pgCriterion['weight'] * 100, 2, ',', '.'), '0'), ',').'%' : 'Chưa áp dụng' }}</td>
                                <td>{{ $pgSources[$pgCriterion['code']] ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @include('technical.guides.partials.note', ['tone' => 'info', 'title' => 'Không tự cộng điểm theo công trình',
                    'text' => 'Mỗi công trình <strong>không</strong> tự mang về một số điểm cố định. Điểm mỗi tiêu chí = tỷ lệ công trình/phiếu/việc <em>đạt</em> trên tổng số được tính trong kỳ, nhân trọng số. Thiếu nguồn hoặc chưa xác nhận thì tiêu chí báo <strong>thiếu dữ liệu</strong>, không tự thành đạt hay 0.'])
            </section>

            {{-- 4. Kỹ thuật --}}
            <section class="tg-section" id="ky-thuat">
                <h2 class="tg-section__title"><span class="num">4</span> Kỹ thuật: nhập kết quả nghiệm thu</h2>
                <p>Bạn chỉ nhập được khi được <strong>giao bước Nghiệm thu</strong> (tên ở ô <em>Phụ trách chính</em> hoặc <em>Phối hợp</em>) và các bước trước đã duyệt.</p>

                @include('technical.guides.partials.step', [
                    'num' => 1, 'title' => 'Tải đủ hồ sơ nghiệm thu',
                    'desc' => 'Ở bảng <strong>HỒ SƠ NGHIỆM THU</strong>, mỗi dòng bấm <strong>Tải lên</strong> và chọn file đúng định dạng. Cột <em>Trạng thái</em> báo <strong>Thiếu N</strong> hoặc <strong>Đã đủ</strong>.',
                    'shot' => 'images/guides/projects/p02-ho-so-nghiem-thu.png',
                    'alt' => 'Bảng hồ sơ nghiệm thu với dòng còn thiếu và dòng đã đủ, nút Tải lên',
                    'caption' => 'Dữ liệu TEST. (1) Dòng còn thiếu file · (2) Nút Tải lên · (3) Dòng đã đủ.',
                    'result' => 'Hồ sơ đủ <span class="pg-kpi-state no">Chưa tính KPI</span> — tải đủ file mới là điều kiện gửi duyệt bước, chưa phải kết quả KPI.',
                ])

                @include('technical.guides.partials.step', [
                    'num' => 2, 'title' => 'Nhập kết quả và bấm Lưu nháp hoặc Gửi kiểm tra',
                    'desc' => 'Kéo xuống khối <strong>KẾT QUẢ NGHIỆM THU TÍNH KPI</strong>. Mỗi mục chọn <em>Chưa ghi nhận / Đạt / Không đạt</em> (phản ánh khách hàng: <em>Không có / Có phản ánh</em> + nội dung). Gồm 3 nhóm: Chất lượng &amp; phản ánh khách hàng, 5 mục An toàn HSE &amp; vệ sinh, EVN &amp; App giám sát.',
                    'bullets' => [
                        '<strong>Lưu nháp</strong>: giữ lại để làm tiếp. Nhóm hiện <em>Đã nhập (nháp)</em>.',
                        '<strong>Gửi kiểm tra</strong>: gửi trưởng phòng. Nhóm hiện <em>Chờ kiểm tra</em>. Mục nào còn thiếu sẽ hiện dòng <em>Còn thiếu: …</em>.',
                        'Không có nút tự chọn “đạt lần đầu”: hệ thống tự lấy số lần bị <em>Trả lại</em> ở Thi công/Nghiệm thu.',
                        'Ô <em>Ghi chú</em> + nút <strong>Lưu</strong> ở phía trên là lưu dữ liệu bước (ngày bàn giao, ghi chú) — <strong>không</strong> phải gửi kết quả KPI.',
                    ],
                    'shot' => 'images/guides/projects/p03-ky-thuat-khai-ket-qua.png',
                    'alt' => 'Khối Kết quả nghiệm thu tính KPI: chọn Đạt/Không đạt cho từng mục và hai nút Lưu nháp, Gửi kiểm tra',
                    'caption' => 'Dữ liệu TEST. (1) Chọn kết quả từng mục · (2) Lưu nháp · (3) Gửi kiểm tra.',
                    'result' => 'Nháp hoặc đã gửi <span class="pg-kpi-state no">Chưa tính KPI</span> — phải được trưởng phòng xác nhận và Admin duyệt nghiệm thu.',
                ])

                @include('technical.guides.partials.step', [
                    'num' => 3, 'title' => 'Bị trả bổ sung: sửa rồi gửi lại',
                    'desc' => 'Nếu trưởng phòng trả, khối hiện thẻ <strong>TRẢ BỔ SUNG KẾT QUẢ KPI</strong> với người trả, nhóm và lý do. Sửa đúng nội dung (hoặc tải thêm minh chứng) rồi bấm lại <strong>Gửi kiểm tra</strong>.',
                    'shot' => 'images/guides/projects/p05-tra-bo-sung.png',
                    'alt' => 'Thẻ Trả bổ sung kết quả KPI với lý do của trưởng phòng, nhóm HSE ở trạng thái Cần bổ sung',
                    'caption' => 'Dữ liệu TEST. (1) Lý do trả bổ sung · (2) Nhóm cần bổ sung.',
                    'result' => 'Nhóm đã sửa phải được <strong>xác nhận lại</strong>; nhóm không sửa giữ nguyên xác nhận.',
                ])

                @include('technical.guides.partials.step', [
                    'num' => 4, 'title' => 'Gửi duyệt bước Nghiệm thu',
                    'desc' => 'Khi hồ sơ đủ, ở dòng <strong>TRẠNG THÁI DUYỆT</strong> cuối trang bấm <strong>Gửi duyệt</strong> (nút mờ nếu còn thiếu file). Đây là gửi Admin duyệt cả bước Nghiệm thu, khác với <em>Gửi kiểm tra</em> kết quả KPI.',
                    'result' => 'Trạng thái chuyển <em>Chờ duyệt</em>. <span class="pg-kpi-state no">Chưa tính KPI</span> cho tới khi Admin duyệt.',
                ])
            </section>

            {{-- 5. Trưởng phòng --}}
            <section class="tg-section" id="truong-phong">
                <h2 class="tg-section__title"><span class="num">5</span> Trưởng phòng: kiểm tra, xác nhận hoặc trả bổ sung</h2>
                @include('technical.guides.partials.step', [
                    'num' => 1, 'title' => 'Xem bản đã gửi và minh chứng',
                    'desc' => 'Mở công trình → <strong>Nghiệm thu</strong>. Mỗi nhóm hiện kết quả kỹ thuật đã gửi và link file minh chứng. Khu <strong>Trưởng phòng kiểm tra</strong> chỉ hiện khi có nhóm <em>Chờ kiểm tra</em>.',
                    'bullets' => [
                        'Tick nhóm đủ dữ liệu → <strong>Xác nhận nhóm đã chọn</strong>. Nhóm còn thiếu không tick được.',
                        'Ghi lý do (bắt buộc), chọn nhóm cần sửa → <strong>Trả bổ sung</strong>. Không chọn nhóm = trả tất cả.',
                        'Người được giao/đã nhập bản đó <strong>không tự xác nhận</strong> được. Admin là ngoại lệ hiện có và được ghi lại.',
                    ],
                    'shot' => 'images/guides/projects/p04-truong-phong-xac-nhan.png',
                    'alt' => 'Khu Trưởng phòng kiểm tra: ô chọn từng nhóm, nút Xác nhận nhóm đã chọn và khung Trả bổ sung',
                    'caption' => 'Dữ liệu TEST. (1) Chọn nhóm đủ dữ liệu → Xác nhận · (2) Ghi lý do → Trả bổ sung.',
                    'result' => 'Xác nhận: nhóm hiện <em>Đã xác nhận — đạt/không đạt</em> kèm người/giờ. Trả bổ sung: nhóm về <em>Cần bổ sung</em>. Cả hai <span class="pg-kpi-state no">Chưa tính KPI</span> cho tới khi Admin duyệt nghiệm thu.',
                ])
                @include('technical.guides.partials.note', ['tone' => 'warning', 'title' => 'Xác nhận nhóm không phải duyệt nghiệm thu',
                    'text' => 'Trưởng phòng xác nhận <strong>dữ liệu KPI</strong>. Duyệt <strong>bước Nghiệm thu</strong> là việc của Admin ở dòng Trạng thái duyệt.'])
            </section>

            {{-- 6. Admin --}}
            <section class="tg-section" id="admin">
                <h2 class="tg-section__title"><span class="num">6</span> Admin: duyệt nghiệm thu cuối</h2>
                @include('technical.guides.partials.step', [
                    'num' => 1, 'title' => 'Bấm Duyệt ở dòng Trạng thái duyệt',
                    'desc' => 'Khi bước ở <em>Chờ duyệt</em>, Admin thấy nút <strong>Duyệt</strong> (hoặc <strong>Duyệt ngoại lệ</strong> nếu còn thiếu file — có hỏi lại) và <strong>Trả hồ sơ cần sửa</strong>. Phía trên có cảnh báo số nhóm KPI đã được trưởng phòng xác nhận.',
                    'shot' => 'images/guides/projects/p06-admin-duyet-nghiem-thu.png',
                    'alt' => 'Dòng Trạng thái duyệt của Admin: Chờ duyệt, nút Duyệt và Trả hồ sơ cần sửa',
                    'caption' => 'Dữ liệu TEST, tài khoản Admin. (1) Trạng thái Chờ duyệt · (2) Nút Duyệt.',
                    'result' => 'Bước thành <em>Đã duyệt</em>. <span class="pg-kpi-state yes">Được xét KPI</span> trong tháng duyệt — nhóm đã xác nhận được tính đạt/không đạt; nhóm chưa xác nhận báo thiếu dữ liệu.',
                ])
                @include('technical.guides.partials.note', ['tone' => 'info', 'title' => 'Sau khi duyệt',
                    'text' => 'Kỹ thuật và trưởng phòng không sửa được kết quả KPI và minh chứng đã xác nhận nữa; Admin vẫn sửa được (có ghi lịch sử) — trừ khi kỳ KPI/lương đã chốt (xem mục 9). Duyệt nghiệm thu không bảo đảm mọi tiêu chí đạt hay tổng KPI 100%.'])
            </section>

            {{-- 7. Xem điểm --}}
            <section class="tg-section" id="xem-diem">
                <h2 class="tg-section__title"><span class="num">7</span> Xem điểm và nguồn trên trang KPI</h2>
                @include('technical.guides.partials.step', [
                    'num' => 1, 'title' => 'Mở trang KPI đúng người và tháng',
                    'desc' => 'Trong khối Kết quả nghiệm thu bấm <strong>Xem nguồn trên trang KPI</strong>, hoặc vào <strong>KPI kỹ thuật</strong>, chọn tháng và người. Dưới bảng 6 tiêu chí có <strong>Checklist nguồn theo tiêu chí</strong>: mỗi dòng ghi Thực hiện, KPI (nhận/mất điểm, chưa tính) và link mở lại công trình.',
                    'bullets' => [
                        'Công trình tính vào tháng <strong>Admin duyệt nghiệm thu</strong>, cho người được giao Khảo sát/Thi công/Nghiệm thu hoặc kỹ sư chính.',
                        '<em>Chưa tính — thiếu dữ liệu</em>: còn nhóm chưa xác nhận hoặc thiếu nguồn.',
                    ],
                    'shot' => 'images/guides/projects/p07-checklist-kpi.png',
                    'alt' => 'Checklist nguồn theo tiêu chí trên trang KPI: dòng công trình TEST nhận điểm Chất lượng kèm người xác nhận',
                    'caption' => 'Dữ liệu TEST. (1) Điểm của tiêu chí · (2) Dòng công trình đã xác nhận, bấm để mở lại công trình.',
                ])
                @include('technical.guides.partials.note', ['tone' => 'info', 'title' => 'Điểm khác tiền thực lĩnh',
                    'text' => 'Tiền KPI chỉ tính theo cấu hình <strong>đã duyệt</strong> có hiệu lực cho kỳ. Kỳ '.$pgMonth.': '.e($pgRuleText).' Bản cấu hình nháp chỉ dùng để xem trước. “Tổng dự kiến” chưa tính ngày công, bảo hiểm, thuế, tạm ứng — không phải thực lĩnh.'])
                @if($canOpenKpi)
                    <p><a href="{{ route('ky-thuat.kpis.index', ['period' => 'month', 'month' => $kpiMonth]) }}"><i class="bi bi-graph-up"></i> Mở trang KPI kỹ thuật tháng {{ $pgMonth }}</a></p>
                @endif
            </section>

            {{-- 8. Nhầm lẫn --}}
            <section class="tg-section" id="nham-lan">
                <h2 class="tg-section__title"><span class="num">8</span> Những điểm hay nhầm</h2>
                <div class="pg-tablewrap">
                    <table class="pg-table">
                        <thead><tr><th>Bạn thấy</th><th>Nghĩa là</th></tr></thead>
                        <tbody>
                            <tr><td>Vòng tròn % (ví dụ 86%, 91%) trên công trình</td><td>Tiến độ quy trình công trình — không phải % KPI hay % lương.</td></tr>
                            <tr><td>Hồ sơ <em>Đã đủ</em></td><td>Đủ file để gửi duyệt bước. Chưa có nghĩa kết quả KPI đã được xác nhận.</td></tr>
                            <tr><td><em>Đã nhập (nháp)</em> / <em>Chờ kiểm tra</em></td><td>Chưa tính KPI. Cần trưởng phòng xác nhận và Admin duyệt nghiệm thu.</td></tr>
                            <tr><td><em>Đã xác nhận — đạt</em></td><td>Trưởng phòng đã xác nhận dữ liệu. Vẫn cần Admin duyệt nghiệm thu mới vào KPI.</td></tr>
                            <tr><td>Nghiệm thu <em>Đã duyệt</em></td><td>Công trình được xét KPI tháng duyệt. Không bảo đảm mọi tiêu chí đạt.</td></tr>
                            <tr><td>Nút <strong>Lưu</strong> (ghi chú bước) và <strong>Lưu nháp</strong> (kết quả KPI)</td><td>Hai việc khác nhau: lưu dữ liệu bước ≠ lưu khai báo KPI.</td></tr>
                            <tr><td><strong>Gửi kiểm tra</strong> và <strong>Gửi duyệt</strong></td><td>Gửi kiểm tra = gửi kết quả KPI cho trưởng phòng. Gửi duyệt = gửi cả bước Nghiệm thu cho Admin.</td></tr>
                            <tr><td>Tiền KPI trên trang KPI</td><td>Theo cấu hình đang áp dụng; bản nháp chỉ xem trước. Không phải thực lĩnh.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- 9. Nguồn cũ, sửa lại, kỳ chốt --}}
            <section class="tg-section" id="nguon-cu">
                <h2 class="tg-section__title"><span class="num">9</span> Nguồn cũ, sửa sau xác nhận và kỳ đã chốt</h2>
                <ul>
                    <li><strong>Công trình có đánh giá dạng cũ</strong>: khối <em>Kết quả từ hồ sơ đã xác nhận</em> cho biết người/giờ đánh giá và kết quả đang dùng trên trang KPI. Form mới trống <strong>không</strong> có nghĩa điểm bị bỏ. Nếu Admin mở <em>Nhập lại kết quả theo luồng mới</em> và lưu, nguồn chuyển sang bản mới — nhóm chưa được xác nhận lại sẽ báo thiếu.</li>
                    <li><strong>Sửa sau khi đã xác nhận</strong>: đổi kết quả, tải thêm/thay minh chứng, đổi quyết định đấu nối hoặc bị <em>Trả lại</em> làm xác nhận của nhóm liên quan hết hiệu lực. Đổi về như cũ cũng không tự có lại — phải <strong>Gửi kiểm tra</strong> và được xác nhận lại.</li>
                    <li><strong>Kỳ KPI/lương đã chốt</strong>: khi phiếu lương KPI kỹ thuật hoặc bảng lương nhân sự của tháng đó đã duyệt (có dùng KPI kỹ thuật), khối hiện thông báo <em>Kỳ KPI … đã chốt</em> và mọi người — kể cả Admin — không sửa được nguồn KPI của công trình. Muốn điều chỉnh phải mở lại kỳ theo quy trình duyệt lương. Khóa này khác với “bước nghiệm thu đã duyệt”.</li>
                    <li>Khóa kỳ hiện áp dụng cho dữ liệu nghiệm thu, hồ sơ Khảo sát/Đề xuất/Nghiệm thu, đấu nối, phân công và trả lại của công trình; <strong>chưa</strong> áp dụng cho đề xuất vật tư.</li>
                </ul>
                @include('technical.guides.partials.lightbox-image', [
                    'src' => 'images/guides/projects/p08-khoa-ky.png',
                    'alt' => 'Thông báo Kỳ KPI đã chốt trên khối Kết quả nghiệm thu, không còn nút nhập',
                    'caption' => 'Dữ liệu TEST, tài khoản Admin. (1) Thông báo kỳ đã chốt — không còn nút Lưu nháp/Gửi kiểm tra, kể cả với Admin.',
                ])
            </section>

            <div class="tg-footer">
                <a href="{{ $returnUrl }}" class="btn btn-primary btn-sm" data-pg-return>
                    <i class="bi bi-arrow-left"></i> Quay lại công trình {{ $pgCode }}
                </a>
                <a href="#ban-can-lam-gi" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-up"></i> Lên đầu bài</a>
            </div>
        </div>

        <aside class="tg-toc" aria-label="Mục lục bài hướng dẫn">
            <p class="tg-toc__title">Trong bài này</p>
            <ol>
                @foreach($pgToc as $pgAnchor => $pgLabel)
                    <li><a href="#{{ $pgAnchor }}" data-tg-toc="{{ $pgAnchor }}">{{ $pgLabel }}</a></li>
                @endforeach
            </ol>
        </aside>
    </div>
</div>

{{-- Ảnh phóng to: Bootstrap Modal có sẵn (đóng bằng nút × hoặc phím Escape). --}}
<div class="modal fade" id="tgLightbox" tabindex="-1" aria-labelledby="tgLightboxLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h2 class="modal-title fs-6" id="tgLightboxLabel">Ảnh minh hoạ</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body text-center p-2">
                <img src="" alt="" id="tgLightboxImage" class="img-fluid">
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';
    var lightbox = document.getElementById('tgLightbox');
    if (lightbox) {
        lightbox.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            if (!trigger) { return; }
            var img = document.getElementById('tgLightboxImage');
            var label = document.getElementById('tgLightboxLabel');
            if (img) {
                img.setAttribute('src', trigger.getAttribute('data-tg-src') || '');
                img.setAttribute('alt', trigger.getAttribute('data-tg-alt') || '');
            }
            if (label) { label.textContent = trigger.getAttribute('data-tg-alt') || 'Ảnh minh hoạ'; }
        });
    }
    var links = Array.prototype.slice.call(document.querySelectorAll('[data-tg-toc]'));
    if (!links.length || !('IntersectionObserver' in window)) { return; }
    var byId = {};
    links.forEach(function (a) { byId[a.getAttribute('data-tg-toc')] = a; });
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var link = byId[entry.target.id];
            if (link && entry.isIntersecting) {
                links.forEach(function (a) { a.classList.remove('is-active'); });
                link.classList.add('is-active');
            }
        });
    }, { rootMargin: '-88px 0px -65% 0px', threshold: 0 });
    Object.keys(byId).forEach(function (id) { var s = document.getElementById(id); if (s) { observer.observe(s); } });
})();
</script>
@endpush
